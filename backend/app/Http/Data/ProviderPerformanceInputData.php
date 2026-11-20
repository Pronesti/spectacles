<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * An unchecked setlist.fm performance; an absent tour name stays absent.
 */
class ProviderPerformanceInputData extends Data
{
    public function __construct(
        public readonly ?ProviderRefInputData $ref = null,
        public readonly ?string $url = null,
        public readonly ?CalendarDateData $date = null,
        public readonly ?ProviderEntityInputData $artist = null,
        public readonly ?ProviderEntityInputData $tour = null,
        public readonly ?ProviderEntityInputData $venue = null,
        public readonly ?string $time = null,
        public readonly ?string $timezone = null,
        public readonly Optional|string|null $tourName = new Optional,
    ) {}
}
