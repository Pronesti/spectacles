<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Data\ProviderPageData;
use App\Http\MusicBrainzPlaces;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use Illuminate\Http\JsonResponse;

class SearchMusicBrainzPlacesController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, MusicBrainzPlaces $places): JsonResponse
    {
        return ProviderResponse::from(fn (): ProviderPageData => $places->search($request->safe()->string('q')->value()), true);
    }
}
