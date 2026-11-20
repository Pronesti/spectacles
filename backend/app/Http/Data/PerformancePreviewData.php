<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * A performance search row expanded with everything needed to save it.
 */
class PerformancePreviewData extends Data
{
    public function __construct(
        public readonly ProviderRefData $ref,
        public readonly string $url,
        public readonly CalendarDateData $date,
        public readonly string $artistName,
        public readonly string $venueName,
        public readonly string $city,
        public readonly ?string $localEventId,
        public readonly string $locator,
        public readonly ProviderArtistData $artist,
        public readonly ?ProviderTourData $tour,
        public readonly ?string $tourName,
        public readonly ProviderVenueData $venue,
        public readonly ?string $time,
        public readonly ?string $timezone,
        public readonly string $selection,
        public readonly ?LocalEventData $localEvent,
    ) {}

    public static function expand(PerformanceSummaryData $summary, ProviderArtistData $artist, ?ProviderTourData $tour, ?string $tourName, ProviderVenueData $venue, ?string $time, ?string $timezone, string $selection, ?LocalEventData $localEvent): self
    {
        return new self($summary->ref, $summary->url, $summary->date, $summary->artistName, $summary->venueName, $summary->city, $summary->localEventId, $summary->locator, $artist, $tour, $tourName, $venue, $time, $timezone, $selection, $localEvent);
    }
}
