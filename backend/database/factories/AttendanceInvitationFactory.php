<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceInvitation> */
class AttendanceInvitationFactory extends Factory
{
    protected $model = AttendanceInvitation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'inviter_user_id' => fn (array $attributes): int => Attendance::findOrFail($attributes['attendance_id'])->owner_user_id,
            'recipient_user_id' => User::factory(), 'status' => 'pending',
        ];
    }
}
