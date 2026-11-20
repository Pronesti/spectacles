<?php

declare(strict_types=1);

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
