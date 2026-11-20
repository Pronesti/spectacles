<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tours;

use App\Http\Controllers\Controller;
use App\Http\MusicBrainzSearch;
use App\Http\Requests\MusicBrainzSearchRequest;
use Illuminate\Http\JsonResponse;

class SearchMusicBrainzToursController extends Controller
{
    public function __invoke(MusicBrainzSearchRequest $request, MusicBrainzSearch $search): JsonResponse
    {
        return $search->respond($request, 'series');
    }
}
