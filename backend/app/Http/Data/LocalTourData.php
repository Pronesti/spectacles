<?php

namespace App\Http\Data;

use App\Models\Tour;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class LocalTourData extends Data
{
    /**
     * @param  list<LocalArtistData>  $artists
     */
    public function __construct(
        public string $id,
        public string $name,
        #[DataCollectionOf(LocalArtistData::class)]
        public array $artists,
    ) {}

    public static function fromTour(Tour $tour): self
    {
        return new self($tour->external_id, $tour->name, array_values($tour->artistLinks->map(fn ($link): LocalArtistData => LocalArtistData::fromArtist($link->artist))->all()));
    }
}
