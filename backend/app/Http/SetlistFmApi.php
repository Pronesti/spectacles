<?php

namespace App\Http;

class SetlistFmApi
{
    public function __construct(private SetlistFmClient $client, private ProviderReadGate $gate) {}

    public function search(array $query): array
    {
        ksort($query);

        return $this->gate->run('setlist_api', 'search:v1:'.json_encode($query), function () use ($query): array {
            $body = $this->client->api('search/setlists', $query);
            if (! is_array($body['setlist'] ?? null) || ! array_is_list($body['setlist']) || count($body['setlist']) > 100 || ! is_int($body['total'] ?? null) || $body['total'] < 0 || ($body['page'] ?? null) !== ($query['p'] ?? 1) || ! is_int($body['itemsPerPage'] ?? null) || $body['itemsPerPage'] < 1 || $body['itemsPerPage'] > 100 || count($body['setlist']) > $body['itemsPerPage']) {
                throw new CatalogProviderUnavailable;
            }
            $rows = [];
            foreach ($body['setlist'] as $raw) {
                if (! is_array($raw)) {
                    throw new CatalogProviderUnavailable;
                }
                $value = $this->performance($raw);
                if (isset($query['artistMbid']) && $value['artist']['ref']['externalId'] !== $query['artistMbid'] || isset($query['venueId']) && $value['venue']['ref']['externalId'] !== $query['venueId']) {
                    throw new CatalogProviderUnavailable;
                }
                if (isset($rows[$value['ref']['externalId']])) {
                    throw new CatalogProviderUnavailable;
                }
                $rows[$value['ref']['externalId']] = $value;
            }

            return ['items' => array_values($rows), 'more' => $body['total'] > $body['page'] * $body['itemsPerPage']];
        }, true);
    }

    public function detail(string $id): array
    {
        return $this->gate->run('setlist_api', 'detail:v1:'.$id, function () use ($id): array {
            $value = $this->performance($this->client->api('setlist/'.$id));
            if ($value['ref']['externalId'] !== $id) {
                throw new CatalogProviderUnavailable;
            }

            return $value;
        }, true);
    }

    public function venue(string $id): array
    {
        return $this->gate->run('setlist_api', 'venue:v1:'.$id, function () use ($id): array {
            $value = $this->venueData($this->client->api('venue/'.$id));
            if ($value['ref']['externalId'] !== $id) {
                throw new CatalogProviderUnavailable;
            }

            return $value;
        }, true);
    }

    private function venueData(array $raw): array
    {
        $city = $raw['city'] ?? [];
        if (! is_array($city) || ! is_array($city['country'] ?? []) || ! is_array($city['coords'] ?? [])) {
            throw new CatalogProviderUnavailable;
        }

        return CatalogProviderData::entity([
            'ref' => ['provider' => 'setlist_fm', 'resourceType' => 'venue', 'externalId' => $raw['id'] ?? null],
            'url' => $raw['url'] ?? null,
            'values' => ['name' => $raw['name'] ?? null, 'city' => $city['name'] ?? '', 'region' => $city['state'] ?? null, 'countryCode' => isset($city['country']['code']) && is_string($city['country']['code']) ? strtoupper($city['country']['code']) : null],
        ]);
    }

    private function performance(array $raw): array
    {
        $artist = $raw['artist'] ?? [];
        $venue = $raw['venue'] ?? [];
        $tour = $raw['tour'] ?? null;
        $date = $raw['eventDate'] ?? null;
        if (! is_array($artist) || ! is_array($venue) || $tour !== null && ! is_array($tour) || ! is_string($date) || ! preg_match('/^(\d{2})-(\d{2})-(\d{4})$/D', $date, $parts)) {
            throw new CatalogProviderUnavailable;
        }
        $value = CatalogProviderData::performance([
            'ref' => ['provider' => 'setlist_fm', 'resourceType' => 'setlist', 'externalId' => $raw['id'] ?? null],
            'url' => $raw['url'] ?? null,
            'date' => ['year' => (int) $parts[3], 'month' => (int) $parts[2], 'day' => (int) $parts[1]],
            'artist' => ['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'artist', 'externalId' => $artist['mbid'] ?? null], 'url' => $artist['url'] ?? null, 'values' => ['name' => $artist['name'] ?? null, 'type' => null, 'disambiguation' => ($artist['disambiguation'] ?? '') === '' ? null : $artist['disambiguation']]],
            'venue' => $this->venueData($venue),
            'tour' => null,
            'time' => null,
            'timezone' => null,
        ]);
        $value['tourName'] = $tour === null ? null : CatalogProviderData::text($tour['name'] ?? null);

        return $value;
    }
}
