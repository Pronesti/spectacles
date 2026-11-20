<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The documented setlist.fm `search/setlists` parameters this app sends.
 */
class SetlistFmSearchQueryData extends Data
{
    public function __construct(
        public readonly int $p,
        public readonly Optional|string $artistMbid = new Optional,
        public readonly Optional|string $venueId = new Optional,
        public readonly Optional|string $tourName = new Optional,
        public readonly Optional|int $year = new Optional,
        public readonly Optional|string $countryCode = new Optional,
        public readonly Optional|string $date = new Optional,
    ) {}

    /**
     * The query string parameters, in a stable order for caching.
     *
     * @return array<string, int|string>
     */
    public function parameters(): array
    {
        $parameters = $this->toArray();
        ksort($parameters);

        return $parameters;
    }
}
