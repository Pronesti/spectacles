<?php

namespace App\Http\Resources;

use App\Models\Venue;
use Illuminate\Http\Request;

/** @mixin Venue */
class VenueResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'name',
        'address',
        'aliases',
        'image',
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

    public function toAttributes(Request $request): array
    {
        return [...parent::toAttributes($request), 'aliases' => $this->resource->aliases ?? []];
    }

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'events' => EventResource::class,
        'providerIds' => VenueProviderIdResource::class,
    ];
}
