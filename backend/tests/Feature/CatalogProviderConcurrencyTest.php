<?php

namespace Tests\Feature;

use App\Http\CatalogProviderSelection;
use App\Http\Data\ProviderPerformanceInputData;
use App\Models\Artist;
use App\Models\Event;
use App\Models\EventProviderReference;
use App\Models\Tour;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogProviderConcurrencyTest extends TestCase
{
    public function test_two_processes_reuse_performance_without_orphan_graphs_and_conflict_atomically(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('Requires dedicated PostgreSQL spectacles_testing and process support.');
        }
        $this->assertSame('spectacles_testing', DB::connection()->getDatabaseName());
        $this->race(false);
        $this->race(true);
    }

    private function race(bool $conflict): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $suffix = strtolower(Str::random(12));
        $id = 'race'.$suffix;
        $artist = Artist::factory()->create(['name' => 'Race Artist '.$suffix]);
        $tour = Tour::factory()->create(['name' => 'Race Tour '.$suffix]);
        $venue = Venue::factory()->create(['name' => 'Race Venue '.$suffix]);
        $mbid = Str::uuid()->toString();
        $artist->providerIds()->create(['provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => $mbid]);
        $tour->providerIds()->create(['provider' => 'setlist_fm', 'resource_type' => 'tour', 'external_provider_id' => $suffix]);
        $venue->providerIds()->create(['provider' => 'setlist_fm', 'resource_type' => 'venue', 'provider_resource_id' => $suffix]);
        $group = $this->arrayAt($f, 'performance');
        data_set($group, 'ref.externalId', $id);
        data_set($group, 'url', 'https://www.setlist.fm/show/2026-10-30/race-'.$suffix.'.html?setlist='.$id);
        data_set($group, 'artist.ref.externalId', $mbid);
        data_set($group, 'artist.url', 'https://musicbrainz.org/artist/'.$mbid);
        data_set($group, 'tour.ref.externalId', $suffix);
        data_set($group, 'tour.url', 'https://www.setlist.fm/search?artist=4bd7afb6&tour='.$suffix);
        data_set($group, 'venue.ref.externalId', $suffix);
        data_set($group, 'venue.url', 'https://www.setlist.fm/venue/race-'.$suffix.'.html');
        $concert = ['id' => Str::uuid()->toString(), 'title' => 'Race Event '.$suffix, 'venueId' => null, 'interested' => false, 'ticketsBought' => true, 'notes' => null, 'setlist' => [], 'artists' => [$artist->name], 'catalog' => ['artistIds' => [$artist->external_id], 'tours' => [['name' => $tour->name, 'catalogId' => $tour->external_id]]], 'date' => $this->arrayAt($group, 'date'), 'time' => '', 'timezone' => null];
        $directory = sys_get_temp_dir().'/spectacles-provider-race-'.$suffix;
        mkdir($directory, 0700);
        file_put_contents($directory.'/input.json', json_encode(['concert' => $concert, 'venueUuid' => $venue->external_id, 'binding' => ['selection' => app(CatalogProviderSelection::class)->issue('performance', ProviderPerformanceInputData::from($group))], 'conflict' => $conflict], JSON_THROW_ON_ERROR));
        $environment = [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => 'spectacles_testing', 'DB_HOST' => config()->string('database.connections.pgsql.host'), 'DB_PORT' => config()->string('database.connections.pgsql.port'), 'DB_USERNAME' => config()->string('database.connections.pgsql.username'), 'DB_PASSWORD' => config()->string('database.connections.pgsql.password')];
        $processes = [];
        try {
            foreach (['a', 'b'] as $slot) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, __DIR__.'/catalog-provider-race-worker.php', $directory, $slot], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 15);
                stream_set_timeout($pipes[2], 15);
                $processes[] = [$process, $pipes];
            }
            $results = [];
            foreach ($processes as $index => [$process, $pipes]) {
                $results[] = trim(stream_get_contents($pipes[1]));
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                unset($processes[$index]);
                $this->assertSame(0, $code, 'Isolated race worker failed: '.substr($error, 0, 200));
            }
            $this->assertFileExists($directory.'/a.ready');
            $this->assertFileExists($directory.'/b.ready');
            if ($conflict) {
                $this->assertCount(1, array_filter($results, fn ($r) => $r === 'CONFLICT'));
                $this->assertCount(1, array_filter($results, fn ($r) => Str::isUuid($r)));
            } else {
                $this->assertTrue(Str::isUuid($results[0]));
                $this->assertSame($results[0], $results[1]);
            }
            $this->assertSame(1, EventProviderReference::where('provider', 'setlist_fm')->where('resource_type', 'setlist')->where('provider_resource_id', $id)->count());
            $this->assertSame(1, Event::where('venue_id', $venue->id)->count());
            $event = Event::where('venue_id', $venue->id)->firstOrFail();
            $this->assertSame(1, $event->artistLinks()->count());
            $this->assertSame(1, $event->tourLinks()->count());
        } finally {
            foreach ($processes as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($process)) {
                    proc_close($process);
                }
            }
            Event::where('venue_id', $venue->id)->delete();
            $artist->delete();
            $tour->delete();
            $venue->delete();
            $files = glob($directory.'/*');
            $this->assertNotFalse($files);
            foreach ($files as $file) {
                unlink($file);
            } rmdir($directory);
        }
    }
}
