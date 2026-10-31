<?php

namespace App\Http;

use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

class SetlistFmHtml
{
    private function document(string $html): DOMXPath
    {
        if ($html === '' || strlen($html) > 2097152) {
            throw new CatalogProviderUnavailable;
        }
        $old = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument;
            if (! $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new CatalogProviderUnavailable;
            }

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }

    private function css(string $class): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
    }

    private function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }

    private function canonical(DOMXPath $xpath, string $kind, ?string $expectedId = null): string
    {
        $links = $xpath->query('//link[@rel="canonical"]');
        if ($links->length !== 1) {
            throw new CatalogProviderUnavailable;
        }
        $url = app(SetlistFmUrl::class)->resolve($links[0]->getAttribute('href'));
        $id = app(SetlistFmUrl::class)->id($url, $kind);
        if ($expectedId !== null && $id !== $expectedId) {
            throw new CatalogProviderUnavailable;
        }

        return $url;
    }

    private function attributes(DOMXPath $xpath, string $kind): array
    {
        $matches = [];
        foreach ($xpath->query('//script[not(@src)]') as $script) {
            if (preg_match('/\bvar sfmPageAttributes = (\{[^\r\n]+\})\s*;?/', $script->textContent, $match)) {
                $value = json_decode($match[1], true);
                if (! is_array($value[$kind] ?? null)) {
                    throw new CatalogProviderUnavailable;
                }
                $matches[] = $value[$kind];
            }
        }
        if (count($matches) !== 1) {
            throw new CatalogProviderUnavailable;
        }

        return $matches[0];
    }

    public function artist(string $html, string $url, ?string $mbid = null): array
    {
        $xpath = $this->document($html);
        $canonical = $this->canonical($xpath, 'artist', app(SetlistFmUrl::class)->id($url, 'artist'));
        $data = $this->attributes($xpath, 'artist');
        $id = $data['mbid'] ?? null;
        if (! is_string($id) || ! Str::isUuid($id) || $mbid !== null && strtolower($id) !== $mbid || $xpath->query('//h3[normalize-space(.)="MBID"]/following-sibling::div[1]//a[@href="https://musicbrainz.org/artist/'.$id.'"]')->length !== 1) {
            throw new CatalogProviderUnavailable;
        }

        return CatalogProviderData::entity(['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'artist', 'externalId' => strtolower($id)], 'url' => $canonical, 'values' => ['name' => CatalogProviderData::text($data['name'] ?? null), 'type' => null, 'disambiguation' => null]]);
    }

    public function performanceArtistUrl(string $html, string $url): string
    {
        $xpath = $this->document($html);
        $links = $xpath->query('//*['.$this->css('ConcertHero-root').']//h1/a[@href] | //*['.$this->css('setlistInfo').']//*['.$this->css('setlistHeadline').']//h1/strong/a[@href]');
        if ($links->length !== 1) {
            throw new CatalogProviderUnavailable;
        }
        $artistUrl = app(SetlistFmUrl::class)->resolve($links[0]->getAttribute('href'), $url);
        app(SetlistFmUrl::class)->id($artistUrl, 'artist');

        return $artistUrl;
    }

    public function venue(string $html, string $id): array
    {
        $xpath = $this->document($html);
        $url = $this->canonical($xpath, 'venue', $id);
        $data = $this->attributes($xpath, 'venue');
        $scope = $xpath->query('//*['.$this->css('venueInfo').']');
        if ($scope->length !== 1) {
            throw new CatalogProviderUnavailable;
        }
        $address = $xpath->query('.//*['.$this->css('address').']', $scope[0]);
        $aliases = $xpath->query('.//span['.$this->css('label').' and normalize-space(.)="Also known as"]/following-sibling::span', $scope[0]);

        return CatalogProviderData::entity(['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'venue', 'externalId' => $id], 'url' => $url, 'linkedRefs' => [], 'values' => ['name' => $data['name'] ?? null, 'city' => $data['city'] ?? '', 'region' => null, 'countryCode' => $data['country'] ?? null, 'address' => $address->length === 1 ? $this->text($address[0]) : null, 'aliases' => array_map(fn ($node) => $this->text($node), iterator_to_array($aliases)), 'lat' => null, 'lng' => null, 'timezone' => null, 'image' => null]]);
    }

    public function tours(string $html, string $artistId): array
    {
        $xpath = $this->document($html);
        $artists = $xpath->query('//select[@name="artist"]/option[@selected]');
        if ($artists->length === 0) {
            $this->canonical($xpath, 'artist', $artistId);
        } elseif ($artists->length !== 1 || $artists[0]->getAttribute('value') !== $artistId) {
            throw new CatalogProviderUnavailable;
        }
        $options = $xpath->query('//select[@name="tour"]/option');
        if ($options->length === 0 || $options->length > 1000) {
            throw new CatalogProviderUnavailable;
        }
        $rows = [];
        foreach ($options as $option) {
            $id = $option->getAttribute('value');
            $name = preg_replace('/\s*\(\d[\d,.]*\)\s*$/u', '', $this->text($option));
            if ($option->hasAttribute('disabled') || in_array(mb_strtolower($name), ['all tours', 'no tour', 'other'], true) || $id === '') {
                continue;
            }
            $url = 'https://www.setlist.fm/search?'.http_build_query(['artist' => $artistId, 'tour' => $id]);
            app(SetlistFmUrl::class)->id($url, 'tour');
            $rows[$id] = ['id' => $id, 'name' => CatalogProviderData::text($name), 'url' => $url];
        }

        return array_values($rows);
    }

    private function links(DOMXPath $xpath, DOMNode $scope, string $kind, string $base): array
    {
        $pattern = match ($kind) {
            'artist' => '/setlists/', 'venue' => '/venue/', 'tour' => 'tour=', 'setlist' => '/setlist/',
        };
        $links = [];
        foreach ($xpath->query('.//a[@href]', $scope) as $link) {
            $href = $link->getAttribute('href');
            if (! str_contains('/'.$href, $pattern) || $kind === 'tour' && ! str_contains($href, 'search?')) {
                continue;
            }
            $url = app(SetlistFmUrl::class)->resolve($href, $base);
            $id = app(SetlistFmUrl::class)->id($url, $kind);
            $links[$id] = ['id' => $id, 'name' => CatalogProviderData::text($this->text($link)), 'url' => $url];
        }

        return array_values($links);
    }

    private function date(DOMXPath $xpath, DOMNode $scope, bool $upcoming = false): array
    {
        $parts = [];
        foreach (['year', 'month', 'day'] as $part) {
            $class = $upcoming ? 'ConcertHeroEventDate-'.$part : $part;
            $nodes = $xpath->query('.//*['.$this->css($class).']', $scope);
            if ($nodes->length !== 1) {
                throw new CatalogProviderUnavailable;
            }
            $parts[$part] = $this->text($nodes[0]);
        }
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $month = array_search($parts['month'], $months, true);
        if ($month === false || ! ctype_digit($parts['year']) || ! ctype_digit($parts['day']) || ! checkdate($month + 1, (int) $parts['day'], (int) $parts['year'])) {
            throw new CatalogProviderUnavailable;
        }

        return ['year' => (int) $parts['year'], 'month' => $month + 1, 'day' => (int) $parts['day']];
    }

    public function performance(string $html, string $url, string $expectedId, array $artist): array
    {
        $urls = app(SetlistFmUrl::class);
        if ($urls->id($url, 'setlist') !== $expectedId) {
            throw new CatalogProviderUnavailable;
        }
        $xpath = $this->document($html);
        $upcoming = str_contains($url, '/show/');
        $roots = $xpath->query('//*['.$this->css($upcoming ? 'ConcertHero-root' : 'setlistInfo').']');
        if ($roots->length !== 1) {
            throw new CatalogProviderUnavailable;
        }
        $scope = $roots[0];
        if (! $upcoming) {
            $canonical = $xpath->query('//link[@rel="canonical"]');
            if ($canonical->length !== 1 || $urls->id($canonical[0]->getAttribute('href'), 'setlist') !== $expectedId) {
                throw new CatalogProviderUnavailable;
            }
        } else {
            $selected = $xpath->query('//a['.$this->css('ConcertLineup-itemButton_selected').']');
            if ($selected->length !== 1 || $urls->id($urls->resolve($selected[0]->getAttribute('href'), $url), 'setlist') !== $expectedId) {
                throw new CatalogProviderUnavailable;
            }
        }
        $artists = $this->links($xpath, $scope, 'artist', $url);
        $venues = $this->links($xpath, $scope, 'venue', $url);
        $tours = $this->links($xpath, $scope, 'tour', $url);
        if (count($artists) !== 1 || count($venues) !== 1 || count($tours) > 1 || $artists[0]['id'] !== $urls->id($artist['url'] ?? '', 'artist')) {
            throw new CatalogProviderUnavailable;
        }
        if ($tours !== []) {
            parse_str(parse_url($tours[0]['url'], PHP_URL_QUERY) ?? '', $tourQuery);
            if (($tourQuery['artist'] ?? null) !== $artists[0]['id']) {
                throw new CatalogProviderUnavailable;
            }
        }
        $place = explode(',', $venues[0]['name']);
        $venue = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'venue', 'externalId' => $venues[0]['id']], 'values' => ['name' => trim(array_shift($place)), 'city' => trim(array_shift($place) ?? ''), 'region' => null, 'countryCode' => null, 'address' => null, 'aliases' => [], 'lat' => null, 'lng' => null, 'timezone' => null, 'image' => null], 'linkedRefs' => [], 'url' => $venues[0]['url']];
        $tour = $tours === [] ? null : ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'tour', 'externalId' => $tours[0]['id']], 'values' => ['name' => $tours[0]['name']], 'url' => $tours[0]['url']];

        return CatalogProviderData::performance(['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'setlist', 'externalId' => $expectedId], 'url' => $url, 'artist' => $artist, 'venue' => $venue, 'tour' => $tour, 'date' => $this->date($xpath, $scope, $upcoming), 'time' => null, 'timezone' => null]);
    }

    public function listingFilters(string $html, array $query): void
    {
        $xpath = $this->document($html);
        foreach ($query as $key => $value) {
            if ($key === 'page') {
                continue;
            }
            $selected = $xpath->query('//form//select[@name="'.$key.'"]/option[@selected]');
            if ($selected->length !== 1 || $selected[0]->getAttribute('value') !== (string) $value) {
                throw new CatalogProviderUnavailable;
            }
        }
    }

    public function search(string $html, string $pageUrl, bool $includeUpcoming = false): array
    {
        $xpath = $this->document($html);
        $main = $xpath->query('//*['.$this->css('mainColumn').']');
        if ($main->length === 0) {
            $main = $xpath->query('//*['.$this->css('rightColumn').']');
        }
        if ($main->length !== 1) {
            throw new CatalogProviderUnavailable;
        }
        $empty = $xpath->query('.//*['.$this->css('alert').' and '.$this->css('alert-info').']', $main[0]);
        foreach ($empty as $node) {
            if (str_contains($this->text($node), 'did not match any setlists.') && $xpath->query('//form//input[@name="query"]')->length > 0) {
                return ['items' => [], 'nextUrl' => null];
            }
        }
        $cards = $xpath->query('.//*['.$this->css('setlistPreview').']', $main[0]);
        $upcoming = $includeUpcoming ? $xpath->query('.//*['.$this->css('searchUpcomingSetlists').' or '.$this->css('upcomingSetlistsList').']//li['.$this->css('setlist').']', $main[0]) : new \ArrayObject;
        if ($cards->length === 0 && count($upcoming) === 0) {
            throw new CatalogProviderUnavailable;
        }
        $items = [];
        foreach ([...$upcoming, ...$cards] as $card) {
            $links = $xpath->query('.//h2/a[@href] | .//*['.$this->css('column').' and '.$this->css('date').']/a[@href]', $card);
            if ($links->length !== 1) {
                throw new CatalogProviderUnavailable;
            }
            $href = $links[0]->getAttribute('href');
            if (preg_match('~^(?:\.\./|/)?upcoming/[^/]+/\d{4}/[^/]+\.html$~', $href)) {
                continue;
            }
            $url = app(SetlistFmUrl::class)->resolve($links[0]->getAttribute('href'), $pageUrl);
            $id = app(SetlistFmUrl::class)->id($url, 'setlist');
            $artist = $this->links($xpath, $card, 'artist', $pageUrl);
            $venue = $this->links($xpath, $card, 'venue', $pageUrl);
            // Some listing cards omit artist links or identify multiple venues.
            // Skip those ambiguous cards without discarding independently verified results.
            if ($artist === [] || $venue === []) {
                // Upcoming search widget has only a show link; its selected Artist is in the scoped heading.
                $heading = $xpath->query('ancestor::*['.$this->css('searchUpcomingSetlists').'][1]/div/h2', $card);
                $content = $xpath->query('.//*['.$this->css('content').']//strong', $card);
                $city = $xpath->query('.//*['.$this->css('subline').']/span[1]', $card);
                if ($content->length !== 1 || $city->length !== 1) {
                    if ($xpath->evaluate('boolean('.$this->css('setlistPreview').')', $card)) {
                        continue;
                    }
                    throw new CatalogProviderUnavailable;
                }
                $date = substr(parse_url($url, PHP_URL_PATH), 6, 10);
                if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                    throw new CatalogProviderUnavailable;
                }
                $canonicalLinks = $xpath->query('//link[@rel="canonical"]');
                $venuePage = $canonicalLinks->length === 1 && str_contains($canonicalLinks[0]->getAttribute('href'), '/venue/');
                $selectedArtist = $xpath->query('//select[@name="artist"]/option[@selected]');
                if ($venuePage) {
                    $venueUrl = $this->canonical($xpath, 'venue');
                    $venueData = $this->attributes($xpath, 'venue');
                    $artistName = $this->text($content[0]);
                    $artistId = null;
                    $venue = [['id' => app(SetlistFmUrl::class)->id($venueUrl, 'venue'), 'url' => $venueUrl]];
                } elseif ($selectedArtist->length === 1 && $heading->length === 1 && preg_match('/^Upcoming (.+) Shows$/u', $this->text($heading[0]), $match)) {
                    $artistName = $match[1];
                    $artistId = $selectedArtist[0]->getAttribute('value');
                } else {
                    $canonical = $this->canonical($xpath, 'artist');
                    $identity = $this->artist($html, $canonical);
                    $artistName = $identity['values']['name'];
                    $artistId = app(SetlistFmUrl::class)->id($canonical, 'artist');
                }
                $artist = [['id' => $artistId]];
                $venueName = $venuePage ? CatalogProviderData::text($venueData['name'] ?? null) : $this->text($content[0]);
                $cityName = $venuePage ? CatalogProviderData::text($venueData['city'] ?? null) : trim(explode(',', $this->text($city[0]))[0]);
                $date = ['year' => (int) $parts[1], 'month' => (int) $parts[2], 'day' => (int) $parts[3]];
            } else {
                if (count($artist) !== 1 || count($venue) !== 1) {
                    continue;
                }
                $location = explode(',', $venue[0]['name']);
                $artistName = $artist[0]['name'];
                $venueName = trim($location[0]);
                $cityName = trim($location[1] ?? '');
                $date = $this->date($xpath, $card);
            }
            $tour = $this->links($xpath, $card, 'tour', $pageUrl);
            if (count($tour) > 1) {
                throw new CatalogProviderUnavailable;
            }
            if ($tour !== []) {
                parse_str(parse_url($tour[0]['url'], PHP_URL_QUERY) ?? '', $tourQuery);
                if (($tourQuery['artist'] ?? null) !== ($artist[0]['id'] ?? null)) {
                    throw new CatalogProviderUnavailable;
                }
            }
            $items[$id] = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'setlist', 'externalId' => $id], 'artistName' => $artistName, 'venueName' => $venueName, 'city' => $cityName, 'date' => $date, 'url' => $url, 'artistId' => $artist[0]['id'] ?? null, 'venueId' => $venue[0]['id'] ?? null, 'upcomingWidget' => str_starts_with(parse_url($url, PHP_URL_PATH), '/show/'), 'venueUrl' => $venue[0]['url'] ?? null, 'tourName' => $tour[0]['name'] ?? null, 'tourId' => ($tour[0]['id'] ?? null)];
            if (count($items) > 100) {
                throw new CatalogProviderUnavailable;
            }
        }
        $next = $xpath->query('.//*['.$this->css('listPagingNavigator').']//a[@title="Go to next page"] | .//*['.$this->css('pagination').']//a[@rel="next"] | .//*['.$this->css('pagination').']//li['.$this->css('next').']/a', $main[0]);
        $nextUrl = $next->length > 0 ? app(SetlistFmUrl::class)->resolve($next[0]->getAttribute('href'), $pageUrl) : null;

        return ['items' => array_values($items), 'nextUrl' => $nextUrl];
    }
}
