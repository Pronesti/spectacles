<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class PendingSelectionData extends Data
{
    public function __construct(
        public readonly string $selection,
    ) {}
}
