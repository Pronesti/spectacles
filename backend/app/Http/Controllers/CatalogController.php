<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\SongResource;
use App\Http\Resources\TourResource;
use App\Http\Resources\VenueResource;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Song;
use App\Models\Tour;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function index(CatalogReadRequest $request): JsonResponse
    {
        [$model, $resource, $display] = match ($request->catalog()) {
            'events' => [Event::class, EventResource::class, 'title'],
            'artists' => [Artist::class, ArtistResource::class, 'name'],
            'venues' => [Venue::class, VenueResource::class, 'name'],
            'songs' => [Song::class, SongResource::class, 'title'],
            'tours' => [Tour::class, TourResource::class, 'name'],
        };
        $query = $model::query();
        $filter = $request->filter();
        $term = $filter->q;
        if ($filter->hasText($term)) {
            if ($request->catalog() === 'events') {
                $query->where(function (Builder $group) use ($term): void {
                    $this->text($group, ['events.title'], $term);
                    $group->orWhereHas('artistLinks.artist', fn (Builder $artist): Builder => $this->text($artist, ['artists.name'], $term));
                });
            } elseif ($request->catalog() === 'tours') {
                $this->text($query, ['tours.search_name'], mb_strtolower(Str::ascii($term)));
            } else {
                $columns = $request->catalog() === 'artists' ? ['artists.name', 'artists.disambiguation'] : [$query->getModel()->getTable().'.'.$display];
                $this->text($query, $columns, $term);
            }
        }
        if ($request->catalog() === 'events') {
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
        }
        if ($request->catalog() === 'artists' && $filter->type !== null) {
            $query->where('type', $filter->type);
        }
        if ($request->catalog() === 'venues') {
            if ($filter->hasText($filter->city)) {
                $this->text($query, ['venues.city'], $filter->city);
            }
            if ($filter->countryCode !== null) {
                $query->where('country_code', $filter->countryCode);
            }
        }
        if ($request->catalog() === 'songs' && $filter->hasText($filter->disambiguation)) {
            $this->text($query, ['songs.disambiguation'], $filter->disambiguation);
        }
        if ($request->catalog() === 'tours' && $filter->scopesToArtistLists()) {
            $matchArtist = function (Builder $artist) use ($filter): void {
                $artist->where(function (Builder $scope) use ($filter): void {
                    $scope->whereIn('external_id', array_map('strtolower', $filter->artistUuids ?? []))
                        ->orWhereHas('providerIds', fn (Builder $provider): Builder => $provider->where('provider', 'musicbrainz')->where('resource_type', 'artist')->whereIn('provider_resource_id', array_map('strtolower', $filter->artistMbids ?? [])));
                });
            };
            $this->scopeToursToArtist($query, $matchArtist);
        } elseif ($request->catalog() === 'tours' && $filter->artistUuid !== null) {
            $this->scopeToursToArtist($query, fn (Builder $artist): Builder => $artist->where('external_id', $filter->artistUuid));
        }
        if (in_array($request->catalog(), ['events', 'artists'], true) && $filter->provider !== null) {
            $relation = $request->catalog() === 'events' ? 'providerReferences' : 'providerIds';
            $query->whereHas($relation, fn (Builder $reference): Builder => $reference->where('provider', $filter->provider)->where('resource_type', $filter->resourceType)->where('provider_resource_id', $filter->providerResourceId));
        }
        $page = $query->with($request->includePaths())
            ->orderBy($display)->orderBy('external_id')
            ->paginate($request->integer('per_page', 20), ['*'], 'page', $request->integer('page', 1))
            ->appends($request->query());

        return $resource::collection($page)->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    private function scopeToursToArtist(Builder $query, callable $matchArtist): void
    {
        $query->where(function (Builder $scope) use ($matchArtist): void {
            $scope->whereHas('artistLinks.artist', $matchArtist)
                ->orWhereHas('eventLinks.event', function (Builder $event) use ($matchArtist): void {
                    $event->has('artistLinks', '=', 1)->has('tourLinks', '=', 1)->whereHas('artistLinks.artist', $matchArtist);
                });
        });
    }

    public function show(CatalogReadRequest $request, Event $event): JsonResponse
    {
        $event->load($request->includePaths());

        return (new EventResource($event))->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    /** @param list<string> $columns */
    private function text(Builder $query, array $columns, string $term): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(function (Builder $group) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $group->{$method}("LOWER({$column}) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            }
        });
    }
}
