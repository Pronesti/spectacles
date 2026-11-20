<?php

declare(strict_types=1);

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

    /** @var Venue */
    public $resource;

    /**
     * The attribute names to serialize, with `aliases` defaulting to an empty list.
     *
     * @return array<int|string, string|list<string>>
     */
    public function toAttributes(Request $request): array
    {
        return [...$this->attributes, 'aliases' => $this->resource->aliases ?? []];
    }

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'events' => EventResource::class,
        'providerIds' => VenueProviderIdResource::class,
    ];
}
