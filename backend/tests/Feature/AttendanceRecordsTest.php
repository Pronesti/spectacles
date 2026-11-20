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
        expect(Event::count())->toBe(1)
            ->and(User::count())->toBe(1);
        $member = $attendance->members()->sole();
        expect($this->present($member->user)->is($owner))->toBeTrue()
            ->and($member->event_id)->toBe($event->id)
            ->and($member->interested)->toBeFalse()
            ->and($member->tickets_bought)->toBeFalse();
    }

    public function test_factory_preserves_existing_owner_membership(): void
    {
        $owner = User::factory()->create();
        $attendance = Attendance::factory()->for($owner, 'owner')
            ->has(AttendanceMember::factory()->for($owner, 'user')
                ->state(['tickets_bought' => true, 'interested' => true]), 'members')->create();
        $member = $attendance->members()->sole();
        expect($member->user_id)->toBe($owner->id)
            ->and($member->event_id)->toBe($attendance->event_id)
            ->and($member->tickets_bought)->toBeTrue()
            ->and($member->interested)->toBeTrue();
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
        expect($this->present($owner->fresh())->interested)->toBeFalse()
            ->and($this->present($guest->fresh())->tickets_bought)->toBeFalse()
            ->and($guest->seats()->count())->toBe(2);
        $before = $a->members()->count();
        $invite = AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
        expect($a->members()->count())->toBe($before);
        $row = $invite->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('attendance_invitations')->insert($row));
        $invite->update(['status' => 'declined']);
        AttendanceInvitation::factory()->create([
            'attendance_id' => $a->id, 'recipient_user_id' => $invite->recipient_user_id,
        ]);
        expect(AttendanceLink::factory()->create(['attendance_id' => $a->id])->type)->toBe('other');
    }

    public function test_failed_owner_membership_rolls_back_attendance_factory(): void
    {
        $event = Event::factory()->create();
        $owner = User::factory()->create();
        Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => $owner->id]);
        $this->assertDatabaseRejects(fn () => Attendance::factory()->create([
            'event_id' => $event->id, 'owner_user_id' => $owner->id,
        ]));
        expect(Attendance::count())->toBe(1);
    }
}
