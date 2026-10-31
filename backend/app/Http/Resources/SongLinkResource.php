<?php

namespace App\Http\Resources;

use App\Models\SongLink;

/** @mixin SongLink */
class SongLinkResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'provider',
        'resource_type',
        'provider_resource_id',
        'url',
        'label',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'song' => SongResource::class,
    ];
}
