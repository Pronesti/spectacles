<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AttendanceMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attendance_id', 'event_id', 'user_id', 'interested', 'tickets_bought'])]
class AttendanceMember extends Model
{
    /** @use HasFactory<AttendanceMemberFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'attendance_members';

    protected $attributes = ['interested' => false, 'tickets_bought' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['interested' => 'boolean', 'tickets_bought' => 'boolean'];
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AttendanceMemberSeat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(AttendanceMemberSeat::class);
    }
}
