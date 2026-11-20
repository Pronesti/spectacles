<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Data\PerformancePreviewData;
use App\Http\ProviderResponse;
use App\Http\Requests\CatalogProviderRequest;
use App\Http\SetlistFmDiscovery;
use Illuminate\Http\JsonResponse;

class ShowSetlistFmPerformanceController extends Controller
{
    public function __invoke(CatalogProviderRequest $request, string $id, SetlistFmDiscovery $discovery): JsonResponse
    {
        return ProviderResponse::from(fn (): PerformancePreviewData => $discovery->preview($id, $request->safe()->string('locator')->value()));
    }
}
