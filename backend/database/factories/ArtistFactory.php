<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Artist> */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(), 'type' => null, 'disambiguation' => null,
        ];
    }
}
