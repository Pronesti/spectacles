<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
if (! $app instanceof Application) {
    throw new RuntimeException('The application bootstrap did not return an application.');
}
$app->make(Kernel::class)->bootstrap();
$database = getenv('DB_DATABASE');
if (DB::connection()->getDriverName() !== 'sqlite' || ! is_string($database) || DB::connection()->getDatabaseName() !== $database || config('cache.default') !== 'database') {
    exit(2);
}
$start = (float) getenv('RACE_START');
while (microtime(true) < $start) {
    usleep(1000);
}
$failures = 0;
$hits = (int) getenv('RACE_HITS');
for ($hit = 0; $hit < $hits; $hit++) {
    try {
        app(RateLimiter::class)->hit('race-limiter', 60);
    } catch (QueryException) {
        $failures++;
    }
}
fwrite(STDOUT, (string) $failures);
