<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * What a performance search row's locator proof binds: the setlist and the search period.
 */
class ProviderLocatorData extends Data
{
    public function __construct(
        public readonly ProviderRefData $ref,
        public readonly string $url,
        public readonly string $period,
        public readonly Optional|string $source = new Optional,
        public readonly Optional|ManualPerformanceData $manual = new Optional,
    ) {}
}
