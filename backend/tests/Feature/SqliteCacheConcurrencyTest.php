<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class SqliteCacheConcurrencyTest extends TestCase
{
    private const int WORKERS = 6;

    private const int HITS = 40;

    /**
     * Parallel requests share one rate limiter counter on the SQLite database cache store.
     * A deferred transaction that reads the counter and then upgrades to a write lock fails
     * at once with "database is locked", which turned MusicBrainz searches into 500s.
     */
    public function test_parallel_rate_limiter_hits_on_the_sqlite_cache_store_do_not_fail(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('Requires process support.');
        }
        $directory = sys_get_temp_dir().'/spectacles-sqlite-race-'.Str::uuid();
        mkdir($directory, 0700);
        $database = $directory.'/database.sqlite';
        $pdo = new \PDO('sqlite:'.$database);
        $pdo->exec('create table "cache" ("key" varchar not null primary key, "value" text not null, "expiration" integer not null)');
        unset($pdo);
        $environment = [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'sqlite', 'DB_URL' => '', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'database', 'RACE_START' => (string) (microtime(true) + 1.5), 'RACE_HITS' => (string) self::HITS];
        $processes = [];
        try {
            for ($worker = 0; $worker < self::WORKERS; $worker++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, __DIR__.'/sqlite-cache-race-worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
                if (! is_resource($process)) {
                    $this->fail('Could not start the race worker.');
                }
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            $failures = [];
            foreach ($processes as $index => [$process, $pipes]) {
                $output = trim((string) stream_get_contents($pipes[1]));
                $error = trim((string) stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                unset($processes[$index]);
                expect($code)->toBe(0, $error)->and($output)->toBe('0', 'Worker lock failures: '.$output);
                $failures[] = $output;
            }
            $counter = new \PDO('sqlite:'.$database);
            $value = $counter->query('select "value" from "cache" where "key" like \'%race-limiter\'')?->fetchColumn();
            expect($failures)->toHaveCount(self::WORKERS)
                ->and(is_string($value) ? unserialize($value) : null)->toBe(self::WORKERS * self::HITS);
        } finally {
            foreach ($processes as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_terminate($process);
                proc_close($process);
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
