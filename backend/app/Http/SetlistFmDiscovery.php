<?php

namespace App\Http;

use App\Http\Data\ArtistValuesData;
use App\Http\Data\LocalEventData;
use App\Http\Data\ManualPerformanceData;
use App\Http\Data\ManualTourOptionData;
use App\Http\Data\PerformancePreviewData;
use App\Http\Data\PerformanceSummaryData;
use App\Http\Data\ProviderCursorData;
use App\Http\Data\ProviderCursorTokenData;
use App\Http\Data\ProviderLocatorData;
use App\Http\Data\ProviderPageData;
use App\Http\Data\ProviderPerformanceData;
use App\Http\Data\ProviderPerformanceSearchData;
use App\Http\Data\ProviderRefData;
use App\Http\Data\ProviderTourData;
use App\Http\Data\ProviderTourSearchData;
use App\Http\Data\ProviderVenueData;
use App\Http\Data\SetlistFmSearchQueryData;
use App\Http\Data\TourCursorScopeData;
use App\Http\Data\TourValuesData;
use App\Http\Data\VenueValuesData;
use App\Models\ArtistProviderId;
use App\Models\Event;
use App\Models\EventProviderReference;
use App\Models\Tour;
use App\Models\TourProviderId;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class SetlistFmDiscovery
{
    public function __construct(private SetlistFmClient $client, private CatalogProviderSelection $proofs) {}

    public function artistMbid(ProviderTourSearchData|ProviderPerformanceSearchData $search): ?string
    {
        if (is_string($search->artistMbid)) {
            return strtolower($search->artistMbid);
        }
        if (is_string($search->artistUuid)) {
            $id = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')->whereHas('artist', fn ($q) => $q->where('external_id', $search->artistUuid))->value('provider_resource_id');
            if ($id === null) {
                throw ValidationException::withMessages(['artistUuid' => 'Select an Artist with a MusicBrainz identity or enter details manually.']);
            }

            return $id;
        }

        return null;
    }

    public function tours(ProviderTourSearchData $search): ProviderPageData
    {
        $this->requireApi();
        $mbid = $this->artistMbid($search);
        if ($mbid === null) {
            throw ValidationException::withMessages(['artistMbid' => 'Select one identified Artist.']);
        }
        $q = is_string($search->q) ? $search->q : '';
        $scope = (new TourCursorScopeData($mbid, $q))->fingerprint();
        $position = $this->cursor('api-tours', $scope, $search->cursor);
        $result = app(SetlistFmApi::class)->search(new SetlistFmSearchQueryData(p: $position->page, artistMbid: $mbid, tourName: $q === '' ? new Optional : $q));
        $rows = $result->items;
        if ($position->offset > count($rows)) {
            throw ValidationException::withMessages(['cursor' => 'Search again.']);
        }
        $term = mb_strtolower(Str::ascii($q));
        $items = [];
        [$page, $offset, $scanned] = [$position->page, $position->offset, $position->scanned];
        while ($offset < count($rows) && count($items) < 10 && $scanned < 100) {
            $row = $rows[$offset++];
            $scanned++;
            $name = $row->namedTour();
            if ($name === null || ! str_contains(mb_strtolower(Str::ascii($name)), $term)) {
                continue;
            }
            $items[$name] = new ManualTourOptionData(manual: true, ref: null, values: new TourValuesData($name), url: $row->url, artistMbid: $mbid, selection: null, localId: null);
        }
        $more = $offset < count($rows) || $result->more;
        if ($offset >= count($rows)) {
            $page++;
            $offset = 0;
        }

        return $this->page(array_values($items), $more && $page <= 10 && $scanned < 100 ? $this->next('api-tours', $scope, new ProviderCursorData($page, $offset, $scanned)) : null, true);
    }

    public function performances(ProviderPerformanceSearchData $search): ProviderPageData
    {
        $this->requireApi();
        $scope = $search->cursorScope();
        $position = $this->cursor('api-performances', $scope, $search->cursor);
        $mbid = $this->artistMbid($search);
        if (is_string($search->venueUrl)) {
            $this->venueUrl(is_string($search->venueId) ? $search->venueId : '', $search->venueUrl);
        }
        if ($mbid === null && ! is_string($search->venueId)) {
            throw ValidationException::withMessages(['artistMbid' => 'Select an identified Artist or Venue.']);
        }
        if (is_string($search->tourId)) {
            throw ValidationException::withMessages(['tourId' => 'The API cannot verify website Tour IDs. Choose a Tour name or enter the details manually.']);
        }
        $result = app(SetlistFmApi::class)->search(new SetlistFmSearchQueryData(
            p: $position->page,
            artistMbid: $mbid ?? new Optional,
            venueId: $search->venueId,
            tourName: $search->tourName,
            year: $search->year,
            countryCode: $search->countryCode,
            date: is_string($search->date) ? substr($search->date, 8, 2).'-'.substr($search->date, 5, 2).'-'.substr($search->date, 0, 4) : new Optional,
        ));
        $rows = $result->items;
        if ($position->offset > count($rows)) {
            throw ValidationException::withMessages(['cursor' => 'Search again.']);
        }
        $term = mb_strtolower(Str::ascii(trim(is_string($search->q) ? $search->q : '')));
        $items = [];
        [$page, $offset, $scanned] = [$position->page, $position->offset, $position->scanned];
        while ($offset < count($rows) && count($items) < 10 && $scanned < 100) {
            $row = $rows[$offset++];
            $scanned++;
            $dateKey = sprintf('%04d-%02d-%02d', $row->date->year, $row->date->month, $row->date->day);
            if (($search->period === 'upcoming' ? $dateKey < now()->format('Y-m-d') : $dateKey > now()->format('Y-m-d')) || is_string($search->date) && $dateKey !== $search->date || is_int($search->year) && $row->date->year !== $search->year || is_string($search->countryCode) && $row->venue->values->countryCode !== $search->countryCode || is_string($search->tourName) && $row->namedTour() !== $search->tourName) {
                continue;
            }
            $haystack = mb_strtolower(Str::ascii(implode(' ', [$row->artist->values->name, $row->venue->values->name, $row->venue->values->city, $row->namedTour() ?? '', $dateKey])));
            if (array_any(preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY), fn ($part) => ! str_contains($haystack, $part))) {
                continue;
            }
            $manual = new ManualPerformanceData($row->artist, $row->venue, $row->date, $row->namedTour(), null, null);
            $items[] = $this->summary($row, $this->proofs->issue('locator', new ProviderLocatorData($row->ref, $row->url, $search->period, 'api', $manual)));
        }
        $more = $offset < count($rows) || $result->more;
        if ($offset >= count($rows)) {
            $page++;
            $offset = 0;
        }

        return $this->page($items, $more && $page <= 10 && $scanned < 100 ? $this->next('api-performances', $scope, new ProviderCursorData($page, $offset, $scanned)) : null, true);
    }

    public function preview(string $id, string $locator): PerformancePreviewData
    {
        $bound = $this->proofs->locator($locator, 'locator');
        if ($bound->ref->externalId !== $id) {
            throw ValidationException::withMessages(['locator' => 'Select the performance again.']);
        }
        $manual = $bound->manual instanceof ManualPerformanceData ? $bound->manual : null;
        $known = EventProviderReference::query()->where('provider', 'setlist_fm')->where('resource_type', 'setlist')->where('provider_resource_id', $id)->first()?->event;
        if ($known !== null && $manual !== null) {
            $value = new ProviderPerformanceData($bound->ref, $bound->url, $manual->date, $manual->artist, null, $manual->venue, $manual->time, $manual->timezone, $manual->tourName);
        } else {
            $value = app(SetlistFmApi::class)->detail($id);
            if ($manual !== null && ! $value->artist->ref->is($manual->artist->ref)) {
                throw new CatalogProviderUnavailable;
            }
        }
        $summary = $this->summary($value, $locator);
        $local = $summary->localEventId === null ? null : $this->localEvent(Event::where('external_id', $summary->localEventId)->firstOrFail());
        $localArtist = ArtistProviderId::query()->where($value->artist->ref->tuple())->first()?->artist;
        $artist = $value->artist->copyWith(
            values: $localArtist === null ? $value->artist->values : new ArtistValuesData($localArtist->name, $localArtist->type, $localArtist->disambiguation),
            selection: app(MusicBrainzSelection::class)->issue('artist', $value->artist->ref->externalId),
            localId: $localArtist?->external_id,
        );

        return PerformancePreviewData::expand(
            summary: $summary,
            artist: $artist,
            tour: $value->tour === null ? null : $this->attachTour($value->tour),
            tourName: $value->namedTour(),
            venue: $this->attachVenue($value->venue),
            time: $value->time,
            timezone: $value->timezone,
            selection: $known === null ? $this->proofs->issue('performance', $value) : $locator,
            localEvent: $local,
        );
    }

    private function venueUrl(string $id, string $url): string
    {
        try {
            if (app(SetlistFmUrl::class)->id($url, 'venue') !== $id || parse_url($url, PHP_URL_QUERY) !== null) {
                throw new CatalogProviderUnavailable;
            }
        } catch (CatalogProviderUnavailable) {
            throw ValidationException::withMessages(['venueUrl' => 'Choose the source Venue again.']);
        }

        return $url;
    }

    public function venue(string $id, ?string $url = null): ProviderVenueData
    {
        $this->requireApi();
        if ($url !== null) {
            $this->venueUrl($id, $url);
        }

        return $this->attachVenue(app(SetlistFmApi::class)->venue($id));
    }

    /**
     * setlist.fm discovery reads only the official API; without a key it is unavailable.
     */
    private function requireApi(): void
    {
        if (! $this->client->usesApi()) {
            throw new CatalogProviderUnavailable;
        }
    }

    private function summary(ProviderPerformanceData $value, string $locator): PerformanceSummaryData
    {
        $localId = EventProviderReference::query()->where($value->ref->tuple())->first()?->event->external_id;

        return new PerformanceSummaryData($value->ref, $value->url, $value->date, $value->artist->values->name, $value->venue->values->name, $value->venue->values->city, $localId, $locator);
    }

    /**
     * Matches the Tour to a saved one and issues the proof that lets the viewer select it.
     */
    public function attachTour(ProviderTourData $value): ProviderTourData
    {
        $local = $this->savedRecord(TourProviderId::class, 'external_provider_id', 'tour', [$value->ref]);
        $attached = $value->copyWith(values: $local === null ? $value->values : new TourValuesData($local->name), localId: $local?->external_id);

        return $attached->copyWith(selection: $this->proofs->issue('tour', $attached));
    }

    /**
     * Matches the Venue to a saved one and issues the proof that lets the viewer select it.
     */
    public function attachVenue(ProviderVenueData $value): ProviderVenueData
    {
        $local = $this->savedRecord(VenueProviderId::class, 'provider_resource_id', 'venue', $value->identities());
        $attached = $value->copyWith(values: $local === null ? $value->values : VenueValuesData::fromVenue($local), localId: $local?->external_id);

        return $attached->copyWith(selection: $this->proofs->issue('venue', $attached));
    }

    /**
     * @param  class-string<TourProviderId|VenueProviderId>  $class
     * @param  list<ProviderRefData>  $refs
     */
    private function savedRecord(string $class, string $idColumn, string $relation, array $refs): Tour|Venue|null
    {
        $parents = [];
        foreach ($refs as $identity) {
            $record = $class::query()->where($identity->tuple($idColumn))->first();
            if ($record !== null) {
                $parents[$record->{$relation}->id] = $record->{$relation};
            }
        }
        abort_if(count($parents) > 1, 409, 'These provider identities belong to different saved records.');

        return array_values($parents)[0] ?? null;
    }

    public function localEvent(Event $event): LocalEventData
    {
        return LocalEventData::fromEvent($event);
    }

    private function page(array $items, ?string $next, bool $partial): ProviderPageData
    {
        return ProviderPageData::of($items, $next, $partial);
    }

    /**
     * Reads a cursor bound to this operation and the fingerprint of these exact filters.
     */
    private function cursor(string $operation, string $scope, Optional|string $cursor): ProviderCursorData
    {
        if (! is_string($cursor)) {
            return ProviderCursorData::start();
        }
        try {
            $token = ProviderCursorTokenData::from(json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR));
            if ($token->operation !== $operation || $token->scope !== $scope || ($token->expiresAt ?? 0) <= now()->timestamp || $token->page === null || $token->page < 1 || $token->page > 10 || $token->offset === null || $token->offset < 0 || $token->offset > 100 || $token->scanned === null || $token->scanned < 0 || $token->scanned >= 100) {
                throw new CatalogProviderUnavailable;
            }

            return new ProviderCursorData($token->page, $token->offset, $token->scanned);
        } catch (\Throwable $failure) {
            throw ValidationException::withMessages(['cursor' => 'This search has expired. Search again.']);
        }
    }

    private function next(string $operation, string $scope, ProviderCursorData $position): string
    {
        return Crypt::encryptString(json_encode(new ProviderCursorTokenData($operation, $scope, $position->page, $position->offset, $position->scanned, now()->addMinutes(15)->timestamp)));
    }
}
