<?php

use App\Http\EventDraftResolver;
use App\Models\EventProviderReference;
use App\Models\Venue;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'spectacles_testing') {
    exit(2);
}
Http::preventStrayRequests();
[$script, $directory, $slot] = $argv;
if (! in_array($slot, ['a', 'b'], true) || ! is_dir($directory) || is_link($directory)) {
    exit(2);
}
$input = json_decode(file_get_contents($directory.'/input.json'), true, 512, JSON_THROW_ON_ERROR);
EventProviderReference::creating(function ($ref) use ($directory, $slot): void {
    if ($ref->provider !== 'setlist_fm' || $ref->resource_type !== 'setlist') {
        return;
    }
    file_put_contents($directory.'/'.$slot.'.ready', 'ready');
    $other = $slot === 'a' ? 'b' : 'a';
    $deadline = microtime(true) + 5;
    while (! is_file($directory.'/'.$other.'.ready')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race insertion barrier timed out.');
        }
        usleep(10000);
    }
});
try {
    $concert = $input['concert'];
    if (($input['conflict'] ?? false) && $slot === 'b') {
        $concert['title'] .= ' differing title';
    }
    $event = DB::transaction(fn () => app(EventDraftResolver::class)->resolve($concert, Venue::where('external_id', $input['venueUuid'])->firstOrFail(), $input['binding']));
    fwrite(STDOUT, $event->external_id);
} catch (HttpException $e) {
    if ($e->getStatusCode() !== 409) {
        fwrite(STDERR, 'Unexpected HTTP failure');
        exit(1);
    }
    fwrite(STDOUT, 'CONFLICT');
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.substr($e->getMessage(), 0, 200));
    exit(1);
}
