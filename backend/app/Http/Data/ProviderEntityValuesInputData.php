<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Unchecked values of a provider Artist, Tour or Venue; each kind reads only its own fields.
 */
class ProviderEntityValuesInputData extends Data
{
    /**
     * @param  array<array-key, mixed>|null  $aliases
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $disambiguation = null,
        public readonly ?string $city = null,
        public readonly ?string $region = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $address = null,
        public readonly ?array $aliases = null,
        public readonly int|float|string|null $lat = null,
        public readonly int|float|string|null $lng = null,
        public readonly ?string $timezone = null,
        public readonly ?VenueImageInputData $image = null,
    ) {}
}
