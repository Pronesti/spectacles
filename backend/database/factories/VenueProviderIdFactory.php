<?php

namespace Database\Factories;

use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VenueProviderId> */
class VenueProviderIdFactory extends Factory
{
    protected $model = VenueProviderId::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'provider' => 'geoapify', 'resource_type' => 'place',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
