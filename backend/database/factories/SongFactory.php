<?php

namespace Database\Factories;

use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Song> */
class SongFactory extends Factory
{
    protected $model = Song::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true), 'disambiguation' => null,
        ];
    }
}
