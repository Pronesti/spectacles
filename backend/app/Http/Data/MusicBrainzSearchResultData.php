<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * A MusicBrainz search row with its local match and selection proof.
 */
class MusicBrainzSearchResultData extends Data
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
        public ?MusicBrainzLocalMatchData $local,
        public string $selection,
    ) {}

    public static function fromRow(MusicBrainzSearchRowData $row, ?MusicBrainzLocalMatchData $local, string $selection): self
    {
        return new self($row->id, $row->kind, $row->label, $row->artistType, $row->disambiguation, $row->area, $row->country, $row->writers, $local, $selection);
    }
}
