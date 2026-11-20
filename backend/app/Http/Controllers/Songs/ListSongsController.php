<?php

namespace App\Http\Controllers\Songs;

use App\Http\CatalogListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\SongResource;
use App\Models\Song;
use Illuminate\Http\JsonResponse;

class ListSongsController extends Controller
{
    public function __invoke(CatalogReadRequest $request): JsonResponse
    {
        $query = Song::query();
        $filter = $request->filter();
        if ($filter->hasText($filter->q)) {
            CatalogListing::text($query, ['songs.title'], $filter->q);
        }
        if ($filter->hasText($filter->disambiguation)) {
            CatalogListing::text($query, ['songs.disambiguation'], $filter->disambiguation);
        }

        return CatalogListing::respond($request, SongResource::collection(CatalogListing::paginate($request, $query, 'title')));
    }
}
