<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

/**
 * A validated MusicBrainz search request. Only Tour searches carry Artist scope.
 */
class MusicBrainzSearchData extends Data
{
    /**
     * @param  list<string>  $artistUuids
     * @param  list<string>  $artistMbids
     */
    public function __construct(
        public readonly string $q,
        #[MapInputName('artist_uuids')]
        public readonly array $artistUuids = [],
        #[MapInputName('artist_mbids')]
        public readonly array $artistMbids = [],
    ) {}
}
