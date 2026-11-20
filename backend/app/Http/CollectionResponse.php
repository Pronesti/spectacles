<?php

namespace App\Http;

use App\Http\Data\CollectionData;
use App\Models\Attendance;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionResponse
{
    /**
     * Renders the viewer's current collection.
     */
    public static function for(Request $request): JsonResponse
    {
        $viewer = $request->user() ?? throw new AuthenticationException;
        $attendances = Attendance::query()->whereNull('deleted_at')->whereHas('members', fn ($query) => $query->where('user_id', $viewer->id))
            ->with(['event.venue', 'event.artistLinks.artist', 'event.tourLinks.tour', 'members' => fn ($query) => $query->where('user_id', $viewer->id), 'entries.song', 'entries.artistLinks.artist'])
            ->orderBy('id')->get();

        return response()->json(['data' => CollectionData::fromAttendances($attendances, $viewer)])->header('Cache-Control', 'private, no-store');
    }
}
