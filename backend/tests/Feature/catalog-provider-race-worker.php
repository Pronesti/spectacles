<?php

use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingSelectionData;
use App\Http\EventDraftResolver;
use App\Models\EventProviderReference;
use App\Models\Venue;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Saloon\Config;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
if (! $app instanceof Application) {
    throw new UnexpectedValueException('bootstrap/app.php did not return the application.');
}
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'spectacles_testing') {
    exit(2);
}
Config::preventStrayRequests();
$arguments = $argv ?? null;
if (! is_array($arguments) || count($arguments) !== 3) {
    exit(2);
}
[, $directory, $slot] = $arguments;
if (! in_array($slot, ['a', 'b'], true) || ! is_dir($directory) || is_link($directory)) {
    exit(2);
}
$contents = file_get_contents($directory.'/input.json');
if ($contents === false) {
    throw new RuntimeException('The race input could not be read.');
}
$input = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
if (! is_array($input) || ! is_array($input['concert'] ?? null) || ! is_string($input['concert']['title'] ?? null)) {
    throw new UnexpectedValueException('The race input lacks a titled concert.');
}
EventProviderReference::creating(function (EventProviderReference $ref) use ($directory, $slot): void {
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
    $event = DB::transaction(fn () => app(EventDraftResolver::class)->resolve(ConcertDraftData::from($concert), Venue::where('external_id', $input['venueUuid'])->firstOrFail(), PendingSelectionData::from($input['binding'])));
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
