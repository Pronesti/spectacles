<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * One pending catalog record to find or create, with the provider identity its proof names.
 */
class PendingIdentityData extends Data
{
    public function __construct(
        public readonly string $map,
        public readonly int|string $key,
        public readonly string $kind,
        public readonly ?ProviderRefData $ref,
        public readonly PendingArtistData|PendingSongData|PendingTourData $draft,
    ) {}

    /**
     * Identities are resolved in a stable order so concurrent saves lock rows in the same order.
     */
    public function sortKey(): string
    {
        return $this->ref?->key() ?? json_encode($this->key, JSON_THROW_ON_ERROR);
    }
}
