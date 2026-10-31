<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\SetlistEntry;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SetlistEntry> */
class SetlistEntryFactory extends Factory
{
    protected $model = SetlistEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'song_id' => Song::factory(),
            'position' => fake()->unique()->numberBetween(1, 2000000000),
            'type' => 'song', 'is_tape' => false, 'display_text' => null, 'notes' => null,
        ];
    }

    public function note(string $text = 'Intermission'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'note', 'song_id' => null, 'is_tape' => false, 'display_text' => $text,
        ]);
    }

    public function unidentified(string $text): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'song', 'song_id' => null, 'display_text' => $text]);
    }

    public function tape(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'song', 'is_tape' => true]);
    }
}
