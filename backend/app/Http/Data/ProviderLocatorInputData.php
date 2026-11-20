<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * An unchecked performance locator proof payload.
 */
class ProviderLocatorInputData extends Data
{
    public function __construct(
        public readonly ?ProviderRefInputData $ref = null,
        public readonly ?string $url = null,
        public readonly ?string $period = null,
        public readonly ?string $source = null,
        public readonly ?ManualPerformanceInputData $manual = null,
    ) {}
}
