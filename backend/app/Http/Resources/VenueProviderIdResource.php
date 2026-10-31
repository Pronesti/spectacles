<?php

namespace App\Http\Resources;

use App\Models\VenueProviderId;

/** @mixin VenueProviderId */
class VenueProviderIdResource extends PublicJsonApiResource
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
        'venue' => VenueResource::class,
    ];
}
