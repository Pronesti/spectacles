<?php

namespace App\Http\Resources;

use App\Models\Attendance;

/** @mixin Attendance */
class AttendanceResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'notes',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'event' => EventResource::class,
        'owner' => UserResource::class,
        'members' => AttendanceMemberResource::class,
        'invitations' => AttendanceInvitationResource::class,
        'links' => AttendanceLinkResource::class,
        'entries' => SetlistEntryResource::class,
    ];
}
