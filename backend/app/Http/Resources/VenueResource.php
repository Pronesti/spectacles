<?php

namespace App\Http\Resources;

use App\Models\Venue;

/** @mixin Venue */
class VenueResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'region',
        'country_code',
        'lat',
        'lng',
        'timezone',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'events' => EventResource::class,
        'providerIds' => VenueProviderIdResource::class,
    ];
}
