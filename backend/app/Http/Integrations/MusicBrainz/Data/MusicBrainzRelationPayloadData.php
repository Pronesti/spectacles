<?php

declare(strict_types=1);

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class MusicBrainzRelationPayloadData extends Data
{
    public function __construct(
        public readonly ?string $type = null,
        #[MapInputName('type-id')]
        public readonly ?string $typeId = null,
        #[MapInputName('target-type')]
        public readonly ?string $targetType = null,
        public readonly ?MusicBrainzEntityPayloadData $artist = null,
        public readonly ?MusicBrainzEntityPayloadData $series = null,
        public readonly ?MusicBrainzUrlPayloadData $url = null,
    ) {}
}
