<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventTour;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use App\Models\Tour;
use App\Models\TourArtist;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class FrontendQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Frontend QA samples are restricted to local and testing environments.');
        }
        $user = User::query()->where('email', 'frontend-qa@example.com')->firstOrFail();
        DB::transaction(function () use ($user): void {
            $today = Carbon::today('America/Argentina/Buenos_Aires');
            $artists = [];
            foreach (['Luz del Sur', 'Los Satélites', 'Björk, guest', 'Orquesta del Río', 'Same Name', 'Same Name', 'Las Noches', 'Teatro Abierto'] as $index => $name) {
                $artists[] = $this->record(Artist::class, "artist-$index", ['name' => $name, 'type' => 'group', 'disambiguation' => 'Fictional frontend QA artist '.($index + 1)]);
            }
            $venues = [];
            foreach ([
                ['Sala del Río (QA)', 'Buenos Aires', -34.587, -58.426],
                ['Teatro Aurora (QA)', 'Buenos Aires', -34.603, -58.383],
                ['Club Horizonte (QA)', 'Buenos Aires', -34.592, -58.438],
                ['Patio Abierto (QA)', 'Buenos Aires', -34.612, -58.405],
                ['Espacio sin ubicación (QA)', '', null, null],
                ['Sala Córdoba (QA)', 'Córdoba', -31.416, -64.183],
            ] as $index => [$name, $city, $lat, $lng]) {
                $venues[] = $this->record(Venue::class, "venue-$index", ['name' => $name, 'city' => $city ?: null, 'country_code' => 'AR', 'lat' => $lat, 'lng' => $lng, 'timezone' => $index === 4 ? null : 'America/Argentina/Buenos_Aires']);
            }
            $tours = [];
            foreach (['Noches del Sur (QA)', 'Gira Horizonte (QA)', 'Encuentros (QA)'] as $index => $name) {
                $tour = $this->record(Tour::class, "tour-$index", ['name' => $name]);
                $this->record(TourArtist::class, "tour-artist-$index", ['tour_id' => $tour->id, 'artist_id' => $artists[$index]->id]);
                $tours[] = $tour;
            }
            $songs = [];
            foreach (['Primera luz', 'Canción del río', 'Volver a empezar', 'Sin título', 'Sin título', 'Último tren'] as $index => $title) {
                $songs[] = $this->record(Song::class, "song-$index", ['title' => $title, 'disambiguation' => 'Fictional QA composition '.($index + 1)]);
            }
            $titles = ['Noche de apertura', 'Sesiones del río', 'Encuentro acústico', 'Festival de las luces', 'Último encore', 'Esta noche', 'Próxima estación', 'Horizonte en vivo', 'Noche sin mapa', 'Un mes por confirmar', 'Recuerdo de otro año', 'Fecha por confirmar'];
            foreach ($titles as $index => $title) {
                $date = $today->copy()->addDays([-240, -120, -45, -12, -1, 0, 7, 21, 45, 60, -400, 0][$index]);
                $event = $this->record(Event::class, "event-$index", [
                    'title' => $title.' (QA sample)', 'type' => $index === 3 ? 'festival' : 'concert',
                    'venue_id' => $index === 11 ? null : $venues[$index === 8 ? 4 : $index % count($venues)]->id,
                    'year' => $index === 11 ? null : $date->year,
                    'month' => $index >= 10 ? null : $date->month,
                    'day' => $index >= 9 ? null : $date->day,
                    'local_start_time' => $index >= 9 ? null : ($index === 5 ? '00:00:07' : '21:30:00'),
                    'timezone' => $index >= 9 ? null : 'America/Argentina/Buenos_Aires',
                ]);
                if ($index !== 11) {
                    $this->record(EventArtist::class, "lineup-$index", ['event_id' => $event->id, 'artist_id' => $artists[$index % count($artists)]->id, 'position' => 1]);
                }
                if ($index === 3) {
                    foreach ([4, 5] as $position => $artistIndex) {
                        $this->record(EventArtist::class, "festival-lineup-$position", ['event_id' => $event->id, 'artist_id' => $artists[$artistIndex]->id, 'position' => $position + 2]);
                    }
                }
                if ($index % 3 === 0) {
                    $this->record(EventTour::class, "event-tour-$index", ['event_id' => $event->id, 'tour_id' => $tours[intdiv($index, 3) % count($tours)]->id]);
                }
                if (AttendanceMember::query()->where('user_id', $user->id)->where('event_id', $event->id)->exists()) {
                    continue;
                }
                $attendance = $this->record(Attendance::class, "attendance-$index", ['event_id' => $event->id, 'owner_user_id' => $user->id, 'notes' => 'Fictional sample for frontend testing. '.($index < 5 ? 'A night to remember — gran sonido y buena compañía.' : 'Check the time and ticket details before going.')]);
                $this->record(AttendanceMember::class, "member-$index", ['attendance_id' => $attendance->id, 'event_id' => $event->id, 'user_id' => $user->id, 'interested' => $index % 3 !== 1, 'tickets_bought' => $index % 2 === 0]);
                if ($index < 5) {
                    foreach ([0, 1, null, 2, 0, 5] as $position => $songIndex) {
                        $entry = $this->record(SetlistEntry::class, "entry-$index-$position", ['attendance_id' => $attendance->id, 'position' => $position + 1, 'type' => $songIndex === null ? 'note' : 'song', 'song_id' => $songIndex === null ? null : $songs[$songIndex]->id, 'display_text' => $songIndex === null ? 'Encore — pausa breve' : $songs[$songIndex]->title, 'is_tape' => $position === 5]);
                        if ($songIndex !== null) {
                            $this->record(SetlistEntryArtist::class, "performer-$index-$position", ['setlist_entry_id' => $entry->id, 'artist_id' => $artists[$index % count($artists)]->id]);
                        }
                    }
                }
            }
        });
    }

    /** @param class-string<Model> $class
     * @param  array<string, mixed>  $attributes
     */
    private function record(string $class, string $key, array $attributes): Model
    {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'spectacles:frontend-qa:v1:'.$key)->toString();
        $existing = $class::query()->where('external_id', $uuid)->first();
        if ($existing !== null) {
            return $existing;
        }
        $model = new $class($attributes);
        $model->external_id = $uuid;
        $model->save();

        return $model;
    }
}
