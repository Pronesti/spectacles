<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * A Wikimedia Commons image with the attribution it must be shown with.
 */
class VenueImageData extends Data
{
    public function __construct(
        public string $url,
        public string $thumbnailUrl,
        public string $sourceUrl,
        public string $credit,
        public string $license,
        public string $licenseUrl,
    ) {}
}
