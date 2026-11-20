<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The parts of a submitted Venue checked after validation, read without its image so a
 * malformed image is reported on its own field.
 */
class VenueDraftSummaryData extends Data
{
    public function __construct(
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly Optional|string $catalogId = new Optional,
    ) {}

    public function isSaved(): bool
    {
        return is_string($this->catalogId);
    }

    public function hasOneCoordinate(): bool
    {
        return ($this->lat === null) !== ($this->lng === null);
    }
}
