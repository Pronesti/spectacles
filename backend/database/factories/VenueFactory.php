<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venue> */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(), 'street' => null, 'house_number' => null,
            'postal_code' => null, 'city' => null, 'region' => null, 'country_code' => null,
            'lat' => null, 'lng' => null, 'timezone' => null,
        ];
    }
}
