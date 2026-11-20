<?php

declare(strict_types=1);

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

class SetlistFmCountryPayloadData extends Data
{
    public function __construct(
        public readonly ?string $code = null,
    ) {}
}
