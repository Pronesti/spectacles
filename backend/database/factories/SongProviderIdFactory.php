<?php

namespace Database\Factories;

use App\Models\Song;
use App\Models\SongProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SongProviderId> */
class SongProviderIdFactory extends Factory
{
    protected $model = SongProviderId::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'song_id' => Song::factory(),
            'provider' => 'musicbrainz', 'resource_type' => 'work',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
