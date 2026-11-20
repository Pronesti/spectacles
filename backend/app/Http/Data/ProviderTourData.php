<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A Tour offered by a provider. The local match and selection proof are only
 * present once the Tour is offered to the viewer.
 */
class ProviderTourData extends Data
{
    use CopiesWithChanges;

    public function __construct(
        public readonly ProviderRefData $ref,
        public readonly TourValuesData $values,
        public readonly string $url,
        public readonly Optional|string|null $localId = new Optional,
        public readonly Optional|string $selection = new Optional,
    ) {}
}
