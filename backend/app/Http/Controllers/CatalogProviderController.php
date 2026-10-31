<?php

namespace App\Http\Controllers;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderSelection;
use App\Http\CatalogProviderUnavailable;
use App\Http\MusicBrainzPlaces;
use App\Http\MusicBrainzUnavailable;
use App\Http\Requests\CatalogProviderRequest;
use App\Http\SetlistFmDiscovery;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Http\JsonResponse;

class CatalogProviderController extends Controller
{
    private function respond(callable $fetch, bool $page = false): JsonResponse
    {
        try {
            $value = $fetch();
            $body = $page ? $value : ['data' => [$value], 'meta' => ['next_cursor' => null, 'partial' => false]];

            return response()->json($body)->header('Cache-Control', 'private, no-store');
        } catch (CatalogProviderUnavailable|MusicBrainzUnavailable $failure) {
            $response = response()->json(['message' => $failure->getMessage(), ...($failure instanceof CatalogProviderUnavailable && $failure->manual !== null ? ['manual' => $failure->manual] : []), ...($failure instanceof CatalogProviderUnavailable && $failure->reason !== null ? ['reason' => $failure->reason] : [])], $failure->status)->header('Cache-Control', 'private, no-store');

            return $failure->status === 429 ? $response->header('Retry-After', (string) $failure->retryAfter) : $response;
        }
    }

    public function tours(CatalogProviderRequest $request, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(fn () => $discovery->tours($request->validated(), $request->validated('q') ?? '', $request->validated('cursor')), true);
    }

    public function performances(CatalogProviderRequest $request, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(fn () => $discovery->performances($request->validated()), true);
    }

    public function preview(CatalogProviderRequest $request, string $id, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(fn () => $discovery->preview($id, $request->validated('locator')));
    }

    public function venue(CatalogProviderRequest $request, string $id, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(fn () => $discovery->venue($id, $request->validated('venueUrl')));
    }

    public function places(CatalogProviderRequest $request, MusicBrainzPlaces $places): JsonResponse
    {
        return $this->respond(fn () => $places->search($request->validated('q')), true);
    }

    public function place(CatalogProviderRequest $request, string $mbid, MusicBrainzPlaces $places): JsonResponse
    {
        return $this->respond(fn () => $places->detail($mbid));
    }

    public function catalogVenue(CatalogProviderRequest $request, string $uuid): JsonResponse
    {
        $venue = Venue::where('external_id', $uuid)->firstOrFail();
        $values = CatalogProviderData::venueValues(['name' => $venue->name, 'city' => $venue->city ?? '', 'region' => $venue->region, 'countryCode' => $venue->country_code, 'address' => $venue->address, 'aliases' => $venue->aliases ?? [], 'lat' => $venue->lat === null ? null : (float) $venue->lat, 'lng' => $venue->lng === null ? null : (float) $venue->lng, 'timezone' => $venue->timezone, 'image' => $venue->image]);
        $refs = $venue->providerIds->filter(fn ($r) => in_array($r->provider, ['musicbrainz', 'setlist_fm'], true) && in_array($r->resource_type, ['place', 'venue'], true))->map(fn ($r) => ['provider' => $r->provider, 'resourceType' => $r->resource_type, 'externalId' => $r->provider_resource_id])->values()->all();

        $venueUrl = $venue->providerIds->first(fn ($ref) => $ref->provider === 'setlist_fm' && $ref->resource_type === 'venue' && $ref->source_url !== null)?->source_url;
        $venueSources = $venue->providerIds->filter(fn ($ref) => $ref->provider === 'setlist_fm' && $ref->resource_type === 'venue' && $ref->source_url !== null)->map(fn ($ref) => ['externalId' => $ref->provider_resource_id, 'url' => $ref->source_url])->values()->all();

        return response()->json(['data' => ['localId' => $uuid, 'values' => $values, 'refs' => $refs, 'venueUrl' => $venueUrl, 'venueSources' => $venueSources]])->header('Cache-Control', 'private, no-store');
    }

    public function confirmVenue(CatalogProviderRequest $request, CatalogProviderSelection $proofs, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(function () use ($request, $proofs, $discovery): array {
            $values = array_map(fn ($p) => $proofs->read($p, 'venue', 'selections'), $request->validated('selections'));
            $refs = [];
            foreach ($values as $value) {
                foreach ([$value['ref'], ...$value['linkedRefs']] as $ref) {
                    $refs[json_encode($ref)] = $ref;
                }
            }
            $localIds = [];
            foreach ($refs as $ref) {
                $id = VenueProviderId::where('provider', $ref['provider'])->where('resource_type', $ref['resourceType'])->where('provider_resource_id', $ref['externalId'])->value('venue_id');
                if ($id !== null) {
                    $localIds[$id] = true;
                }
            }
            abort_if(count($localIds) > 1, 409, 'These identities already belong to different saved Venues.');
            $primary = ($values[0]['ref']['resourceType'] === 'place' ? $values[0] : $values[1]);
            unset($refs[json_encode($primary['ref'])]);
            $primary['linkedRefs'] = array_values($refs);
            $sources = [];
            foreach ($values as $value) {
                foreach ($value['venueSources'] ?? [] as $source) {
                    $sources[$source['externalId']] = $source;
                }
            }
            $primary['venueSources'] = array_values($sources);
            $urls = array_values(array_unique(array_filter(array_column($values, 'venueUrl'))));
            if (count($urls) === 1) {
                $primary['venueUrl'] = $urls[0];
            }

            return $discovery->attachEntity(CatalogProviderData::entity($primary), 'venue');
        });
    }
}
