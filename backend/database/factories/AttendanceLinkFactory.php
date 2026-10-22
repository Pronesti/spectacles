<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceLink> */
class AttendanceLinkFactory extends Factory
{
    protected $model = AttendanceLink::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'url' => 'https://example.com/album', 'label' => null, 'type' => 'other',
        ];
    }
}
