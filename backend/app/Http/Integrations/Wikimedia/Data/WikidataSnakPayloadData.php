<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class WikidataSnakPayloadData extends Data
{
    public function __construct(
        public readonly ?WikidataDataValuePayloadData $datavalue = null,
    ) {}
}
