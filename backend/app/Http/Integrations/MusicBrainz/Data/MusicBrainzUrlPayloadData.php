<?php

declare(strict_types=1);

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Data;

class MusicBrainzUrlPayloadData extends Data
{
    public function __construct(
        public readonly ?string $resource = null,
    ) {}
}
