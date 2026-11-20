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
        expect(DB::connection()->getDatabaseName())->toBe('spectacles_testing');
        foreach (['artist', 'work', 'series'] as $kind) {
            $this->race($kind);
        }
    }

    /**
     * @param  'artist'|'work'|'series'  $kind
     */
    private function race(string $kind): void
    {
        $mbid = (string) Str::uuid();
        $key = (string) Str::uuid();
        $entry = (string) Str::uuid();
        $label = 'Race '.$kind.' '.$mbid;
        $proof = app(MusicBrainzSelection::class)->issue($kind, $mbid);
        $concert = ['id' => (string) Str::uuid(), 'title' => $label, 'artists' => [], 'venueId' => null, 'date' => ['year' => 2026, 'month' => null, 'day' => null], 'time' => null, 'timezone' => null, 'interested' => false, 'ticketsBought' => true, 'notes' => null, 'catalog' => ['artistIds' => [], 'tours' => []], 'setlist' => []];
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
        $environment = [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => 'spectacles_testing', 'DB_HOST' => config()->string('database.connections.pgsql.host'), 'DB_PORT' => config()->string('database.connections.pgsql.port'), 'DB_USERNAME' => config()->string('database.connections.pgsql.username'), 'DB_PASSWORD' => config()->string('database.connections.pgsql.password')];
        $processes = [];
        try {
            foreach (['a', 'b'] as $slot) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, __DIR__.'/musicbrainz-race-worker.php', $directory, $slot], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
                if (! is_resource($process)) {
                    $this->fail('Could not start the race worker.');
                }
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
                expect($code)->toBe(0);
            }
            expect($directory.'/a.ready')->toBeFile()
                ->and($directory.'/b.ready')->toBeFile()
                ->and(Str::isUuid($ids[0]))->toBeTrue()
                ->and($ids[1])->toBe($ids[0])
                ->and($parent::query()->where($field, $label)->count())->toBe(1)
                ->and($reference::query()->where('provider', 'musicbrainz')->where('resource_type', $kind)->where($kind === 'series' ? 'external_provider_id' : 'provider_resource_id', $mbid)->count())->toBe(1);
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
            $reference::query()->where('provider', 'musicbrainz')->where('resource_type', $kind)->where($kind === 'series' ? 'external_provider_id' : 'provider_resource_id', $mbid)->delete();
            $parent::query()->where($field, $label)->delete();
            $files = glob($directory.'/*');
            if ($files === false) {
                $this->fail('Could not list the race directory.');
            }
            foreach ($files as $file) {
                unlink($file);
            } rmdir($directory);
        }
    }
}
