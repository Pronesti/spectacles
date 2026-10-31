<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'owner_user_id', 'notes'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'attendances';

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<AttendanceMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(AttendanceMember::class);
    }

    /** @return HasMany<AttendanceInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(AttendanceInvitation::class);
    }

    /** @return HasMany<AttendanceLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(AttendanceLink::class);
    }

    /** @return HasMany<SetlistEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SetlistEntry::class)->orderBy('position');
    }
}
