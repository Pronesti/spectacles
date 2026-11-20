<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The saved catalog record that already carries a MusicBrainz identity.
 */
class MusicBrainzLocalMatchData extends Data
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $artistType,
        public ?string $disambiguation,
    ) {}
}
