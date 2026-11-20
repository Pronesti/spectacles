<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * One provider identity, such as a MusicBrainz place or a setlist.fm venue.
 */
class ProviderRefData extends Data
{
    public function __construct(
        public readonly string $provider,
        public readonly string $resourceType,
        public readonly string $externalId,
    ) {}

    /**
     * A stable string for de-duplicating and ordering identities.
     */
    public function key(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function is(self $other): bool
    {
        return $this->key() === $other->key();
    }

    /**
     * The identity as columns of a `*_provider_ids` table.
     *
     * @return array<string, string>
     */
    public function tuple(string $idColumn = 'provider_resource_id'): array
    {
        return ['provider' => $this->provider, 'resource_type' => $this->resourceType, $idColumn => $this->externalId];
    }
}
