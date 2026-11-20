<?php

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

/**
 * One setlist as the setlist.fm API returns it; songs are not read.
 */
class SetlistFmSetlistPayloadData extends Data
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $url = null,
        public readonly ?string $eventDate = null,
        public readonly ?SetlistFmArtistPayloadData $artist = null,
        public readonly ?SetlistFmVenuePayloadData $venue = null,
        public readonly ?SetlistFmTourPayloadData $tour = null,
    ) {}
}
