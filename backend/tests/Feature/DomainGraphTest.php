<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Attendance;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventProviderReference;
use App\Models\EventTour;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use App\Models\SongLink;
use App\Models\SongProviderId;
use App\Models\Tour;
use App\Models\TourArtist;
use App\Models\TourProviderId;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Database\Seeders\LiveEventSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_indexes_cover_parent_lookups_without_redundant_indexes(): void
    {
        $parents = [
            'event_artists' => 'event_id', 'tour_artists' => 'tour_id',
            'event_tours' => 'event_id', 'attendance_members' => 'user_id',
            'setlist_entries' => 'attendance_id', 'setlist_entry_artists' => 'setlist_entry_id',
            'event_provider_references' => 'event_id', 'song_links' => 'song_id',
        ];
        foreach ($parents as $table => $column) {
            $indexes = collect(Schema::getIndexes($table));
            expect($indexes->contains(fn ($index): bool => $index['columns'] === [$column]))->toBeFalse()
                ->and($indexes->contains(fn ($index): bool => $index['unique']
                && $index['columns'][0] === $column))->toBeTrue();
        }
        foreach (['event_artists' => 'artist_id', 'attendance_invitations' => 'attendance_id'] as $table => $column) {
            expect(collect(Schema::getIndexes($table))->contains(
                fn ($index): bool => ! $index['unique'] && $index['columns'] === [$column]
            ))->toBeTrue();
        }
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('models')]
    public function test_every_model_factory_has_independent_immutable_public_identity(string $class): void
    {
        $row = Factory::factoryForModel($class)->createOne();
        expect($row->getAttribute('id'))->toBeInt()
            ->and(Str::isUuid($row->getAttribute('external_id')))->toBeTrue()
            ->and($row->getRouteKeyName())->toBe('external_id')
            ->and($this->present($row->resolveRouteBinding($row->getAttribute('external_id')))->is($row))->toBeTrue();
        $uuid = $row->getAttribute('external_id');
        $row->save();
        expect($this->present($row->fresh())->getAttribute('external_id'))->toBe($uuid)
            ->and($row->replicate()->getAttribute('external_id'))->toBeNull();
        $this->assertDatabaseRejects(fn () => Factory::factoryForModel($class)->create(['external_id' => $uuid]));
        $row->setAttribute('external_id', (string) Str::uuid7());
        try {
            $row->save();
            $this->fail('Changing public identity was accepted.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('external_id');
        }
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return [
            'User' => [User::class],
            'Artist' => [Artist::class],
            'Venue' => [Venue::class],
            'Tour' => [Tour::class],
            'Song' => [Song::class],
            'Event' => [Event::class],
            'EventArtist' => [EventArtist::class],
            'TourArtist' => [TourArtist::class],
            'EventTour' => [EventTour::class],
            'Attendance' => [Attendance::class],
            'AttendanceMember' => [AttendanceMember::class],
            'AttendanceMemberSeat' => [AttendanceMemberSeat::class],
            'AttendanceInvitation' => [AttendanceInvitation::class],
            'AttendanceLink' => [AttendanceLink::class],
            'SetlistEntry' => [SetlistEntry::class],
            'SetlistEntryArtist' => [SetlistEntryArtist::class],
            'ArtistProviderId' => [ArtistProviderId::class],
            'VenueProviderId' => [VenueProviderId::class],
            'TourProviderId' => [TourProviderId::class],
            'SongProviderId' => [SongProviderId::class],
            'EventProviderReference' => [EventProviderReference::class],
            'SongLink' => [SongLink::class],
        ];
    }

    public function test_attendance_deletion_removes_owned_rows_and_preserves_other_attendance(): void
    {
        $a = Attendance::factory()->create();
        $other = Attendance::factory()->create(['event_id' => $a->event_id]);
        $member = $a->members()->sole();
        $seat = AttendanceMemberSeat::factory()->create(['attendance_member_id' => $member->id]);
        $invite = AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
        $link = AttendanceLink::factory()->create(['attendance_id' => $a->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id]);
        $credit = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        $song = $this->present($entry->song);
        $artist = $this->present($credit->artist);
        $event = $this->present($a->event);
        $a->delete();
        foreach ([$a, $member, $seat, $invite, $link, $entry, $credit] as $deleted) {
            $this->assertDatabaseMissing($deleted->getTable(), ['id' => $deleted->id]);
        }
        foreach ([$other, $song, $artist, $event] as $kept) {
            $this->assertDatabaseHas($kept->getTable(), ['id' => $kept->id]);
        }
    }

    public function test_usage_restricts_catalog_event_and_account_deletion(): void
    {
        $venue = Venue::factory()->create();
        $a = Attendance::factory()->create();
        $this->present($a->event)->update(['venue_id' => $venue->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id]);
        $credit = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        $tourLink = EventTour::factory()->create(['event_id' => $a->event_id]);
        foreach ([$venue, $this->present($a->event), $this->present($entry->song), $this->present($credit->artist), $this->present($tourLink->tour), $this->present($a->owner)] as $kept) {
            $this->assertDatabaseRejects(fn () => $kept->delete());
            $this->assertDatabaseHas($kept->getTable(), ['id' => $kept->id]);
        }
    }

    public function test_provider_metadata_and_owned_catalog_joins_do_not_block_deletion(): void
    {
        foreach ([
            [ArtistProviderId::class, 'artist'],
            [VenueProviderId::class, 'venue'],
            [TourProviderId::class, 'tour'],
            [SongProviderId::class, 'song'],
            [EventProviderReference::class, 'event'],
            [SongLink::class, 'song'],
        ] as [$class, $relation]) {
            $reference = Factory::factoryForModel($class)->createOne();
            $parent = $reference->getRelationValue($relation);
            if (! $parent instanceof Model) {
                $this->fail('The reference must belong to a parent model.');
            }
            $parent->delete();
            $this->assertDatabaseMissing($reference->getTable(), ['id' => $reference->id]);
        }
        $eventArtist = EventArtist::factory()->create();
        $eventTour = EventTour::factory()->create(['event_id' => $eventArtist->event_id]);
        $this->present($eventArtist->event)->delete();
        $this->assertDatabaseMissing('event_artists', ['id' => $eventArtist->id]);
        $this->assertDatabaseMissing('event_tours', ['id' => $eventTour->id]);
        $this->assertDatabaseHas('artists', ['id' => $eventArtist->artist_id]);
        $tourArtist = TourArtist::factory()->create();
        $this->present($tourArtist->tour)->delete();
        $this->assertDatabaseMissing('tour_artists', ['id' => $tourArtist->id]);
        $this->assertDatabaseHas('artists', ['id' => $tourArtist->artist_id]);
    }

    public function test_removing_an_ordinary_member_removes_only_their_seats(): void
    {
        $a = Attendance::factory()->create();
        $guest = AttendanceMember::factory()->create(['attendance_id' => $a->id]);
        $seat = AttendanceMemberSeat::factory()->create(['attendance_member_id' => $guest->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id]);
        $guest->delete();
        $this->assertDatabaseMissing('attendance_member_seats', ['id' => $seat->id]);
        $this->assertDatabaseHas('setlist_entries', ['id' => $entry->id]);
        expect($a->members()->count())->toBe(1);
    }

    public function test_declared_foreign_keys_are_real_database_constraints(): void
    {
        $expected = [
            'events' => [['venue_id']],
            'event_artists' => [['event_id'], ['artist_id']],
            'tour_artists' => [['tour_id'], ['artist_id']],
            'event_tours' => [['event_id'], ['tour_id']],
            'attendances' => [['event_id'], ['owner_user_id']],
            'attendance_members' => [['attendance_id', 'event_id'], ['user_id']],
            'attendance_member_seats' => [['attendance_member_id']],
            'attendance_invitations' => [['attendance_id'], ['inviter_user_id'], ['recipient_user_id']],
            'attendance_links' => [['attendance_id']],
            'setlist_entries' => [['attendance_id'], ['song_id']],
            'setlist_entry_artists' => [['setlist_entry_id'], ['artist_id']],
            'artist_provider_ids' => [['artist_id']], 'venue_provider_ids' => [['venue_id']],
            'tour_provider_ids' => [['tour_id']], 'song_provider_ids' => [['song_id']],
            'event_provider_references' => [['event_id']], 'song_links' => [['song_id']],
        ];
        foreach ($expected as $table => $columnSets) {
            $actual = array_column(Schema::getForeignKeys($table), 'columns');
            foreach ($columnSets as $columns) {
                expect($actual)->toContain($columns);
            }
        }
        $this->assertDatabaseRejects(fn () => SongProviderId::factory()->create(['song_id' => 2147483647]));
    }

    public function test_optional_seeder_creates_a_connected_graph(): void
    {
        $this->seed(LiveEventSeeder::class);
        expect(Attendance::count())->toBe(1);
        $a = Attendance::sole();
        expect($a->members()->count())->toBe(2)
            ->and($a->entries()->count())->toBe(3)
            ->and($a->entries()->where('is_tape', true)->count())->toBe(1)
            ->and($this->present($a->event)->providerReferences()->count())->toBe(1);
    }
}
