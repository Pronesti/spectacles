<?php

namespace App\Http\Data;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderUnavailable;
use App\Http\Integrations\UpstreamPayload;
use App\Models\Venue;
use Spatie\LaravelData\Data;

/**
 * The descriptive values of a Venue, as offered by a provider or the catalog.
 */
class VenueValuesData extends Data
{
    use CopiesWithChanges;

    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $name,
        public string $city,
        public ?string $region,
        public ?string $countryCode,
        public ?string $address,
        public array $aliases,
        public ?float $lat,
        public ?float $lng,
        public ?string $timezone,
        public ?VenueImageData $image,
    ) {}

    /**
     * Describes a saved Venue, held to the same rules as provider values.
     */
    public static function fromVenue(Venue $venue): self
    {
        return CatalogProviderData::venueValues(new ProviderEntityValuesInputData(
            name: $venue->name,
            city: $venue->city ?? '',
            region: $venue->region,
            countryCode: $venue->country_code,
            address: $venue->address,
            aliases: $venue->aliases ?? [],
            lat: $venue->lat === null ? null : (float) $venue->lat,
            lng: $venue->lng === null ? null : (float) $venue->lng,
            timezone: $venue->timezone,
            image: $venue->image === null ? null : UpstreamPayload::parse(VenueImageInputData::class, $venue->image, fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable),
        ));
    }
}
