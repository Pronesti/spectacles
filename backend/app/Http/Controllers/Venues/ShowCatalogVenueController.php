<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Data\CatalogVenueData;
use App\Http\Requests\CatalogProviderRequest;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

class ShowCatalogVenueController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, string $uuid): JsonResponse
    {
        $venue = Venue::where('external_id', $uuid)->firstOrFail();

        return response()->json(['data' => CatalogVenueData::fromVenue($venue, $uuid)])->header('Cache-Control', 'private, no-store');
    }
}
