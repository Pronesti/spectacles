<?php

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A MusicBrainz artist, work, series or place as the web service returns it.
 * Every field is optional because callers decide which ones they require.
 */
class MusicBrainzEntityPayloadData extends Data
{
    /**
     * @param  array<MusicBrainzRelationPayloadData>|null  $relations
     * @param  array<MusicBrainzAliasPayloadData>|null  $aliases
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $name = null,
        public readonly ?string $title = null,
        public readonly ?string $type = null,
        public readonly ?string $disambiguation = null,
        public readonly ?string $country = null,
        public readonly ?string $address = null,
        public readonly ?MusicBrainzAreaPayloadData $area = null,
        public readonly ?MusicBrainzCoordinatesPayloadData $coordinates = null,
        #[DataCollectionOf(MusicBrainzRelationPayloadData::class)]
        public readonly ?array $relations = null,
        #[DataCollectionOf(MusicBrainzAliasPayloadData::class)]
        public readonly ?array $aliases = null,
    ) {}
}
