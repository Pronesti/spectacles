<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

class ProviderPageMetaData extends Data
{
    public function __construct(
        public ?string $next_cursor,
        public bool $partial,
    ) {}
}
