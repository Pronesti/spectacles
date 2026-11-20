<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

/**
 * A claim value; its shape depends on the property, so it is kept as given.
 */
class WikidataDataValuePayloadData extends Data
{
    public function __construct(
        public readonly mixed $value = null,
    ) {}
}
