<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Performance details a locator carries so a saved Event can be previewed
 * without another provider read.
 */
class ManualPerformanceData extends Data
{
    public function __construct(
        public readonly ProviderArtistData $artist,
        public readonly ProviderVenueData $venue,
        public readonly CalendarDateData $date,
        public readonly ?string $tourName,
        public readonly ?string $time,
        public readonly ?string $timezone,
    ) {}
}
