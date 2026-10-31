<?php

namespace Tests\Feature;

use App\Http\CatalogProviderSelection;
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
        $artist->providerIds()->create(['provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => $f['mbid'] = Str::uuid()->toString()]);
        $tour->providerIds()->create(['provider' => 'setlist_fm', 'resource_type' => 'tour', 'external_provider_id' => $suffix]);
        $venue->providerIds()->create(['provider' => 'setlist_fm', 'resource_type' => 'venue', 'provider_resource_id' => $suffix]);
        $group = $f['performance'];
        $group['ref']['externalId'] = $id;
        $group['url'] = 'https://www.setlist.fm/show/2026-10-30/race-'.$suffix.'.html?setlist='.$id;
        $group['artist']['ref']['externalId'] = $f['mbid'];
        $group['artist']['url'] = 'https://musicbrainz.org/artist/'.$f['mbid'];
        $group['tour']['ref']['externalId'] = $suffix;
        $group['tour']['url'] = 'https://www.setlist.fm/search?artist=4bd7afb6&tour='.$suffix;
        $group['venue']['ref']['externalId'] = $suffix;
        $group['venue']['url'] = 'https://www.setlist.fm/venue/race-'.$suffix.'.html';
        $concert = ['title' => 'Race Event '.$suffix, 'artists' => [$artist->name], 'catalog' => ['artistIds' => [$artist->external_id], 'tours' => [['name' => $tour->name, 'catalogId' => $tour->external_id]]], 'date' => $group['date'], 'time' => '', 'timezone' => null];
        $directory = sys_get_temp_dir().'/spectacles-provider-race-'.$suffix;
        mkdir($directory, 0700);
        file_put_contents($directory.'/input.json', json_encode(['concert' => $concert, 'venueUuid' => $venue->external_id, 'binding' => ['selection' => app(CatalogProviderSelection::class)->issue('performance', $group)], 'conflict' => $conflict], JSON_THROW_ON_ERROR));
        $connection = config('database.connections.pgsql');
        $environment = [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => 'spectacles_testing', 'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password']];
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
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            } rmdir($directory);
        }
    }
}
