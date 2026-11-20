<?php

namespace App\Http\Controllers\Events;

use App\Http\CatalogListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ListEventsController extends Controller
{
    public function __invoke(CatalogReadRequest $request): JsonResponse
    {
        $query = Event::query();
        $filter = $request->filter();
        $term = $filter->q;
        if ($filter->hasText($term)) {
            $query->where(function (Builder $group) use ($term): void {
                CatalogListing::text($group, ['events.title'], $term);
                $group->orWhereHas('artistLinks.artist', fn (Builder $artist): Builder => CatalogListing::text($artist, ['artists.name'], $term));
            });
        }
        foreach (['year' => $filter->year, 'month' => $filter->month, 'day' => $filter->day] as $component => $value) {
            if ($value !== null) {
                $query->where($component, $value);
            }
        }
        if ($filter->venueUuid !== null) {
            $query->whereHas('venue', fn (Builder $venue): Builder => $venue->where('external_id', $filter->venueUuid));
        }
        if ($filter->artistUuid !== null) {
            $query->whereHas('artistLinks.artist', fn (Builder $artist): Builder => $artist->where('external_id', $filter->artistUuid));
        }
        if ($filter->artistMbid !== null) {
            $query->whereHas('artistLinks.artist.providerIds', fn (Builder $provider): Builder => $provider->where('provider', 'musicbrainz')->where('resource_type', 'artist')->where('provider_resource_id', strtolower($filter->artistMbid)));
        }
        if ($filter->provider !== null) {
            $query->whereHas('providerReferences', fn (Builder $reference): Builder => $reference->where('provider', $filter->provider)->where('resource_type', $filter->resourceType)->where('provider_resource_id', $filter->providerResourceId));
        }

        return CatalogListing::respond($request, EventResource::collection(CatalogListing::paginate($request, $query, 'title')));
    }
}
