<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class CommonsImageInfoPayloadData extends Data
{
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $thumburl = null,
        public readonly ?string $descriptionurl = null,
        public readonly ?CommonsMetadataPayloadData $extmetadata = null,
    ) {}
}
