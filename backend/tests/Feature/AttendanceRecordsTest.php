<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_reuses_event_and_creates_exactly_one_owner_membership(): void
    {
        $event = Event::factory()->create();
        $owner = User::factory()->create();
        $attendance = Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => $owner->id]);
        $this->assertSame(1, Event::count());
        $this->assertSame(1, User::count());
        $member = $attendance->members()->sole();
        $this->assertTrue($member->user->is($owner));
        $this->assertSame($event->id, $member->event_id);
        $this->assertFalse($member->interested);
        $this->assertFalse($member->tickets_bought);
    }

    public function test_factory_preserves_existing_owner_membership(): void
    {
        $owner = User::factory()->create();
        $attendance = Attendance::factory()->for($owner, 'owner')
            ->has(AttendanceMember::factory()->for($owner, 'user')
                ->state(['tickets_bought' => true, 'interested' => true]), 'members')->create();
        $member = $attendance->members()->sole();
        $this->assertSame($owner->id, $member->user_id);
        $this->assertSame($attendance->event_id, $member->event_id);
        $this->assertTrue($member->tickets_bought);
        $this->assertTrue($member->interested);
    }

    public function test_membership_foreign_key_and_event_uniqueness_are_database_rules(): void
    {
        $a = Attendance::factory()->create();
        $b = Attendance::factory()->create(['event_id' => $a->event_id]);
        $row = $a->members()->sole()->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $row['attendance_id'] = $b->id;
        $this->assertDatabaseRejects(fn () => DB::table('attendance_members')->insert($row));
        $row['user_id'] = User::factory()->create()->id;
        $row['event_id'] = Event::factory()->create()->id;
        $this->assertDatabaseRejects(fn () => DB::table('attendance_members')->insert($row));
    }

    public function test_flags_seats_and_pending_invites_are_independent(): void
    {
        $a = Attendance::factory()->create();
        $owner = $a->members()->sole();
        $guest = AttendanceMember::factory()->create(['attendance_id' => $a->id, 'interested' => true]);
        AttendanceMemberSeat::factory()->count(2)->create(['attendance_member_id' => $guest->id]);
        $owner->update(['tickets_bought' => true]);
        $this->assertFalse($owner->fresh()->interested);
        $this->assertFalse($guest->fresh()->tickets_bought);
        $this->assertSame(2, $guest->seats()->count());
        $before = $a->members()->count();
        $invite = AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
        $this->assertSame($before, $a->members()->count());
        $row = $invite->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('attendance_invitations')->insert($row));
        $invite->update(['status' => 'declined']);
        AttendanceInvitation::factory()->create([
            'attendance_id' => $a->id, 'recipient_user_id' => $invite->recipient_user_id,
        ]);
        $this->assertSame('other', AttendanceLink::factory()->create(['attendance_id' => $a->id])->type);
    }

    public function test_failed_owner_membership_rolls_back_attendance_factory(): void
    {
        $event = Event::factory()->create();
        $owner = User::factory()->create();
        Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => $owner->id]);
        $this->assertDatabaseRejects(fn () => Attendance::factory()->create([
            'event_id' => $event->id, 'owner_user_id' => $owner->id,
        ]));
        $this->assertSame(1, Attendance::count());
    }
}
