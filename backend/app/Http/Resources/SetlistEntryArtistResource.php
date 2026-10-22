<?php

namespace App\Http\Resources;

use App\Models\SetlistEntryArtist;

/** @mixin SetlistEntryArtist */
class SetlistEntryArtistResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'entry' => SetlistEntryResource::class,
        'artist' => ArtistResource::class,
    ];
}
