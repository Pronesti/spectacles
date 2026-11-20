<?php

namespace App\Http\Controllers;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderSelection;
use App\Http\CatalogProviderUnavailable;
use App\Http\Data\CatalogVenueData;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderPageData;
use App\Http\Data\ProviderPerformanceSearchData;
use App\Http\Data\ProviderTourSearchData;
use App\Http\Data\ProviderVenueData;
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
            $body = $page ? $value : ProviderPageData::of([$value], null, false);

            return response()->json($body)->header('Cache-Control', 'private, no-store');
        } catch (CatalogProviderUnavailable|MusicBrainzUnavailable $failure) {
            $response = response()->json(['message' => $failure->getMessage(), ...($failure instanceof CatalogProviderUnavailable && $failure->reason !== null ? ['reason' => $failure->reason] : [])], $failure->status)->header('Cache-Control', 'private, no-store');

            return $failure->status === 429 ? $response->header('Retry-After', (string) $failure->retryAfter) : $response;
        }
    }

    public function tours(CatalogProviderRequest $request, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(fn () => $discovery->tours(ProviderTourSearchData::from($request->validated())), true);
    }

    public function performances(CatalogProviderRequest $request, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(fn () => $discovery->performances(ProviderPerformanceSearchData::from($request->validated())), true);
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

        return response()->json(['data' => CatalogVenueData::fromVenue($venue, $uuid)])->header('Cache-Control', 'private, no-store');
    }

    public function confirmVenue(CatalogProviderRequest $request, CatalogProviderSelection $proofs, SetlistFmDiscovery $discovery): JsonResponse
    {
        return $this->respond(function () use ($request, $proofs, $discovery): ProviderVenueData {
            $values = array_map(fn (string $proof): ProviderVenueData => $proofs->venue($proof, 'selections'), $request->validated('selections'));
            $refs = [];
            $sources = [];
            $urls = [];
            foreach ($values as $value) {
                foreach ($value->identities() as $ref) {
                    $refs[$ref->key()] = $ref;
                }
                foreach ($value->venueSources as $source) {
                    $sources[$source->externalId] = $source;
                }
                if (is_string($value->venueUrl)) {
                    $urls[$value->venueUrl] = true;
                }
            }
            $localIds = [];
            foreach ($refs as $ref) {
                $id = VenueProviderId::where($ref->tuple())->value('venue_id');
                if ($id !== null) {
                    $localIds[$id] = true;
                }
            }
            abort_if(count($localIds) > 1, 409, 'These identities already belong to different saved Venues.');
            $primary = $values[0]->ref->resourceType === 'place' ? $values[0] : $values[1];
            unset($refs[$primary->ref->key()]);
            $merged = $primary->copyWith(linkedRefs: array_values($refs), venueSources: array_values($sources), venueUrl: count($urls) === 1 ? array_key_first($urls) : $primary->venueUrl);

            return $discovery->attachVenue(CatalogProviderData::venue(ProviderEntityInputData::from($merged)));
        });
    }
}
