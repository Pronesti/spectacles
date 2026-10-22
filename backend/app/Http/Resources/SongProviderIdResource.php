<?php

namespace App\Http\Resources;

use App\Models\SongProviderId;

/** @mixin SongProviderId */
class SongProviderIdResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'provider',
        'resource_type',
        'provider_resource_id',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'song' => SongResource::class,
    ];
}
