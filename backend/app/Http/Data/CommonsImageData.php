<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The attributed image Commons describes for a file, if it can be shown.
 */
class CommonsImageData extends Data
{
    public function __construct(
        public readonly ?VenueImageData $image,
    ) {}
}
