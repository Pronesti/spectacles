<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Tour;
use App\Models\TourArtist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourArtist> */
class TourArtistFactory extends Factory
{
    protected $model = TourArtist::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'artist_id' => Artist::factory(),
        ];
    }
}
