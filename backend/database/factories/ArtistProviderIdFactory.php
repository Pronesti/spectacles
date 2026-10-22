<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\ArtistProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArtistProviderId> */
class ArtistProviderIdFactory extends Factory
{
    protected $model = ArtistProviderId::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'provider' => 'musicbrainz', 'resource_type' => 'artist',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
