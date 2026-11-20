<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The encrypted pagination cursor of a provider search; read back unchecked.
 */
class ProviderCursorTokenData extends Data
{
    public function __construct(
        public readonly ?string $operation = null,
        public readonly ?string $scope = null,
        public readonly ?int $page = null,
        public readonly ?int $offset = null,
        public readonly ?int $scanned = null,
        public readonly ?int $expiresAt = null,
    ) {}
}
