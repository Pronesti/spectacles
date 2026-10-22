<?php

namespace App\Http\Resources;

use App\Models\AttendanceMember;

/** @mixin AttendanceMember */
class AttendanceMemberResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'interested',
        'tickets_bought',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'attendance' => AttendanceResource::class,
        'event' => EventResource::class,
        'user' => UserResource::class,
        'seats' => AttendanceMemberSeatResource::class,
    ];
}
