<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The filters a Tour search cursor is bound to.
 */
class TourCursorScopeData extends Data
{
    public function __construct(
        public readonly string $artistMbid,
        public readonly string $q,
    ) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this));
    }
}
