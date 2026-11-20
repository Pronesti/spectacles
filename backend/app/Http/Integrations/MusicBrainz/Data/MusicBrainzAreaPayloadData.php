<?php

declare(strict_types=1);

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class MusicBrainzAreaPayloadData extends Data
{
    /**
     * @param  array<mixed>|null  $countryCodes
     */
    public function __construct(
        public readonly ?string $name = null,
        #[MapInputName('iso-3166-1-codes')]
        public readonly ?array $countryCodes = null,
    ) {}
}
