<?php

namespace App\Http\Integrations\MusicBrainz\Data;

use Spatie\LaravelData\Data;

/**
 * A MusicBrainz search response. Rows stay undecoded here so that one malformed
 * row can be skipped without rejecting the whole page.
 */
class MusicBrainzSearchPayloadData extends Data
{
    /**
     * @param  array<mixed>|null  $artists
     * @param  array<mixed>|null  $works
     * @param  array<mixed>|null  $series
     * @param  array<mixed>|null  $places
     */
    public function __construct(
        public readonly ?array $artists = null,
        public readonly ?array $works = null,
        public readonly ?array $series = null,
        public readonly ?array $places = null,
    ) {}
}
