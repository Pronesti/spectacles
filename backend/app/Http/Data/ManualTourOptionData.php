<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * A tour name seen in setlist.fm results, offered for manual Tour creation
 * because the API exposes no verifiable Tour identity.
 */
class ManualTourOptionData extends Data
{
    public function __construct(
        public readonly bool $manual,
        public readonly ?ProviderRefData $ref,
        public readonly TourValuesData $values,
        public readonly string $url,
        public readonly string $artistMbid,
        public readonly ?string $selection,
        public readonly ?string $localId,
    ) {}
}
