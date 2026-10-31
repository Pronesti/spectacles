<?php

namespace App\Http;

use App\Models\ArtistProviderId;
use App\Models\Event;
use App\Models\EventProviderReference;
use App\Models\TourProviderId;
use App\Models\VenueProviderId;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SetlistFmDiscovery
{
    public function __construct(private SetlistFmClient $client, private SetlistFmHtml $html, private ProviderReadGate $gate, private CatalogProviderSelection $proofs) {}

    public function artistMbid(array $scope): ?string
    {
        if (isset($scope['artistMbid'])) {
            return strtolower($scope['artistMbid']);
        }
        if (isset($scope['artistUuid'])) {
            $id = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')->whereHas('artist', fn ($q) => $q->where('external_id', $scope['artistUuid']))->value('provider_resource_id');
            if ($id === null) {
                throw ValidationException::withMessages(['artistUuid' => 'Select an Artist with a MusicBrainz identity or enter details manually.']);
            }

            return $id;
        }

        return null;
    }

    private function artist(string $mbid): array
    {
        return $this->artistPage($this->client->artistUrl($mbid), $mbid);
    }

    private function artistPage(string $url, ?string $mbid = null): array
    {
        $id = app(SetlistFmUrl::class)->id($url, 'artist');
        $page = $this->gate->run('setlist_html', 'artist-page:v3:'.$id, function () use ($url, $id): array {
            $html = $this->client->html($url);
            $artist = $this->html->artist($html, $url);
            try {
                $tours = $this->html->tours($html, $id);
            } catch (CatalogProviderUnavailable) {
                $tours = null;
            }

            try {
                $listings = $this->html->search($html, $url, true);
            } catch (CatalogProviderUnavailable) {
                $listings = null;
            }

            return ['artist' => $artist, 'tours' => $tours, 'listings' => $listings];
        }, true);
        if ($mbid !== null && $page['artist']['ref']['externalId'] !== $mbid) {
            throw new CatalogProviderUnavailable;
        }

        return $page;
    }

    public function tours(array $scope, string $q, ?string $cursor = null): array
    {
        $mbid = $this->artistMbid($scope);
        if ($mbid === null) {
            throw ValidationException::withMessages(['artistMbid' => 'Select one identified Artist.']);
        }
        $filters = ['artistMbid' => $mbid, 'q' => $q];
        [$page, $offset, $scanned] = $this->cursor('tours', $filters, $cursor);
        $artist = $this->artist($mbid);
        if ($artist['tours'] === null) {
            throw new CatalogProviderUnavailable;
        }
        $body = ['rows' => $artist['tours']];
        $term = mb_strtolower(Str::ascii($q));
        $rows = array_values(array_filter($body['rows'], fn ($row) => $term === '' || str_contains(mb_strtolower(Str::ascii($row['name'])), $term)));
        $items = [];
        foreach (array_slice($rows, $offset, 10) as $row) {
            $items[] = $this->attachEntity(['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'tour', 'externalId' => $row['id']], 'values' => ['name' => $row['name']], 'url' => $row['url']], 'tour');
        }
        $next = $offset + count($items);

        return $this->page($items, $next < count($rows) ? $this->next('tours', $filters, $page, $next, $scanned + count($items)) : null, true);
    }

    public function performances(array $filters): array
    {
        $cursor = $filters['cursor'] ?? null;
        unset($filters['cursor']);
        ksort($filters);
        [$page, $offset, $scanned] = $this->cursor('performances', $filters, $cursor);
        $mbid = $this->artistMbid($filters);
        if (isset($filters['venueUrl'])) {
            $this->venueUrl($filters['venueId'] ?? '', $filters['venueUrl']);
        }
        if ($mbid === null && ! isset($filters['venueId'])) {
            throw ValidationException::withMessages(['artistMbid' => 'Select an identified Artist or Venue.']);
        }
        $query = $page > 1 ? ['page' => $page] : [];
        $artistId = null;
        $artistPage = null;
        if ($mbid !== null) {
            $artistPage = $this->artist($mbid);
            $artist = $artistPage['artist'];
            $artistId = app(SetlistFmUrl::class)->id($artist['url'], 'artist');
            $baseUrl = $artist['url'];
            foreach (['venueId' => 'venue', 'tourId' => 'tour', 'year' => 'year', 'countryCode' => 'country'] as $from => $to) {
                if (isset($filters[$from])) {
                    $query[$to] = $from === 'countryCode' ? strtolower($filters[$from]) : $filters[$from];
                }
            }
        } else {
            $baseUrl = $this->venueUrl($filters['venueId'], $filters['venueUrl'] ?? null);
            if (isset($filters['tourId']) || isset($filters['countryCode'])) {
                throw ValidationException::withMessages(['artistMbid' => 'Select an identified Artist for Tour or country filters.']);
            }
            if (isset($filters['year'])) {
                $query['year'] = $filters['year'];
            }
        }
        $pageUrl = $baseUrl.($query === [] ? '' : '?'.http_build_query($query));
        if ($query === [] && $artistPage !== null) {
            $result = $artistPage['listings'];
            if ($result === null) {
                throw new CatalogProviderUnavailable;
            }
        } else {
            $result = $this->gate->run('setlist_html', 'listing:v1:'.$pageUrl, function () use ($baseUrl, $pageUrl, $query, $artistId, $mbid, $filters): array {
                $html = $this->client->html($baseUrl, $query);
                if ($artistId !== null) {
                    $this->html->artist($html, $baseUrl, $mbid);
                } else {
                    $this->html->venue($html, $filters['venueId']);
                }
                $this->html->listingFilters($html, $query);

                return $this->html->search($html, $pageUrl, true);
            }, true);
        }
        $rows = $result['items'];
        $more = $filters['period'] === 'past' && $result['nextUrl'] !== null;
        if ($more) {
            $nextUrl = $result['nextUrl'];
            parse_str(parse_url($nextUrl, PHP_URL_QUERY) ?? '', $nextQuery);
            $expected = [...$query, 'page' => $page + 1];
            ksort($nextQuery);
            ksort($expected);
            if (parse_url($nextUrl, PHP_URL_PATH) !== parse_url($baseUrl, PHP_URL_PATH) || $nextQuery != $expected) {
                throw new CatalogProviderUnavailable;
            }
        }
        foreach ($rows as $row) {
            if ($artistId !== null && ($row['artistId'] ?? null) !== $artistId || isset($filters['venueId'], $row['venueId']) && $row['venueId'] !== $filters['venueId']) {
                throw new CatalogProviderUnavailable;
            }
        }
        if ($offset > count($rows)) {
            throw ValidationException::withMessages(['cursor' => 'Search again.']);
        }
        $items = [];
        while ($offset < count($rows) && count($items) < 10 && $scanned < 100) {
            $row = $rows[$offset++];
            $scanned++;
            $dateKey = sprintf('%04d-%02d-%02d', $row['date']['year'], $row['date']['month'], $row['date']['day']);
            $terms = preg_split('/\s+/u', mb_strtolower(trim($filters['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            $haystack = mb_strtolower(implode(' ', [$row['artistName'], $row['venueName'], $row['city'], $row['tourName'] ?? '', $dateKey]));
            if (array_any($terms, fn ($term) => ! str_contains($haystack, $term)) || isset($filters['venueId']) && ($row['venueId'] ?? null) !== $filters['venueId'] || isset($filters['countryCode']) && ($row['upcomingWidget'] ?? false)) {
                continue;
            }
            if (($filters['period'] === 'upcoming' ? $dateKey < now()->format('Y-m-d') : $dateKey > now()->format('Y-m-d')) || isset($filters['tourId']) && ($row['tourId'] ?? null) !== $filters['tourId'] || isset($filters['date']) && $dateKey !== $filters['date'] || isset($filters['year']) && $row['date']['year'] !== (int) $filters['year']) {
                continue;
            }
            $summary = $this->summary($row);
            $manual = [];
            if (isset($artist, $row['venueUrl'], $row['venueId'])) {
                $manual = ['manual' => ['artist' => $artist, 'venue' => CatalogProviderData::entity(['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'venue', 'externalId' => $row['venueId']], 'url' => $row['venueUrl'], 'linkedRefs' => [], 'values' => ['name' => $row['venueName'], 'city' => $row['city']]]), 'date' => $row['date'], 'tourName' => $row['tourName'], 'time' => null, 'timezone' => null]];
            }
            $summary['locator'] = $this->proofs->issue('locator', [...$summary, 'period' => $filters['period'], ...$manual]);
            $items[] = $summary;
        }
        $hasMore = $offset < count($rows) || $more;
        if ($offset >= count($rows)) {
            $page++;
            $offset = 0;
        }
        $allowed = $hasMore && $page <= 10 && $scanned < 100;

        return $this->page($items, $allowed ? $this->next('performances', $filters, $page, $offset, $scanned) : null, true);
    }

    public function preview(string $id, string $locator): array
    {
        $bound = $this->proofs->read($locator, 'locator', 'locator');
        if ($bound['ref']['externalId'] !== $id) {
            throw ValidationException::withMessages(['locator' => 'Select the performance again.']);
        }
        $known = EventProviderReference::query()->where('provider', 'setlist_fm')->where('resource_type', 'setlist')->where('provider_resource_id', $id)->first()?->event;
        if ($known !== null && isset($bound['manual'])) {
            $value = CatalogProviderData::performance([...$bound['manual'], 'ref' => $bound['ref'], 'url' => $bound['url'], 'tour' => null]);
        } else {
            try {
                $parsed = $this->gate->run('setlist_html', 'detail:v3:'.$id, function () use ($bound, $id): array {
                    $html = $this->client->html($bound['url']);
                    $artistUrl = $this->html->performanceArtistUrl($html, $bound['url']);
                    $descriptor = ['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'artist', 'externalId' => '00000000-0000-4000-8000-000000000001'], 'values' => ['name' => 'Unverified artist', 'type' => null, 'disambiguation' => null], 'url' => $artistUrl];

                    return $this->html->performance($html, $bound['url'], $id, $descriptor);
                }, true);
                $artist = $this->artistPage($parsed['artist']['url'])['artist'];
                if (isset($bound['manual']) && $artist['ref'] !== $bound['manual']['artist']['ref']) {
                    throw new CatalogProviderUnavailable;
                }
                $value = CatalogProviderData::performance([...$parsed, 'artist' => $artist]);
            } catch (CatalogProviderUnavailable $failure) {
                $manual = $bound['manual'] ?? null;
                if ($manual !== null) {
                    $manual['artist']['selection'] = app(MusicBrainzSelection::class)->issue('artist', $manual['artist']['ref']['externalId']);
                    $savedArtist = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')->where('provider_resource_id', $manual['artist']['ref']['externalId'])->first()?->artist;
                    $manual['artist']['localId'] = $savedArtist?->external_id;
                    if ($savedArtist !== null) {
                        $manual['artist']['values'] = ['name' => $savedArtist->name, 'type' => $savedArtist->type, 'disambiguation' => $savedArtist->disambiguation];
                    }
                    $manual['venue'] = $this->attachEntity($manual['venue'], 'venue');
                }
                throw new CatalogProviderUnavailable($failure->status, $failure->retryAfter, $manual, $failure->reason);
            }
        }
        $summary = $this->summary($value);
        $local = $summary['localEventId'] === null ? null : $this->localEvent(Event::where('external_id', $summary['localEventId'])->firstOrFail());
        $artist = $value['artist'];
        $artist['selection'] = app(MusicBrainzSelection::class)->issue('artist', $artist['ref']['externalId']);
        $localArtist = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')->where('provider_resource_id', $artist['ref']['externalId'])->first()?->artist;
        $artist['localId'] = $localArtist?->external_id;
        if ($localArtist !== null) {
            $artist['values'] = ['name' => $localArtist->name, 'type' => $localArtist->type, 'disambiguation' => $localArtist->disambiguation];
        }

        return [...$summary, 'locator' => $locator, 'artist' => $artist, 'tour' => $value['tour'] === null ? null : $this->attachEntity($value['tour'], 'tour'), 'venue' => $this->attachEntity($value['venue'], 'venue'), 'time' => $value['time'], 'timezone' => $value['timezone'], 'selection' => $known === null ? $this->proofs->issue('performance', $value) : $locator, 'localEvent' => $local];
    }

    private function venueUrl(string $id, ?string $url): string
    {
        if ($url === null) {
            $url = VenueProviderId::query()->where('provider', 'setlist_fm')->where('resource_type', 'venue')->where('provider_resource_id', $id)->value('source_url');
        }
        if ($url === null) {
            throw ValidationException::withMessages(['venueUrl' => 'Choose a Venue with a confirmed source URL or enter details manually.']);
        }
        try {
            if (app(SetlistFmUrl::class)->id($url, 'venue') !== $id || parse_url($url, PHP_URL_QUERY) !== null) {
                throw new CatalogProviderUnavailable;
            }
        } catch (CatalogProviderUnavailable) {
            throw ValidationException::withMessages(['venueUrl' => 'Choose the source Venue again.']);
        }

        return $url;
    }

    public function venue(string $id, ?string $url = null): array
    {
        $url = $this->venueUrl($id, $url);
        $value = $this->gate->run('setlist_html', 'venue:v2:'.$url, fn () => $this->html->venue($this->client->html($url), $id), true);

        return $this->attachEntity($value, 'venue');
    }

    private function summary(array $value): array
    {
        $localId = EventProviderReference::query()->where('provider', 'setlist_fm')->where('resource_type', 'setlist')->where('provider_resource_id', $value['ref']['externalId'])->first()?->event->external_id;

        return ['ref' => $value['ref'], 'url' => $value['url'], 'date' => $value['date'], 'artistName' => $value['artist']['values']['name'] ?? $value['artistName'], 'venueName' => $value['venue']['values']['name'] ?? $value['venueName'], 'city' => $value['venue']['values']['city'] ?? $value['city'], 'localEventId' => $localId];
    }

    public function attachEntity(array $value, string $kind): array
    {
        $ref = $value['ref'];
        $class = $kind === 'tour' ? TourProviderId::class : VenueProviderId::class;
        $column = $kind === 'tour' ? 'external_provider_id' : 'provider_resource_id';
        $parents = [];
        foreach ([$ref, ...($value['linkedRefs'] ?? [])] as $identity) {
            $record = $class::query()->where('provider', $identity['provider'])->where('resource_type', $identity['resourceType'])->where($column, $identity['externalId'])->first();
            if ($record !== null) {
                $parents[$record->{$kind}->id] = $record->{$kind};
            }
        }
        abort_if(count($parents) > 1, 409, 'These provider identities belong to different saved records.');
        $local = array_values($parents)[0] ?? null;
        $value['localId'] = $local?->external_id;
        if ($local !== null && $kind === 'venue') {
            $value['values'] = CatalogProviderData::venueValues(['name' => $local->name, 'city' => $local->city ?? '', 'region' => $local->region, 'countryCode' => $local->country_code, 'address' => $local->address, 'aliases' => $local->aliases ?? [], 'lat' => $local->lat === null ? null : (float) $local->lat, 'lng' => $local->lng === null ? null : (float) $local->lng, 'timezone' => $local->timezone, 'image' => $local->image]);
        } elseif ($local !== null) {
            $value['values'] = ['name' => $local->name];
        }
        $value['selection'] = $this->proofs->issue($kind, $value);

        return $value;
    }

    public function localEvent(Event $event): array
    {
        $event->loadMissing(['venue', 'artistLinks.artist', 'tourLinks.tour.artistLinks.artist']);
        $artist = fn ($a) => ['id' => $a->external_id, 'name' => $a->name, 'type' => $a->type, 'disambiguation' => $a->disambiguation];
        $venue = $event->venue;

        return ['id' => $event->external_id, 'title' => $event->title, 'type' => $event->type, 'date' => ['year' => $event->year, 'month' => $event->month, 'day' => $event->day], 'time' => $event->local_start_time, 'timezone' => $event->timezone, 'venue' => $venue === null ? null : ['id' => $venue->external_id, 'name' => $venue->name, 'street' => $venue->street, 'house_number' => $venue->house_number, 'postal_code' => $venue->postal_code, 'city' => $venue->city, 'region' => $venue->region, 'country_code' => $venue->country_code, 'lat' => $venue->lat === null ? null : (float) $venue->lat, 'lng' => $venue->lng === null ? null : (float) $venue->lng, 'timezone' => $venue->timezone, 'address' => $venue->address, 'aliases' => $venue->aliases ?? [], 'image' => $venue->image], 'artists' => $event->artistLinks->map(fn ($link) => $artist($link->artist))->all(), 'tours' => $event->tourLinks->map(fn ($link) => ['id' => $link->tour->external_id, 'name' => $link->tour->name, 'artists' => $link->tour->artistLinks->map(fn ($a) => $artist($a->artist))->all()])->all()];
    }

    private function page(array $items, ?string $next, bool $partial): array
    {
        return ['data' => $items, 'meta' => ['next_cursor' => $next, 'partial' => $partial]];
    }

    private function cursor(string $operation, array $filters, ?string $cursor): array
    {
        if ($cursor === null) {
            return [1, 0, 0];
        }
        try {
            $value = json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
            if (($value['operation'] ?? null) !== $operation || ($value['scope'] ?? null) !== hash('sha256', json_encode($filters)) || ($value['expiresAt'] ?? 0) <= now()->timestamp || ! is_int($value['page'] ?? null) || $value['page'] < 1 || $value['page'] > 10 || ! is_int($value['offset'] ?? null) || $value['offset'] < 0 || $value['offset'] > 100 || ! is_int($value['scanned'] ?? null) || $value['scanned'] < 0 || $value['scanned'] >= 100) {
                throw new CatalogProviderUnavailable;
            }

            return [$value['page'], $value['offset'], $value['scanned']];
        } catch (\Throwable $failure) {
            throw ValidationException::withMessages(['cursor' => 'This search has expired. Search again.']);
        }
    }

    private function next(string $operation, array $filters, int $page, int $offset, int $scanned): string
    {
        return Crypt::encryptString(json_encode(['operation' => $operation, 'scope' => hash('sha256', json_encode($filters)), 'page' => $page, 'offset' => $offset, 'scanned' => $scanned, 'expiresAt' => now()->addMinutes(15)->timestamp]));
    }
}
