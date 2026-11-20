<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class ShowEventController extends Controller
{
    public function __invoke(CatalogReadRequest $request, Event $event): JsonResponse
    {
        $event->load($request->includePaths());

        return (new EventResource($event))->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
