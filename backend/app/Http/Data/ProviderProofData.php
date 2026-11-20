<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The encrypted v2 proof envelope for a provider selection; read back unchecked.
 *
 * The payload stays decoded JSON because its class depends on `kind`.
 */
class ProviderProofData extends Data
{
    /**
     * @param  array<mixed>|null  $payload
     */
    public function __construct(
        public readonly ?int $v = null,
        public readonly ?string $kind = null,
        public readonly ?array $payload = null,
        public readonly ?int $expiresAt = null,
    ) {}
}
