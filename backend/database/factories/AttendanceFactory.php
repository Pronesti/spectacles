<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends Factory<Attendance> */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'owner_user_id' => User::factory(),
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Attendance $attendance): void {
            $attendance->members()->firstOrCreate(
                ['user_id' => $attendance->owner_user_id],
                ['event_id' => $attendance->event_id],
            );
        });
    }

    public function create(mixed $attributes = [], ?Model $parent = null): Model|Collection
    {
        return DB::transaction(fn () => parent::create($attributes, $parent));
    }
}
