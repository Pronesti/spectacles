<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

/**
 * A Commons `prop=imageinfo` query response.
 */
class CommonsImageInfoResponsePayloadData extends Data
{
    public function __construct(
        public readonly ?CommonsQueryPayloadData $query = null,
    ) {}
}
