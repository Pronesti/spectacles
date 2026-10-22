<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventTour;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventTour> */
class EventTourFactory extends Factory
{
    protected $model = EventTour::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'tour_id' => Tour::factory(),
        ];
    }
}
