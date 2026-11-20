<?php

namespace App\Http;

use App\Http\Data\MusicBrainzQueryData;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderEntityValuesInputData;
use App\Http\Data\ProviderPageData;
use App\Http\Data\ProviderRefInputData;
use App\Http\Data\ProviderVenueData;
use App\Http\Data\VenueSourceInputData;
use App\Http\Integrations\MusicBrainz\Data\MusicBrainzEntityPayloadData;
use App\Http\Integrations\MusicBrainz\Data\MusicBrainzSearchPayloadData;
use Illuminate\Support\Str;

class MusicBrainzPlaces
{
    public function __construct(private MusicBrainzClient $client, private SetlistFmDiscovery $discovery, private VenueImageClient $images) {}

    public function search(string $q): ProviderPageData
    {
        $terms = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        $escaped = array_map(function (string $term): string {
            foreach (str_split('\\+-!(){}[]^"~*?:/&|') as $character) {
                $term = str_replace($character, '\\'.$character, $term);
            }

            return $term.'*';
        }, $terms);
        $rows = $this->client->publicRead('musicbrainz:place-search:v3:'.hash('sha256', $q), 'place', MusicBrainzQueryData::search('place:('.implode(' AND ', $escaped).')'), MusicBrainzSearchPayloadData::class, ProviderVenueData::class, function (MusicBrainzSearchPayloadData $page): array {
            if ($page->places === null || ! array_is_list($page->places) || count($page->places) > 100) {
                throw new MusicBrainzUnavailable;
            }

            return array_map(fn (mixed $place): ProviderVenueData => $this->normalize(UntrustedPayload::parse(MusicBrainzEntityPayloadData::class, $place, fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable)), array_slice($page->places, 0, 10));
        });

        return ProviderPageData::of(array_map(fn (ProviderVenueData $row): ProviderVenueData => $this->discovery->attachVenue($row), $rows), null, true);
    }

    public function detail(string $mbid): ProviderVenueData
    {
        $value = $this->client->publicRead('musicbrainz:place:v3:'.$mbid, 'place/'.$mbid, MusicBrainzQueryData::lookup('aliases+url-rels'), MusicBrainzEntityPayloadData::class, ProviderVenueData::class, function (MusicBrainzEntityPayloadData $place) use ($mbid): ProviderVenueData {
            if (strtolower($place->id ?? '') !== strtolower($mbid)) {
                throw new CatalogProviderUnavailable;
            }
            $value = $this->normalize($place);

            return $value->copyWith(values: $value->values->copyWith(image: $this->images->find($place->relations ?? [])));
        });

        return $this->discovery->attachVenue($value);
    }

    /**
     * Maps a MusicBrainz place to Venue entity input and validates it.
     */
    private function normalize(MusicBrainzEntityPayloadData $place): ProviderVenueData
    {
        $id = $place->id ?? '';
        if (! Str::isUuid($id)) {
            throw new CatalogProviderUnavailable;
        }
        if ($place->relations !== null && (! array_is_list($place->relations) || count($place->relations) > 1000) || $place->aliases !== null && (! array_is_list($place->aliases) || count($place->aliases) > 100)) {
            throw new CatalogProviderUnavailable;
        }
        $refs = [];
        $venueUrls = [];
        foreach ($place->relations ?? [] as $relation) {
            if ($relation->targetType === 'url' && in_array($relation->type, ['setlistfm', 'setlist.fm'], true)) {
                $url = $relation->url?->resource ?? '';
                $refs[] = new ProviderRefInputData('setlist_fm', 'venue', app(SetlistFmUrl::class)->id($url, 'venue'));
                $venueUrls[$url] = true;
            }
        }
        $aliases = [];
        foreach ($place->aliases ?? [] as $alias) {
            $aliases[] = CatalogProviderData::text($alias->name);
        }
        $countryCode = $place->area?->countryCodes[0] ?? null;
        if ($countryCode !== null && ! is_string($countryCode)) {
            throw new CatalogProviderUnavailable;
        }

        return CatalogProviderData::venue(new ProviderEntityInputData(
            ref: new ProviderRefInputData('musicbrainz', 'place', strtolower($id)),
            url: 'https://musicbrainz.org/place/'.strtolower($id),
            values: new ProviderEntityValuesInputData(name: $place->name, city: $place->area?->name ?? '', countryCode: $countryCode, address: $place->address ?: null, aliases: array_values(array_unique($aliases)), lat: $place->coordinates?->latitude, lng: $place->coordinates?->longitude),
            linkedRefs: $refs,
            venueUrl: count($venueUrls) === 1 ? array_key_first($venueUrls) : null,
            venueSources: array_map(fn (string $source): VenueSourceInputData => new VenueSourceInputData(app(SetlistFmUrl::class)->id($source, 'venue'), $source), array_keys($venueUrls)),
        ));
    }
}
