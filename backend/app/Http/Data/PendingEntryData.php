<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The pending Song and Artist keys one setlist entry refers to.
 */
class PendingEntryData extends Data
{
    public function __construct(
        public readonly Optional|string $song = new Optional,
        public readonly Optional|string $artist = new Optional,
    ) {}
}
