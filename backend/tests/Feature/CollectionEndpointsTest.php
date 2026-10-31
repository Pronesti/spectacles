<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): array
    {
        return ['concert' => [
            'id' => (string) Str::uuid(), 'title' => 'Noche Ñ', 'artists' => ['Björk, guest', 'Björk, guest'],
            'venueId' => null, 'date' => ['year' => 2024, 'month' => null, 'day' => null], 'time' => '00:00:07', 'timezone' => null,
            'interested' => false, 'ticketsBought' => true, 'notes' => 'My notes',
            'catalog' => ['artistIds' => [null, null], 'tours' => []],
            'setlist' => [
                ['id' => (string) Str::uuid(), 'type' => 'song', 'title' => 'Canción', 'artist' => 'Mi artista', 'tape' => false],
                ['id' => (string) Str::uuid(), 'type' => 'note', 'title' => 'Encore', 'artist' => '', 'tape' => false],
            ],
        ]];
    }

    public function test_collection_requires_authentication_and_starts_empty_without_demo_data(): void
    {
        $this->getJson('/api/v1/collection')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/collection')->assertOk()->assertJsonPath('data.concerts', [])->assertJsonPath('data.venues', []);
    }

    public function test_manual_concert_and_setlist_round_trip_and_other_accounts_cannot_see_or_change_it(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $draft = $this->draft();
        $saved = $this->actingAs($owner)->postJson('/api/v1/attendances', $draft)->assertOk()
            ->assertJsonPath('data.concerts.0.id', $draft['concert']['id'])
            ->assertJsonPath('data.concerts.0.artists', ['Björk, guest', 'Björk, guest'])
            ->assertJsonPath('data.concerts.0.time', '00:00:07')->assertJsonPath('data.concerts.0.timezone', null)
            ->assertJsonPath('data.concerts.0.interested', false)->assertJsonPath('data.concerts.0.notes', 'My notes')
            ->json('data.concerts.0');
        $this->assertNotSame($saved['catalog']['artistIds'][0], $saved['catalog']['artistIds'][1]);
        $this->actingAs($other)->getJson('/api/v1/collection')->assertJsonPath('data.concerts', []);
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $saved])->assertNotFound();
        $this->deleteJson('/api/v1/attendances/'.$saved['id'])->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/collection')->assertJsonPath('data.concerts.0', $saved);
    }

    public function test_setlist_reorder_and_delete_are_atomic_and_undo_can_restore_public_entry_ids(): void
    {
        $this->actingAs(User::factory()->create());
        $saved = $this->postJson('/api/v1/attendances', $this->draft())->assertOk()->json('data.concerts.0');
        $saved['setlist'] = array_reverse($saved['setlist']);
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $saved])->assertOk()->assertJsonPath('data.concerts.0.setlist.0.type', 'note');
        $original = $saved['setlist'];
        $saved['setlist'] = [$saved['setlist'][0]];
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $saved])->assertOk();
        $saved['setlist'] = $original;
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $saved])->assertOk()->assertJsonCount(2, 'data.concerts.0.setlist');
        $this->deleteJson('/api/v1/attendances/'.$saved['id'])->assertOk()->assertJsonPath('data.concerts', []);
        $this->postJson('/api/v1/attendances', ['concert' => $saved])->assertOk()->assertJsonPath('data.concerts.0.id', $saved['id']);
    }

    public function test_catalog_event_reused_and_personal_updates_do_not_change_shared_facts(): void
    {
        $this->actingAs(User::factory()->create());
        $event = Event::factory()->create(['title' => 'Shared Event']);
        $draft = $this->draft();
        $draft['concert']['catalog']['eventId'] = $event->external_id;
        $saved = $this->postJson('/api/v1/attendances', $draft)->assertOk()->assertJsonPath('data.concerts.0.title', 'Shared Event')->json('data.concerts.0');
        $saved['notes'] = 'Updated';
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $saved])->assertOk()->assertJsonPath('data.concerts.0.notes', 'Updated');
        $this->assertSame('Shared Event', $event->fresh()->title);
        $this->postJson('/api/v1/attendances', $draft)->assertConflict();
    }

    public function test_shared_fact_edit_preserves_seats_and_entries_without_modifying_the_original_event(): void
    {
        $this->actingAs(User::factory()->create());
        $saved = $this->postJson('/api/v1/attendances', $this->draft())->assertOk()->json('data.concerts.0');
        $oldEvent = $saved['catalog']['eventId'];
        $member = AttendanceMember::query()->firstOrFail();
        $seat = $member->seats()->create(['label' => 'Keep this seat']);
        unset($saved['catalog']['eventId']);
        $saved['title'] = 'Edited';
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $saved])->assertOk()->assertJsonPath('data.concerts.0.title', 'Edited');
        $this->assertSame('Noche Ñ', Event::query()->where('external_id', $oldEvent)->firstOrFail()->title);
        $this->assertDatabaseHas('attendance_member_seats', ['external_id' => $seat->external_id, 'label' => 'Keep this seat']);
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_invalid_partial_dates_and_note_identity_or_tape_are_rejected_without_writes(): void
    {
        $this->actingAs(User::factory()->create());
        $draft = $this->draft();
        $draft['concert']['date'] = ['year' => 2025, 'month' => 2, 'day' => 29];
        $this->postJson('/api/v1/attendances', $draft)->assertUnprocessable();
        $draft = $this->draft();
        $draft['concert']['setlist'][1]['tape'] = true;
        $this->postJson('/api/v1/attendances', $draft)->assertUnprocessable();
        $this->assertDatabaseCount('events', 0);
    }

    public function test_accepted_members_share_notes_and_setlist_but_keep_their_own_flags_and_cannot_delete(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $saved = $this->actingAs($owner)->postJson('/api/v1/attendances', $this->draft())->assertOk()->json('data.concerts.0');
        $attendance = Attendance::query()->firstOrFail();
        $attendance->members()->create(['event_id' => $attendance->event_id, 'user_id' => $guest->id, 'interested' => true, 'tickets_bought' => false]);
        $guestConcert = $this->actingAs($guest)->getJson('/api/v1/collection')->assertOk()->assertJsonPath('data.concerts.0.ticketsBought', false)->json('data.concerts.0');
        $guestConcert['notes'] = 'Shared notes';
        $guestConcert['interested'] = false;
        $this->patchJson('/api/v1/attendances/'.$saved['id'], ['concert' => $guestConcert])->assertOk();
        $this->deleteJson('/api/v1/attendances/'.$saved['id'])->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/collection')->assertJsonPath('data.concerts.0.ticketsBought', true)->assertJsonPath('data.concerts.0.notes', 'Shared notes');
    }

    public function test_archive_and_restore_retain_other_members_and_seats_and_readding_the_same_event_works(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $saved = $this->postJson('/api/v1/attendances', $this->draft())->assertOk()->json('data.concerts.0');
        $member = AttendanceMember::query()->firstOrFail();
        $seat = $member->seats()->create(['label' => 'Retained']);
        $this->deleteJson('/api/v1/attendances/'.$saved['id'])->assertOk()->assertJsonPath('data.concerts', []);
        $saved['id'] = (string) Str::uuid();
        $this->postJson('/api/v1/attendances', ['concert' => $saved])->assertOk()->assertJsonCount(1, 'data.concerts');
        $this->assertDatabaseHas('attendance_member_seats', ['external_id' => $seat->external_id]);
    }
}
