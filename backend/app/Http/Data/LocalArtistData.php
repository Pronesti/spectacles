<?php

namespace App\Http\Data;

use App\Models\Artist;
use Spatie\LaravelData\Data;

class LocalArtistData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $type,
        public ?string $disambiguation,
    ) {}

    public static function fromArtist(Artist $artist): self
    {
        return new self($artist->external_id, $artist->name, $artist->type, $artist->disambiguation);
    }
}
