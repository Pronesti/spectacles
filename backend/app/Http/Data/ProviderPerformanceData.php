<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * One setlist.fm performance with its Artist, Venue and optional Tour.
 */
class ProviderPerformanceData extends Data
{
    use CopiesWithChanges;

    public function __construct(
        public readonly ProviderRefData $ref,
        public readonly string $url,
        public readonly CalendarDateData $date,
        public readonly ProviderArtistData $artist,
        public readonly ?ProviderTourData $tour,
        public readonly ProviderVenueData $venue,
        public readonly ?string $time,
        public readonly ?string $timezone,
        public readonly Optional|string|null $tourName = new Optional,
    ) {}

    /**
     * The tour name setlist.fm reported, if any.
     */
    public function namedTour(): ?string
    {
        return $this->tourName instanceof Optional ? null : $this->tourName;
    }
}
