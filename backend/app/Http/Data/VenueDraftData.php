<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A Venue submitted with a concert: either a saved Venue's catalog id or new Venue details.
 */
class VenueDraftData extends Data
{
    /**
     * @param  Optional|list<string>  $aliases
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $city,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly Optional|string|null $neighborhood = new Optional,
        public readonly Optional|string $catalogId = new Optional,
        public readonly Optional|string|null $region = new Optional,
        public readonly Optional|string|null $countryCode = new Optional,
        public readonly Optional|string|null $address = new Optional,
        public readonly Optional|array $aliases = new Optional,
        public readonly Optional|VenueImageData|null $image = new Optional,
        public readonly Optional|string|null $timezone = new Optional,
    ) {}

    public function isSaved(): bool
    {
        return is_string($this->catalogId);
    }

    /**
     * The attributes a new Venue is created with.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $given = fn (mixed $value, mixed $default = null): mixed => $value instanceof Optional ? $default : $value;

        return ['name' => $this->name, 'city' => $this->city, 'region' => $given($this->region), 'country_code' => $given($this->countryCode), 'address' => $given($this->address), 'aliases' => $given($this->aliases, []), 'image' => $given($this->image)?->toArray(), 'lat' => $this->lat, 'lng' => $this->lng, 'timezone' => $given($this->timezone)];
    }
}
