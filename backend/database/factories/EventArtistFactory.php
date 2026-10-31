<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Event;
use App\Models\EventArtist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventArtist> */
class EventArtistFactory extends Factory
{
    protected $model = EventArtist::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'artist_id' => Artist::factory(),
            'position' => null,
        ];
    }
}
