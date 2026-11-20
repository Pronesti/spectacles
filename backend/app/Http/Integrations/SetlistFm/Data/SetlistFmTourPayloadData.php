<?php

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

class SetlistFmTourPayloadData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
    ) {}
}
