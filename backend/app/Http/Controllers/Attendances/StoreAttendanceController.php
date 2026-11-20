<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendances;

use App\Http\AttendanceWriter;
use App\Http\CollectionResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveConcertRequest;
use Illuminate\Http\JsonResponse;

class StoreAttendanceController extends Controller
{
    public function __invoke(SaveConcertRequest $request, AttendanceWriter $writer): JsonResponse
    {
        $writer->save($request, null);

        return CollectionResponse::for($request);
    }
}
