<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SetlistRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_song_occurrences_live_tape_and_multiple_performers(): void
    {
        $a = Attendance::factory()->create();
        $song = Song::factory()->create();
        $live = SetlistEntry::factory()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 1]);
        SetlistEntry::factory()->tape()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 2]);
        SetlistEntry::factory()->note('Encore')->create(['attendance_id' => $a->id, 'position' => 5]);
        SetlistEntry::factory()->unidentified('Unknown instrumental')->create(['attendance_id' => $a->id, 'position' => 8]);
        SetlistEntryArtist::factory()->count(2)->create(['setlist_entry_id' => $live->id]);
        $this->assertSame([1, 2, 5, 8], $a->entries()->pluck('position')->all());
        $this->assertSame(2, $live->artistLinks()->count());
        $this->assertSame(1, $song->entries()->where('type', 'song')->where('is_tape', false)->count());
        $this->assertSame(2, $song->entries()->count());
    }

    public function test_song_queries_find_covers_across_events_by_identity(): void
    {
        $song = Song::factory()->create();
        foreach ([Attendance::factory()->create(), Attendance::factory()->create()] as $a) {
            $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id, 'song_id' => $song->id]);
            SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        }
        $events = DB::table('setlist_entries')->join('attendances', 'attendances.id', '=', 'setlist_entries.attendance_id')
            ->where('setlist_entries.song_id', $song->id)->where('is_tape', false)
            ->distinct()->count('attendances.event_id');
        $this->assertSame(2, $events);
    }

    public function test_position_and_performer_pairs_have_database_uniqueness(): void
    {
        $entry = SetlistEntry::factory()->create();
        $row = $entry->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('setlist_entries')->insert($row));
        $link = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        $row = $link->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('setlist_entry_artists')->insert($row));
    }
}
