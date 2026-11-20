<?php

namespace App\Http\Controllers\Venues;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderSelection;
use App\Http\Controllers\Controller;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderVenueData;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use App\Http\SetlistFmDiscovery;
use App\Models\VenueProviderId;
use Illuminate\Http\JsonResponse;

class ConfirmVenueController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, CatalogProviderSelection $proofs, SetlistFmDiscovery $discovery): JsonResponse
    {
        return ProviderResponse::from(function () use ($request, $proofs, $discovery): ProviderVenueData {
            $values = array_map(fn (string $proof): ProviderVenueData => $proofs->venue($proof, 'selections'), array_filter($request->safe()->array('selections'), is_string(...)));
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
                if (is_int($id) || is_string($id)) {
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
