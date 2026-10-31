<?php

namespace Database\Factories;

use App\Models\Song;
use App\Models\SongLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SongLink> */
class SongLinkFactory extends Factory
{
    protected $model = SongLink::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'song_id' => Song::factory(),
            'provider' => 'spotify', 'resource_type' => 'track',
            'provider_resource_id' => (string) fake()->uuid(),
            'url' => null, 'label' => null,
        ];
    }
}
