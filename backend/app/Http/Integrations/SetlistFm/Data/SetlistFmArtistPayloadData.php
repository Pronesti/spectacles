<?php

declare(strict_types=1);

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

class SetlistFmArtistPayloadData extends Data
{
    public function __construct(
        public readonly ?string $mbid = null,
        public readonly ?string $name = null,
        public readonly ?string $url = null,
        public readonly ?string $disambiguation = null,
    ) {}
}
