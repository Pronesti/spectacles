<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventProviderReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventProviderReference> */
class EventProviderReferenceFactory extends Factory
{
    protected $model = EventProviderReference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'provider' => 'setlist_fm', 'resource_type' => 'setlist',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
