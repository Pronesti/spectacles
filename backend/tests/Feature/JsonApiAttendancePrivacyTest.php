<?php

namespace Tests\Feature;

use App\Http\Resources\EventResource;
use App\Models\Artist;
use App\Models\Attendance;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\Event;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JsonApiAttendancePrivacyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('includePaths')]
    public function test_private_includes_are_filtered_at_every_depth(string $root, string $include, string $viewerRole): void
    {
        $graph = $this->graph();
        $viewer = match ($viewerRole) {
            'owner' => $graph['owner'],
            'member' => $graph['member'],
            'outsider' => User::factory()->create(),
            default => null,
        };
        $model = $graph[$root];
        $model->load(explode(',', $include));
        $document = $this->document($model, $include, $viewer);
        $json = json_encode($document, JSON_THROW_ON_ERROR);

        foreach ($graph['hidden'] as $record) {
            $this->assertStringNotContainsString($record->external_id, $json);
        }
        $this->assertStringNotContainsString('Hidden attendance notes', $json);
        if (in_array($viewerRole, ['owner', 'member'], true)) {
            $this->assertStringContainsString($graph['attendance']->external_id, $json);
            $this->assertStringContainsString('Shared attendance notes', $json);
            $this->assertStringContainsString($graph['entry']->external_id, $json);
            $this->assertStringContainsString($graph['seat']->external_id, $json);
            $this->assertStringContainsString($graph['link']->external_id, $json);
        } else {
            foreach (['attendance', 'entry', 'seat', 'link', 'credit'] as $key) {
                $this->assertStringNotContainsString($graph[$key]->external_id, $json);
            }
            $this->assertStringNotContainsString('Shared attendance notes', $json);
        }
    }

    public function test_pending_invitation_recipient_cannot_include_private_attendance_details(): void
    {
        $graph = $this->graph();
        $recipient = User::factory()->create();
        $invitation = AttendanceInvitation::factory()->create([
            'attendance_id' => $graph['attendance']->id,
            'inviter_user_id' => $graph['owner']->id,
            'recipient_user_id' => $recipient->id,
        ]);
        $include = 'attendance.members.seats,attendance.entries.artistLinks,attendance.links,inviter.ownedAttendances';
        $invitation->load(explode(',', $include));

        $document = $this->document($invitation, $include, $recipient);
        $this->assertSame('pending', $document['data']['attributes']['status']);
        $this->assertNull($document['data']['relationships']['attendance']['data']);
        $json = json_encode($document, JSON_THROW_ON_ERROR);
        foreach (['attendance', 'entry', 'seat', 'link', 'credit'] as $key) {
            $this->assertStringNotContainsString($graph[$key]->external_id, $json);
        }

        AttendanceMember::factory()->create([
            'attendance_id' => $graph['attendance']->id, 'user_id' => $recipient->id,
        ]);
        $invitation->update(['status' => 'accepted']);
        $acceptedDocument = $this->document($invitation, $include, $recipient);
        $this->assertSame($graph['attendance']->external_id,
            $acceptedDocument['data']['relationships']['attendance']['data']['id']);
    }

    public function test_user_invitation_includes_only_return_own_invitations_or_member_attendances(): void
    {
        $graph = $this->graph();
        $recipient = User::factory()->create();
        $invitation = AttendanceInvitation::factory()->create([
            'attendance_id' => $graph['attendance']->id,
            'inviter_user_id' => $graph['owner']->id,
            'recipient_user_id' => $recipient->id,
        ]);
        $include = 'receivedAttendanceInvitations.attendance.members';

        $own = $this->document($recipient, $include, $recipient);
        $this->assertSame($invitation->external_id,
            $own['data']['relationships']['receivedAttendanceInvitations']['data'][0]['id']);
        $this->assertStringNotContainsString($graph['attendance']->external_id, json_encode($own, JSON_THROW_ON_ERROR));

        foreach ([null, User::factory()->create()] as $viewer) {
            $other = $this->document($recipient, $include, $viewer);
            $this->assertSame([], $other['data']['relationships']['receivedAttendanceInvitations']['data']);
            $this->assertStringNotContainsString($invitation->external_id, json_encode($other, JSON_THROW_ON_ERROR));
        }
    }

    public function test_collection_includes_do_not_share_access_between_users(): void
    {
        $graph = $this->graph();
        $otherAttendance = Attendance::factory()->create(['event_id' => $graph['event']->id, 'notes' => 'Other private notes']);
        $request = $this->request('attendances.members.user', $graph['member']);
        $document = EventResource::collection(collect([$graph['event']]))
            ->toResponse($request)->getData(true);
        $ids = array_column($document['data'][0]['relationships']['attendances']['data'], 'id');
        $this->assertSame([$graph['attendance']->external_id], $ids);
        $this->assertStringNotContainsString($otherAttendance->external_id, json_encode($document, JSON_THROW_ON_ERROR));
    }

    public function test_collaborators_do_not_expose_their_other_attendances_through_nested_user_includes(): void
    {
        $graph = $this->graph();
        $other = Attendance::factory()->create(['owner_user_id' => $graph['member']->id, 'notes' => 'Collaborator private notes']);
        $include = 'members.user.ownedAttendances.entries,members.user.attendanceMemberships.attendance';
        $document = $this->document($graph['attendance'], $include, $graph['owner']);
        $json = json_encode($document, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($other->external_id, $json);
        $this->assertStringNotContainsString('Collaborator private notes', $json);
        $this->assertStringContainsString($graph['member']->external_id, $json);
        $this->assertStringContainsString($graph['attendance']->external_id, $json);
    }

    public function test_preloaded_relationships_cannot_bypass_membership_filtering_without_include_parameter(): void
    {
        $graph = $this->graph();
        $graph['song']->load('entries.attendance.members.seats', 'entries.artistLinks');
        foreach ([null, User::factory()->create()] as $viewer) {
            $document = $graph['song']->toResource()->includePreviouslyLoadedRelationships()
                ->toResponse($this->request('', $viewer))->getData(true);

            $this->assertSame([], $document['data']['relationships']['entries']['data']);
            $this->assertArrayNotHasKey('included', $document);
        }

        $document = $graph['song']->toResource()->includePreviouslyLoadedRelationships()
            ->toResponse($this->request('', $graph['member']))->getData(true);
        $this->assertSame([$graph['entry']->external_id],
            array_column($document['data']['relationships']['entries']['data'], 'id'));
    }

    private function request(string $include, ?User $viewer): Request
    {
        $request = Request::create('/resources', 'GET', ['include' => $include]);
        $request->setUserResolver(fn () => $viewer);

        return $request;
    }

    private function document(Model $model, string $include, ?User $viewer): array
    {
        return $model->toResource()->toResponse($this->request($include, $viewer))->getData(true);
    }

    private function graph(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $artist = Artist::factory()->create();
        $song = Song::factory()->create();
        $event = Event::factory()->create();
        $attendance = Attendance::factory()->create([
            'event_id' => $event->id, 'owner_user_id' => $owner->id, 'notes' => 'Shared attendance notes',
        ]);
        $membership = AttendanceMember::factory()->create(['attendance_id' => $attendance->id, 'user_id' => $member->id]);
        $seat = AttendanceMemberSeat::factory()->create(['attendance_member_id' => $membership->id]);
        $link = AttendanceLink::factory()->create(['attendance_id' => $attendance->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $attendance->id, 'song_id' => $song->id]);
        $credit = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id, 'artist_id' => $artist->id]);

        $hiddenAttendance = Attendance::factory()->create(['notes' => 'Hidden attendance notes']);
        $hiddenEntry = SetlistEntry::factory()->create(['attendance_id' => $hiddenAttendance->id, 'song_id' => $song->id]);
        $hiddenCredit = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $hiddenEntry->id, 'artist_id' => $artist->id]);
        $hiddenSeat = AttendanceMemberSeat::factory()->create(['attendance_member_id' => $hiddenAttendance->members()->sole()->id]);
        $hiddenLink = AttendanceLink::factory()->create(['attendance_id' => $hiddenAttendance->id]);
        $hidden = [$hiddenAttendance, $hiddenEntry, $hiddenCredit, $hiddenSeat, $hiddenLink];

        return compact('owner', 'member', 'artist', 'song', 'event', 'attendance', 'seat', 'link', 'entry', 'credit', 'hidden');
    }

    public static function includePaths(): array
    {
        $paths = [
            'event' => ['attendances.members.seats,attendances.entries.artistLinks,attendances.links'],
            'song' => ['entries.attendance.members.seats,entries.attendance.links,entries.artistLinks'],
            'artist' => ['entryLinks.entry.attendance.members.seats,entryLinks.entry.attendance.links'],
            'member' => ['ownedAttendances.members.seats,ownedAttendances.entries.artistLinks,ownedAttendances.links,attendanceMemberships.attendance.entries.artistLinks,attendanceMemberships.attendance.members.seats,attendanceMemberships.attendance.links'],
        ];
        $cases = [];
        foreach ($paths as $root => [$include]) {
            foreach (['anonymous', 'outsider', 'owner', 'member'] as $role) {
                $cases[$root.' '.$role] = [$root, $include, $role];
            }
        }

        return $cases;
    }
}
