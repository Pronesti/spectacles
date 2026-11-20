<?php

namespace App\Http\Controllers\Attendances;

use App\Http\CollectionResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DestroyAttendanceController extends Controller
{
    public function __invoke(Request $request, Attendance $attendance): JsonResponse
    {
        $viewer = $request->user() ?? throw new AuthenticationException;
        DB::transaction(function () use ($viewer, $attendance): void {
            User::query()->whereKey($viewer->id)->lockForUpdate()->firstOrFail();
            $locked = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);
            abort_unless($locked->owner_user_id === $viewer->id, 404);
            $locked->forceFill(['deleted_at' => now()])->save();
        });

        return CollectionResponse::for($request);
    }
}
