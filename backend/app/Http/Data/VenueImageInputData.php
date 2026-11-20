<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Unchecked attributed image metadata for a Venue.
 */
class VenueImageInputData extends Data
{
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $credit = null,
        public readonly ?string $license = null,
        public readonly ?string $licenseUrl = null,
    ) {}
}
