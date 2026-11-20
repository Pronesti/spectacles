<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

/**
 * The validated `filter` of a catalog read; each catalog reads only the filters its rules allow.
 */
class CatalogFilterData extends Data
{
    /**
     * @param  list<string>|null  $artistUuids  null when the filter was not sent
     * @param  list<string>|null  $artistMbids  null when the filter was not sent
     */
    public function __construct(
        public readonly ?string $q = null,
        #[MapInputName('venue_uuid')]
        public readonly ?string $venueUuid = null,
        #[MapInputName('artist_uuid')]
        public readonly ?string $artistUuid = null,
        #[MapInputName('artist_mbid')]
        public readonly ?string $artistMbid = null,
        public readonly ?int $year = null,
        public readonly ?int $month = null,
        public readonly ?int $day = null,
        public readonly ?string $type = null,
        public readonly ?string $city = null,
        #[MapInputName('country_code')]
        public readonly ?string $countryCode = null,
        public readonly ?string $disambiguation = null,
        #[MapInputName('artist_uuids')]
        public readonly ?array $artistUuids = null,
        #[MapInputName('artist_mbids')]
        public readonly ?array $artistMbids = null,
        public readonly ?string $provider = null,
        #[MapInputName('resource_type')]
        public readonly ?string $resourceType = null,
        #[MapInputName('provider_resource_id')]
        public readonly ?string $providerResourceId = null,
    ) {}

    /**
     * @phpstan-assert-if-true non-empty-string $value
     */
    public function hasText(?string $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * Whether the Tour filter names Artists by a list of identities.
     */
    public function scopesToArtistLists(): bool
    {
        return $this->artistUuids !== null || $this->artistMbids !== null;
    }
}
