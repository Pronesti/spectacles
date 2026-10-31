<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AttendanceMemberSeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attendance_member_id', 'section_label', 'row_label', 'seat_label', 'label'])]
class AttendanceMemberSeat extends Model
{
    /** @use HasFactory<AttendanceMemberSeatFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'attendance_member_seats';

    /** @return BelongsTo<AttendanceMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(AttendanceMember::class, 'attendance_member_id');
    }
}
