<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Where a paginated setlist.fm scan continues: the API page, the row within it,
 * and how many rows the whole search has already examined.
 */
class ProviderCursorData extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $offset,
        public readonly int $scanned,
    ) {}

    public static function start(): self
    {
        return new self(1, 0, 0);
    }
}
