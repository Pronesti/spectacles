<?php

namespace Database\Factories;

use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceMemberSeat> */
class AttendanceMemberSeatFactory extends Factory
{
    protected $model = AttendanceMemberSeat::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attendance_member_id' => AttendanceMember::factory(),
            'section_label' => null, 'row_label' => null, 'seat_label' => null,
            'label' => 'General admission',
        ];
    }
}
