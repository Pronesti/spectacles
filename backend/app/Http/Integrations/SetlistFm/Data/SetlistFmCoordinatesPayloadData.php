<?php

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

/**
 * setlist.fm city coordinates; read only to reject malformed cities.
 */
class SetlistFmCoordinatesPayloadData extends Data
{
    public function __construct(
        public readonly int|float|null $lat = null,
        public readonly int|float|null $long = null,
    ) {}
}
