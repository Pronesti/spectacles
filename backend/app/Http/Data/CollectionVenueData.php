<?php

namespace App\Http\Data;

use App\Models\Venue;
use Spatie\LaravelData\Data;

class CollectionVenueData extends Data
{
    /**
     * @param  list<string>  $aliases
     * @param  array<string, string>|null  $image
     */
    public function __construct(
        public string $id,
        public string $catalogId,
        public ?string $address,
        public array $aliases,
        public ?array $image,
        public ?string $region,
        public ?string $countryCode,
        public string $name,
        public string $city,
        public string $neighborhood,
        public ?float $lat,
        public ?float $lng,
        public ?string $timezone,
    ) {}

    public static function fromVenue(Venue $venue): self
    {
        return new self(
            id: $venue->external_id,
            catalogId: $venue->external_id,
            address: $venue->address,
            aliases: $venue->aliases ?? [],
            image: $venue->image,
            region: $venue->region,
            countryCode: $venue->country_code,
            name: $venue->name,
            city: $venue->city ?? '',
            neighborhood: '',
            lat: $venue->lat === null ? null : (float) $venue->lat,
            lng: $venue->lng === null ? null : (float) $venue->lng,
            timezone: $venue->timezone,
        );
    }
}
