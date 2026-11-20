<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class CommonsMetadataValuePayloadData extends Data
{
    public function __construct(
        public readonly ?string $value = null,
    ) {}
}
