<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourProviderId> */
class TourProviderIdFactory extends Factory
{
    protected $model = TourProviderId::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'provider' => 'musicbrainz', 'resource_type' => 'series',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
