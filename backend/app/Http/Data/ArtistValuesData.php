<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class ArtistValuesData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly ?string $disambiguation,
    ) {}
}
