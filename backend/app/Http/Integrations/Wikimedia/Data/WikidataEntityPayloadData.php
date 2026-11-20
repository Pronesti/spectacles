<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class WikidataEntityPayloadData extends Data
{
    public function __construct(
        public readonly ?WikidataClaimsPayloadData $claims = null,
    ) {}
}
