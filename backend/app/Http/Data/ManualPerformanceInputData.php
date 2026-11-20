<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Unchecked performance details carried by a locator proof.
 */
class ManualPerformanceInputData extends Data
{
    public function __construct(
        public readonly ?ProviderEntityInputData $artist = null,
        public readonly ?ProviderEntityInputData $venue = null,
        public readonly ?CalendarDateData $date = null,
        public readonly ?string $tourName = null,
    ) {}
}
