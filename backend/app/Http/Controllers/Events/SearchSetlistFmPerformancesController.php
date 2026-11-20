<?php

declare(strict_types=1);

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Data\ProviderPageData;
use App\Http\Data\ProviderPerformanceSearchData;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use App\Http\SetlistFmDiscovery;
use Illuminate\Http\JsonResponse;

class SearchSetlistFmPerformancesController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, SetlistFmDiscovery $discovery): JsonResponse
    {
        return ProviderResponse::from(fn (): ProviderPageData => $discovery->performances(ProviderPerformanceSearchData::from($request->validated())), true);
    }
}
