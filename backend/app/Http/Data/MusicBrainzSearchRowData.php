<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * One normalized MusicBrainz search row, as cached by the client.
 */
class MusicBrainzSearchRowData extends Data
{
    /**
     * @param  list<string>  $writers
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $label,
        public ?string $artistType,
        public ?string $disambiguation,
        public ?string $area,
        public ?string $country,
        public array $writers,
    ) {}
}
