<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * An Artist offered by a provider. The selection proof and local match are
 * only present once the Artist is offered to the viewer.
 */
class ProviderArtistData extends Data
{
    use CopiesWithChanges;

    public function __construct(
        public readonly ProviderRefData $ref,
        public readonly ArtistValuesData $values,
        public readonly string $url,
        public readonly Optional|string $selection = new Optional,
        public readonly Optional|string|null $localId = new Optional,
    ) {}
}
