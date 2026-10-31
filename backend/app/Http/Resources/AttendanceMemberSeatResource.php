<?php

namespace App\Http\Resources;

use App\Models\AttendanceMemberSeat;

/** @mixin AttendanceMemberSeat */
class AttendanceMemberSeatResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'section_label',
        'row_label',
        'seat_label',
        'label',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'member' => AttendanceMemberResource::class,
    ];
}
