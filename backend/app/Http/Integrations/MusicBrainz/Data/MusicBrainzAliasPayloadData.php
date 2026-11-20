<?php

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Data;

class MusicBrainzAliasPayloadData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
    ) {}
}
