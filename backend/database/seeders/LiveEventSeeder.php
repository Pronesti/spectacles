<?php

namespace Database\Seeders;

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
use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LiveEventSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $artist = Artist::factory()->create(['name' => 'Sample Artist', 'type' => 'group']);
            $venue = Venue::factory()->create(['name' => 'Sample Venue', 'country_code' => 'AR']);
            $tour = Tour::factory()->create(['name' => 'Sample Tour']);
            $song = Song::factory()->create(['title' => 'Sample Song']);
            $event = Event::factory()->create(['title' => 'Sample Concert', 'venue_id' => $venue->id, 'year' => 2026]);
            EventArtist::factory()->create(['event_id' => $event->id, 'artist_id' => $artist->id, 'position' => 1]);
            TourArtist::factory()->create(['tour_id' => $tour->id, 'artist_id' => $artist->id]);
            EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
            $a = Attendance::factory()->create(['event_id' => $event->id, 'notes' => 'Sample personal notes']);
            $guest = AttendanceMember::factory()->create(['attendance_id' => $a->id, 'interested' => true]);
            AttendanceMemberSeat::factory()->create(['attendance_member_id' => $guest->id, 'seat_label' => '12']);
            AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
            AttendanceLink::factory()->create(['attendance_id' => $a->id, 'type' => 'photo_album']);
            $live = SetlistEntry::factory()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 1]);
            SetlistEntryArtist::factory()->create(['setlist_entry_id' => $live->id, 'artist_id' => $artist->id]);
            SetlistEntry::factory()->tape()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 2]);
            SetlistEntry::factory()->note('Encore')->create(['attendance_id' => $a->id, 'position' => 3]);
            ArtistProviderId::factory()->create(['artist_id' => $artist->id]);
            VenueProviderId::factory()->create(['venue_id' => $venue->id]);
            TourProviderId::factory()->create(['tour_id' => $tour->id]);
            SongProviderId::factory()->create(['song_id' => $song->id]);
            EventProviderReference::factory()->create(['event_id' => $event->id]);
            SongLink::factory()->create(['song_id' => $song->id]);
        });
    }
}
