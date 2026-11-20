<?php

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Data;

/**
 * Coordinates are passed on unchanged; the Venue validator checks their range.
 */
class MusicBrainzCoordinatesPayloadData extends Data
{
    public function __construct(
        public readonly int|float|string|null $latitude = null,
        public readonly int|float|string|null $longitude = null,
    ) {}
}
