<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class TourValuesData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
