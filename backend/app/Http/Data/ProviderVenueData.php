<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A Venue offered by a provider, with the other identities and setlist.fm
 * pages known for the same place. The local match and selection proof are only
 * present once the Venue is offered to the viewer.
 */
class ProviderVenueData extends Data
{
    use CopiesWithChanges;

    /**
     * @param  list<ProviderRefData>  $linkedRefs
     * @param  list<VenueSourceData>  $venueSources
     */
    public function __construct(
        public readonly ProviderRefData $ref,
        public readonly VenueValuesData $values,
        public readonly string $url,
        #[DataCollectionOf(ProviderRefData::class)]
        public readonly array $linkedRefs,
        public readonly Optional|string $venueUrl,
        #[DataCollectionOf(VenueSourceData::class)]
        public readonly array $venueSources,
        public readonly Optional|string|null $localId = new Optional,
        public readonly Optional|string $selection = new Optional,
    ) {}

    /**
     * The primary identity followed by the linked ones.
     *
     * @return list<ProviderRefData>
     */
    public function identities(): array
    {
        return [$this->ref, ...$this->linkedRefs];
    }

    /**
     * setlist.fm venue pages keyed by venue ID.
     *
     * @return array<string, string>
     */
    public function sourceUrls(): array
    {
        $urls = [];
        foreach ($this->venueSources as $source) {
            $urls[$source->externalId] = $source->url;
        }

        return $urls;
    }
}
