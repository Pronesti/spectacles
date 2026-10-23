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
        $filter = $request->validated('filter', []);
        $term = $filter['q'] ?? null;
        if (is_string($term) && $term !== '') {
            if ($request->catalog() === 'events') {
                $query->where(function (Builder $group) use ($term): void {
                    $this->text($group, ['events.title'], $term);
                    $group->orWhereHas('artistLinks.artist', fn (Builder $artist): Builder => $this->text($artist, ['artists.name'], $term));
                });
            } else {
                $columns = $request->catalog() === 'artists' ? ['artists.name', 'artists.disambiguation'] : [$query->getModel()->getTable().'.'.$display];
                $this->text($query, $columns, $term);
            }
        }
        if ($request->catalog() === 'events') {
            foreach (['year', 'month', 'day'] as $component) {
                if (isset($filter[$component])) {
                    $query->where($component, (int) $filter[$component]);
                }
            }
            if (isset($filter['venue_uuid'])) {
                $query->whereHas('venue', fn (Builder $venue): Builder => $venue->where('external_id', $filter['venue_uuid']));
            }
        }
        if ($request->catalog() === 'artists' && isset($filter['type'])) {
            $query->where('type', $filter['type']);
        }
        if ($request->catalog() === 'venues') {
            if (is_string($filter['city'] ?? null) && $filter['city'] !== '') {
                $this->text($query, ['venues.city'], $filter['city']);
            }
            if (isset($filter['country_code'])) {
                $query->where('country_code', $filter['country_code']);
            }
        }
        if ($request->catalog() === 'songs' && is_string($filter['disambiguation'] ?? null) && $filter['disambiguation'] !== '') {
            $this->text($query, ['songs.disambiguation'], $filter['disambiguation']);
        }
        if ($request->catalog() === 'tours' && isset($filter['artist_uuid'])) {
            $query->whereHas('artistLinks.artist', fn (Builder $artist): Builder => $artist->where('external_id', $filter['artist_uuid']));
        }
        if (in_array($request->catalog(), ['events', 'artists'], true) && isset($filter['provider'])) {
            $relation = $request->catalog() === 'events' ? 'providerReferences' : 'providerIds';
            $query->whereHas($relation, function (Builder $reference) use ($filter): void {
                foreach (['provider', 'resource_type', 'provider_resource_id'] as $component) {
                    $reference->where($component, $filter[$component]);
                }
            });
        }
        $page = $query->with($request->includePaths())
            ->orderBy($display)->orderBy('external_id')
            ->paginate($request->integer('per_page', 20), ['*'], 'page', $request->integer('page', 1))
            ->appends($request->query());

        return $resource::collection($page)->toResponse($request)->header('Cache-Control', 'private, no-store');
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
