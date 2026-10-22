<?php

namespace App\Http\Resources;

use App\Models\User;

/** @mixin User */
class UserResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'name',
        'locale',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'attendanceMemberships' => AttendanceMemberResource::class,
        'ownedAttendances' => AttendanceResource::class,
        'sentAttendanceInvitations' => AttendanceInvitationResource::class,
        'receivedAttendanceInvitations' => AttendanceInvitationResource::class,
    ];
}
