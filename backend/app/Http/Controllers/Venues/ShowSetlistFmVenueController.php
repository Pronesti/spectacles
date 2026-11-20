<?php

declare(strict_types=1);

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Data\ProviderVenueData;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use App\Http\SetlistFmDiscovery;
use Illuminate\Http\JsonResponse;

class ShowSetlistFmVenueController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, string $id, SetlistFmDiscovery $discovery): JsonResponse
    {
        $venueUrl = $request->validated('venueUrl');

        return ProviderResponse::from(fn (): ProviderVenueData => $discovery->venue($id, is_string($venueUrl) ? $venueUrl : null));
    }
}
