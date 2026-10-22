<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->words(4, true), 'type' => 'concert', 'venue_id' => null,
            'year' => null, 'month' => null, 'day' => null,
            'local_start_time' => null, 'timezone' => null,
        ];
    }
}
