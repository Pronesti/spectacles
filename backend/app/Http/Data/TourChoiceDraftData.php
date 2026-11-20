<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class TourChoiceDraftData extends Data
{
    use CopiesWithChanges;

    public function __construct(
        public readonly string $name,
        public readonly ?string $catalogId,
    ) {}
}
