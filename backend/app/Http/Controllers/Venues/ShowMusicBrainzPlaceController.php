<?php

declare(strict_types=1);

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Data\ProviderVenueData;
use App\Http\MusicBrainzPlaces;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use Illuminate\Http\JsonResponse;

class ShowMusicBrainzPlaceController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, string $mbid, MusicBrainzPlaces $places): JsonResponse
    {
        return ProviderResponse::from(fn (): ProviderVenueData => $places->detail($mbid));
    }
}
