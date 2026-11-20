<?php

namespace App\Http\Data;

use App\Models\Venue;
use Spatie\LaravelData\Data;

/**
 * A saved Venue as embedded in a local Event; keys keep the model's snake_case.
 */
class LocalVenueData extends Data
{
    /**
     * @param  list<string>  $aliases
     * @param  array<string, string>|null  $image
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $street,
        public ?string $house_number,
        public ?string $postal_code,
        public ?string $city,
        public ?string $region,
        public ?string $country_code,
        public ?float $lat,
        public ?float $lng,
        public ?string $timezone,
        public ?string $address,
        public array $aliases,
        public ?array $image,
    ) {}

    public static function fromVenue(Venue $venue): self
    {
        return new self(
            id: $venue->external_id,
            name: $venue->name,
            street: $venue->street,
            house_number: $venue->house_number,
            postal_code: $venue->postal_code,
            city: $venue->city,
            region: $venue->region,
            country_code: $venue->country_code,
            lat: $venue->lat === null ? null : (float) $venue->lat,
            lng: $venue->lng === null ? null : (float) $venue->lng,
            timezone: $venue->timezone,
            address: $venue->address,
            aliases: $venue->aliases ?? [],
            image: $venue->image,
        );
    }
}
