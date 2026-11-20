<?php

namespace App\Http\Data;

use App\Models\Venue;
use App\Models\VenueProviderId;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A saved Venue described with its provider identities, for provider lookups.
 */
class CatalogVenueData extends Data
{
    /**
     * @param  list<ProviderRefData>  $refs
     * @param  list<VenueSourceData>  $venueSources
     */
    public function __construct(
        public string $localId,
        public VenueValuesData $values,
        #[DataCollectionOf(ProviderRefData::class)]
        public array $refs,
        public ?string $venueUrl,
        #[DataCollectionOf(VenueSourceData::class)]
        public array $venueSources,
    ) {}

    public static function fromVenue(Venue $venue, string $localId): self
    {
        $isVenueSource = fn (VenueProviderId $ref): bool => $ref->provider === 'setlist_fm' && $ref->resource_type === 'venue' && $ref->source_url !== null;

        return new self(
            localId: $localId,
            values: VenueValuesData::fromVenue($venue),
            refs: $venue->providerIds
                ->filter(fn (VenueProviderId $ref): bool => in_array($ref->provider, ['musicbrainz', 'setlist_fm'], true) && in_array($ref->resource_type, ['place', 'venue'], true))
                ->map(fn (VenueProviderId $ref): ProviderRefData => new ProviderRefData($ref->provider, $ref->resource_type, $ref->provider_resource_id))
                ->values()->all(),
            venueUrl: $venue->providerIds->first($isVenueSource)?->source_url,
            venueSources: $venue->providerIds->filter($isVenueSource)
                ->map(fn (VenueProviderId $ref): VenueSourceData => new VenueSourceData($ref->provider_resource_id, $ref->source_url))
                ->values()->all(),
        );
    }
}
