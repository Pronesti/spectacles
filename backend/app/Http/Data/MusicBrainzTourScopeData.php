<?php

namespace App\Http\Data;

use App\Http\MusicBrainzUnavailable;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * A Tour search limited to the series of known MusicBrainz Artists.
 */
class MusicBrainzTourScopeData extends Data
{
    /**
     * @param  list<string>  $artistMbids  lowercase, unique and sorted
     */
    public function __construct(
        public readonly string $query,
        public readonly array $artistMbids,
    ) {}

    /**
     * @param  iterable<string>  $artistMbids
     */
    public static function normalized(string $query, iterable $artistMbids): self
    {
        $ids = [];
        foreach ($artistMbids as $id) {
            if (! Str::isUuid($id)) {
                throw new MusicBrainzUnavailable;
            }
            $ids[strtolower($id)] = true;
        }
        $ids = array_keys($ids);
        sort($ids);

        return new self($query, $ids);
    }
}
