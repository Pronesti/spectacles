<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A validated setlist.fm performance search. Absent filters stay Optional, so
 * the filters a cursor is bound to are exactly the ones the viewer sent.
 */
class ProviderPerformanceSearchData extends Data
{
    public function __construct(
        public readonly string $period,
        public readonly Optional|string|null $q = new Optional,
        public readonly Optional|string $artistUuid = new Optional,
        public readonly Optional|string $artistMbid = new Optional,
        public readonly Optional|string $cursor = new Optional,
        public readonly Optional|string $venueUrl = new Optional,
        public readonly Optional|string $venueId = new Optional,
        public readonly Optional|string $tourId = new Optional,
        public readonly Optional|string $tourName = new Optional,
        public readonly Optional|string $date = new Optional,
        public readonly Optional|int $year = new Optional,
        public readonly Optional|string $countryCode = new Optional,
    ) {}

    /**
     * The fingerprint of the filters a pagination cursor is bound to. The year is
     * encoded as the query string sent it, so cursors issued before it was typed still match.
     */
    public function cursorScope(): string
    {
        $filters = $this->except('cursor')->toArray();
        if (is_int($this->year)) {
            $filters['year'] = (string) $this->year;
        }
        ksort($filters);

        return hash('sha256', json_encode($filters) ?: '');
    }
}
