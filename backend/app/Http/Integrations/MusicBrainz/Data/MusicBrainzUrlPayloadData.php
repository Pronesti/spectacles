<?php

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Data;

class MusicBrainzUrlPayloadData extends Data
{
    public function __construct(
        public readonly ?string $resource = null,
    ) {}
}
