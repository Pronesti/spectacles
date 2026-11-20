<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class CollectionTourData extends Data
{
    public function __construct(
        public string $name,
        public string $catalogId,
    ) {}
}
