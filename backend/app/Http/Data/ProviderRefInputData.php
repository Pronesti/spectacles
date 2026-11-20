<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * An unchecked provider identity, as an upstream response or a proof supplied it.
 */
class ProviderRefInputData extends Data
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $resourceType = null,
        public readonly ?string $externalId = null,
    ) {}
}
