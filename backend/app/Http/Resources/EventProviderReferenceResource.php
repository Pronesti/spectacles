<?php

namespace App\Http\Resources;

use App\Models\EventProviderReference;

/** @mixin EventProviderReference */
class EventProviderReferenceResource extends PublicJsonApiResource
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
        'event' => EventResource::class,
    ];
}
