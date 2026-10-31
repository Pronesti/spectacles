<?php

namespace App\Http;

use Illuminate\Support\Str;

class MusicBrainzPlaces
{
    public function __construct(private MusicBrainzClient $client, private SetlistFmDiscovery $discovery, private VenueImageClient $images) {}

    public function search(string $q): array
    {
        $terms = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        $escaped = array_map(function (string $term): string {
            foreach (str_split('\\+-!(){}[]^"~*?:/&|') as $character) {
                $term = str_replace($character, '\\'.$character, $term);
            }

            return $term.'*';
        }, $terms);
        $rows = $this->client->publicRead('musicbrainz:place-search:v3:'.hash('sha256', $q), 'place', ['query' => 'place:('.implode(' AND ', $escaped).')', 'fmt' => 'json', 'limit' => 10], function (array $body): array {
            if (! is_array($body['places'] ?? null) || ! array_is_list($body['places']) || count($body['places']) > 100) {
                throw new MusicBrainzUnavailable;
            }

            return array_map(fn ($raw) => is_array($raw) ? $this->normalize($raw) : throw new CatalogProviderUnavailable, array_slice($body['places'], 0, 10));
        });

        return ['data' => array_map(fn ($row) => $this->discovery->attachEntity($row, 'venue'), $rows), 'meta' => ['next_cursor' => null, 'partial' => true]];
    }

    public function detail(string $mbid): array
    {
        $value = $this->client->publicRead('musicbrainz:place:v3:'.$mbid, 'place/'.$mbid, ['inc' => 'aliases+url-rels', 'fmt' => 'json'], function (array $raw) use ($mbid): array {
            if (strtolower($raw['id'] ?? '') !== strtolower($mbid)) {
                throw new CatalogProviderUnavailable;
            }
            $value = $this->normalize($raw);
            $value['values']['image'] = $this->images->find($raw['relations'] ?? []);

            return $value;
        });

        return $this->discovery->attachEntity($value, 'venue');
    }

    private function normalize(array $raw): array
    {
        $id = $raw['id'] ?? '';
        if (! is_string($id) || ! Str::isUuid($id)) {
            throw new CatalogProviderUnavailable;
        }
        if (isset($raw['relations']) && (! is_array($raw['relations']) || ! array_is_list($raw['relations']) || count($raw['relations']) > 1000) || isset($raw['aliases']) && (! is_array($raw['aliases']) || ! array_is_list($raw['aliases']) || count($raw['aliases']) > 100)) {
            throw new CatalogProviderUnavailable;
        }
        $refs = [];
        $venueUrls = [];
        foreach ($raw['relations'] ?? [] as $relation) {
            if (($relation['target-type'] ?? null) === 'url' && in_array($relation['type'] ?? null, ['setlistfm', 'setlist.fm'], true)) {
                $url = $relation['url']['resource'] ?? '';
                $refs[] = ['provider' => 'setlist_fm', 'resourceType' => 'venue', 'externalId' => app(SetlistFmUrl::class)->id($url, 'venue')];
                $venueUrls[$url] = true;
            }
        }
        $aliases = [];
        foreach ($raw['aliases'] ?? [] as $alias) {
            $aliases[] = CatalogProviderData::text($alias['name'] ?? null);
        }
        $country = $raw['area']['iso-3166-1-codes'][0] ?? null;
        $value = ['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'place', 'externalId' => strtolower($id)], 'url' => 'https://musicbrainz.org/place/'.strtolower($id), 'linkedRefs' => $refs, 'values' => ['name' => $raw['name'] ?? null, 'city' => $raw['area']['name'] ?? '', 'region' => null, 'countryCode' => $country, 'address' => ($raw['address'] ?? '') ?: null, 'aliases' => array_values(array_unique($aliases)), 'lat' => $raw['coordinates']['latitude'] ?? null, 'lng' => $raw['coordinates']['longitude'] ?? null, 'timezone' => null, 'image' => null]];

        if (count($venueUrls) === 1) {
            $value['venueUrl'] = array_key_first($venueUrls);
        }

        $value['venueSources'] = array_map(fn ($source) => ['externalId' => app(SetlistFmUrl::class)->id($source, 'venue'), 'url' => $source], array_keys($venueUrls));

        return CatalogProviderData::entity($value);
    }
}
