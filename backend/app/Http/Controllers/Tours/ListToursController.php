<?php

namespace App\Http\Controllers\Tours;

use App\Http\CatalogListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ListToursController extends Controller
{
    public function __invoke(CatalogReadRequest $request): JsonResponse
    {
        $query = Tour::query();
        $filter = $request->filter();
        if ($filter->hasText($filter->q)) {
            CatalogListing::text($query, ['tours.search_name'], mb_strtolower(Str::ascii($filter->q)));
        }
        if ($filter->scopesToArtistLists()) {
            $matchArtist = function (Builder $artist) use ($filter): void {
                $artist->where(function (Builder $scope) use ($filter): void {
                    $scope->whereIn('external_id', array_map(strtolower(...), $filter->artistUuids ?? []))
                        ->orWhereHas('providerIds', fn (Builder $provider): Builder => $provider->where('provider', 'musicbrainz')->where('resource_type', 'artist')->whereIn('provider_resource_id', array_map(strtolower(...), $filter->artistMbids ?? [])));
                });
            };
            $this->scopeToArtist($query, $matchArtist);
        } elseif ($filter->artistUuid !== null) {
            $this->scopeToArtist($query, fn (Builder $artist): Builder => $artist->where('external_id', $filter->artistUuid));
        }

        return CatalogListing::respond($request, TourResource::collection(CatalogListing::paginate($request, $query, 'name')));
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  \Closure(Builder<Model>): mixed  $matchArtist
     */
    private function scopeToArtist(Builder $query, \Closure $matchArtist): void
    {
        $query->where(function (Builder $scope) use ($matchArtist): void {
            $scope->whereHas('artistLinks.artist', $matchArtist)
                ->orWhereHas('eventLinks.event', function (Builder $event) use ($matchArtist): void {
                    $event->has('artistLinks', '=', 1)->has('tourLinks', '=', 1)->whereHas('artistLinks.artist', $matchArtist);
                });
        });
    }
}
