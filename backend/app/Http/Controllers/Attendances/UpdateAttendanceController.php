<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendances;

use App\Http\AttendanceWriter;
use App\Http\CollectionResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveConcertRequest;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;

class UpdateAttendanceController extends Controller
{
    public function __invoke(SaveConcertRequest $request, Attendance $attendance, AttendanceWriter $writer): JsonResponse
    {
        $writer->save($request, $attendance);

        return CollectionResponse::for($request);
    }
}
