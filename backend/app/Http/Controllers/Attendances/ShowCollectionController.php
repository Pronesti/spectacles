<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendances;

use App\Http\CollectionResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowCollectionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return CollectionResponse::for($request);
    }
}
