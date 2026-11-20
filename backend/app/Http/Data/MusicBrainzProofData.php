<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The encrypted v1 proof that a MusicBrainz result was offered; read back unchecked.
 */
class MusicBrainzProofData extends Data
{
    public function __construct(
        public readonly ?int $v = null,
        public readonly ?string $kind = null,
        public readonly ?string $mbid = null,
        public readonly ?int $expiresAt = null,
    ) {}
}
