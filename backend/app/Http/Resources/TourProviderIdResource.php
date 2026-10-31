<?php

namespace App\Http\Resources;

use App\Models\TourProviderId;

/** @mixin TourProviderId */
class TourProviderIdResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'provider',
        'resource_type',
        'external_provider_id',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'tour' => TourResource::class,
    ];
}
