<?php

namespace App\Http\Controllers\Artists;

use App\Http\CatalogListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\ArtistResource;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ListArtistsController extends Controller
{
    public function __invoke(CatalogReadRequest $request): JsonResponse
    {
        $query = Artist::query();
        $filter = $request->filter();
        if ($filter->hasText($filter->q)) {
            CatalogListing::text($query, ['artists.name', 'artists.disambiguation'], $filter->q);
        }
        if ($filter->type !== null) {
            $query->where('type', $filter->type);
        }
        if ($filter->provider !== null) {
            $query->whereHas('providerIds', fn (Builder $reference): Builder => $reference->where('provider', $filter->provider)->where('resource_type', $filter->resourceType)->where('provider_resource_id', $filter->providerResourceId));
        }

        return CatalogListing::respond($request, ArtistResource::collection(CatalogListing::paginate($request, $query, 'name')));
    }
}
