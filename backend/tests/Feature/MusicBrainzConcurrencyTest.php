<?php

namespace Tests\Feature;

use App\Http\MusicBrainzSelection;
use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Song;
use App\Models\SongProviderId;
use App\Models\Tour;
use App\Models\TourProviderId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MusicBrainzConcurrencyTest extends TestCase
{
    public function test_two_processes_reuse_each_provider_identity_without_orphan_parents(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('Requires dedicated PostgreSQL and process support.');
        }
        $this->assertSame('spectacles_testing', DB::connection()->getDatabaseName());
        foreach (['artist', 'work', 'series'] as $kind) {
            $this->race($kind);
        }
    }

    private function race(string $kind): void
    {
        $mbid = (string) Str::uuid();
        $key = (string) Str::uuid();
        $entry = (string) Str::uuid();
        $label = 'Race '.$kind.' '.$mbid;
        $proof = app(MusicBrainzSelection::class)->issue($kind, $mbid);
        $concert = ['artists' => [], 'catalog' => ['artistIds' => [], 'tours' => []], 'setlist' => []];
        $pending = ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => []];
        if ($kind === 'artist') {
            $concert['artists'] = [$label];
            $concert['catalog']['artistIds'] = [null];
            $pending['artists'][$key] = ['name' => $label, 'type' => null, 'disambiguation' => null, 'selection' => $proof];
            $pending['lineup'][0] = $key;
        } elseif ($kind === 'work') {
            $concert['setlist'] = [['id' => $entry, 'type' => 'song', 'title' => $label, 'artist' => '', 'tape' => false]];
            $pending['songs'][$key] = ['title' => $label, 'disambiguation' => null, 'selection' => $proof];
            $pending['entries'][$entry] = ['song' => $key];
        } else {
            $concert['catalog']['tours'] = [['name' => $label, 'catalogId' => null]];
            $pending['tours'] = [$key => ['name' => $label, 'selection' => $proof]];
            $pending['tourChoices'] = ['0' => $key];
        }
        [$reference, $parent, $field] = match ($kind) {
            'artist' => [ArtistProviderId::class, Artist::class, 'name'],
            'work' => [SongProviderId::class, Song::class, 'title'],
            'series' => [TourProviderId::class, Tour::class, 'name'],
        };
        $directory = sys_get_temp_dir().'/spectacles-mb-race-'.Str::uuid();
        mkdir($directory, 0700);
        file_put_contents($directory.'/input.json', json_encode(['kind' => $kind, 'label' => $label, 'concert' => $concert, 'pendingCatalog' => $pending], JSON_THROW_ON_ERROR));
        $connection = config('database.connections.pgsql');
        $environment = [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => 'spectacles_testing', 'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password']];
        $processes = [];
        try {
            foreach (['a', 'b'] as $slot) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, __DIR__.'/musicbrainz-race-worker.php', $directory, $slot], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 10);
                stream_set_timeout($pipes[2], 10);
                $processes[] = [$process, $pipes];
            }
            $ids = [];
            foreach ($processes as $index => [$process, $pipes]) {
                $ids[] = trim(stream_get_contents($pipes[1]));
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                unset($processes[$index]);
                $this->assertSame(0, $code, 'Race worker failed; inspect isolated test environment.');
            }
            $this->assertFileExists($directory.'/a.ready');
            $this->assertFileExists($directory.'/b.ready');
            $this->assertTrue(Str::isUuid($ids[0]));
            $this->assertSame($ids[0], $ids[1]);
            $this->assertSame(1, $parent::query()->where($field, $label)->count());
            $this->assertSame(1, $reference::query()->where('provider', 'musicbrainz')->where('resource_type', $kind)->where('provider_resource_id', $mbid)->count());
        } finally {
            foreach ($processes as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                } foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                } if (is_resource($process)) {
                    proc_close($process);
                }
            }
            $reference::query()->where('provider', 'musicbrainz')->where('resource_type', $kind)->where('provider_resource_id', $mbid)->delete();
            $parent::query()->where($field, $label)->delete();
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            } rmdir($directory);
        }
    }
}
