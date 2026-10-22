<?php

namespace App\Http\Resources;

use App\Models\AttendanceLink;

/** @mixin AttendanceLink */
class AttendanceLinkResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'url',
        'label',
        'type',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'attendance' => AttendanceResource::class,
    ];
}
