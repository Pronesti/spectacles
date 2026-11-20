<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class VenueSourceData extends Data
{
    public function __construct(
        public string $externalId,
        public string $url,
    ) {}
}
