<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class VenueSourceData extends Data
{
    public function __construct(
        public string $externalId,
        public string $url,
    ) {}
}
