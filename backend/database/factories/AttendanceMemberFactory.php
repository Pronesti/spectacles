<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceMember> */
class AttendanceMemberFactory extends Factory
{
    protected $model = AttendanceMember::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'event_id' => fn (array $attributes): int => Attendance::findOrFail($attributes['attendance_id'])->event_id,
            'user_id' => User::factory(),
            'interested' => false, 'tickets_bought' => false,
        ];
    }
}
