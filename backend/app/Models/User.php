<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<AttendanceMember, $this> */
    public function attendanceMemberships(): HasMany
    {
        return $this->hasMany(AttendanceMember::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function ownedAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'owner_user_id');
    }

    /** @return HasMany<AttendanceInvitation, $this> */
    public function sentAttendanceInvitations(): HasMany
    {
        return $this->hasMany(AttendanceInvitation::class, 'inviter_user_id');
    }

    /** @return HasMany<AttendanceInvitation, $this> */
    public function receivedAttendanceInvitations(): HasMany
    {
        return $this->hasMany(AttendanceInvitation::class, 'recipient_user_id');
    }
}
