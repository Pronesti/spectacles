<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class WikidataSnakPayloadData extends Data
{
    public function __construct(
        public readonly ?WikidataDataValuePayloadData $datavalue = null,
    ) {}
}
