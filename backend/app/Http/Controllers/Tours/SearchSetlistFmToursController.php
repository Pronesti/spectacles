<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tours;

use App\Http\Controllers\Controller;
use App\Http\Data\ProviderPageData;
use App\Http\Data\ProviderTourSearchData;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use App\Http\SetlistFmDiscovery;
use Illuminate\Http\JsonResponse;

class SearchSetlistFmToursController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, SetlistFmDiscovery $discovery): JsonResponse
    {
        return ProviderResponse::from(fn (): ProviderPageData => $discovery->tours(ProviderTourSearchData::from($request->validated())), true);
    }
}
