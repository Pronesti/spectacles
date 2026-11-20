<?php

namespace App\Http\Controllers\Venues;

use App\Http\CatalogListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

class ListVenuesController extends Controller
{
    public function __invoke(CatalogReadRequest $request): JsonResponse
    {
        $query = Venue::query();
        $filter = $request->filter();
        if ($filter->hasText($filter->q)) {
            CatalogListing::text($query, ['venues.name'], $filter->q);
        }
        if ($filter->hasText($filter->city)) {
            CatalogListing::text($query, ['venues.city'], $filter->city);
        }
        if ($filter->countryCode !== null) {
            $query->where('country_code', $filter->countryCode);
        }

        return CatalogListing::respond($request, VenueResource::collection(CatalogListing::paginate($request, $query, 'name')));
    }
}
