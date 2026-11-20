<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * An unchecked setlist.fm Venue page claimed for a provider Venue.
 */
class VenueSourceInputData extends Data
{
    public function __construct(
        public readonly ?string $externalId = null,
        public readonly ?string $url = null,
    ) {}
}
