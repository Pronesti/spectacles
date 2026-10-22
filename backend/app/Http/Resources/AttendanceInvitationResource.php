<?php

namespace App\Http\Resources;

use App\Models\AttendanceInvitation;

/** @mixin AttendanceInvitation */
class AttendanceInvitationResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'attendance' => AttendanceResource::class,
        'inviter' => UserResource::class,
        'recipient' => UserResource::class,
    ];
}
