<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A validated search for the tour names setlist.fm reports for one Artist.
 */
class ProviderTourSearchData extends Data
{
    public function __construct(
        public readonly Optional|string|null $q = new Optional,
        public readonly Optional|string $artistUuid = new Optional,
        public readonly Optional|string $artistMbid = new Optional,
        public readonly Optional|string $cursor = new Optional,
    ) {}
}
