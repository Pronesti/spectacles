<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * An unchecked provider Artist, Tour or Venue; `CatalogProviderData::entity()` decides which it is.
 */
class ProviderEntityInputData extends Data
{
    /**
     * @param  array<array-key, ProviderRefInputData>|null  $linkedRefs
     * @param  array<array-key, VenueSourceInputData>|null  $venueSources
     */
    public function __construct(
        public readonly ?ProviderRefInputData $ref = null,
        public readonly ?string $url = null,
        public readonly ?ProviderEntityValuesInputData $values = null,
        #[DataCollectionOf(ProviderRefInputData::class)]
        public readonly ?array $linkedRefs = null,
        public readonly ?string $venueUrl = null,
        #[DataCollectionOf(VenueSourceInputData::class)]
        public readonly ?array $venueSources = null,
    ) {}
}
