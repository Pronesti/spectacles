<?php

namespace App\Http\Resources;

use App\Models\Song;

/** @mixin Song */
class SongResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'title',
        'disambiguation',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'entries' => SetlistEntryResource::class,
        'providerIds' => SongProviderIdResource::class,
        'links' => SongLinkResource::class,
    ];
}
