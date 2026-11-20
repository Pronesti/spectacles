<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Query parameters of one MusicBrainz web service read; absent parameters are not sent.
 */
class MusicBrainzQueryData extends Data
{
    public function __construct(
        public readonly Optional|string $query = new Optional,
        public readonly Optional|string $inc = new Optional,
        public readonly string $fmt = 'json',
        public readonly Optional|int $limit = new Optional,
    ) {}

    /**
     * A Lucene search returning at most ten results.
     */
    public static function search(string $query): self
    {
        return new self(query: $query, limit: 10);
    }

    /**
     * A lookup by MBID including the named subqueries.
     */
    public static function lookup(string $inc): self
    {
        return new self(inc: $inc);
    }
}
