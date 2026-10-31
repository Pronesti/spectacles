<?php

namespace App\Http\Resources;

use App\Models\SetlistEntry;

/** @mixin SetlistEntry */
class SetlistEntryResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'position',
        'type',
        'is_tape',
        'display_text',
        'notes',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'attendance' => AttendanceResource::class,
        'song' => SongResource::class,
        'artistLinks' => SetlistEntryArtistResource::class,
    ];
}
