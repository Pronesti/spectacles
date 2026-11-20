<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class CommonsMetadataValuePayloadData extends Data
{
    public function __construct(
        public readonly ?string $value = null,
    ) {}
}
