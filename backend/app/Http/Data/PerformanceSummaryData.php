<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * One row of a setlist.fm performance search.
 */
class PerformanceSummaryData extends Data
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
    ) {}
}
