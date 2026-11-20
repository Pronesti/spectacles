<?php

namespace App\Http;

use App\Http\Requests\CatalogReadRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogListing
{
    /**
     * Matches the term as a case-insensitive substring of any of the columns.
     *
     * @param  Builder<covariant Model>  $query
     * @param  list<literal-string>  $columns
     * @return Builder<covariant Model>
     */
    public static function text(Builder $query, array $columns, string $term): Builder
    {
        $folded = str_replace(['’', '‘'], "'", $term);
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $folded).'%';

        return $query->where(function (Builder $group) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $group->{$method}("LOWER(REPLACE(REPLACE({$column}, '’', ''''), '‘', '''')) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            }
        });
    }

    /**
     * Loads the requested includes and pages the records by their display column.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(CatalogReadRequest $request, Builder $query, string $display): LengthAwarePaginator
    {
        return $query->with($request->includePaths())
            ->orderBy($display)->orderBy('external_id')
            ->paginate($request->integer('per_page', 20), ['*'], 'page', $request->integer('page', 1))
            ->appends($request->query());
    }

    public static function respond(CatalogReadRequest $request, ResourceCollection $collection): JsonResponse
    {
        return $collection->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
