<?php

namespace App\Http;

use App\Http\Data\PendingIdentityData;

/**
 * The catalog external ids each pending draft resolved to, looked up by its client key.
 */
final class ResolvedCatalogIds
{
    /**
     * @var array<string, array<int|string, string>>
     */
    private array $ids = ['artists' => [], 'songs' => [], 'tours' => []];

    public function record(PendingIdentityData $job, string $externalId): void
    {
        $this->ids[$job->map][$job->key] = $externalId;
    }

    public function artist(int|string $key): string
    {
        return $this->ids['artists'][$key];
    }

    public function song(int|string $key): string
    {
        return $this->ids['songs'][$key];
    }

    public function tour(int|string $key): string
    {
        return $this->ids['tours'][$key];
    }
}
