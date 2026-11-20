<?php

use App\Http\CatalogDraftResolver;
use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingCatalogData;
use App\Models\Artist;
use App\Models\Song;
use App\Models\Tour;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Saloon\Config;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
if (! $app instanceof Application) {
    throw new RuntimeException('The application bootstrap did not return an application.');
}
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'pgsql'
    || DB::connection()->getDatabaseName() !== 'spectacles_testing') {
    exit(2);
}
Config::preventStrayRequests();
$arguments = $argv ?? [];
$directory = $arguments[1] ?? null;
$slot = $arguments[2] ?? null;
if (! is_string($directory) || ! is_string($slot)) {
    throw new RuntimeException('Usage: musicbrainz-race-worker.php <directory> <slot>.');
}
$json = file_get_contents($directory.'/input.json');
if ($json === false) {
    throw new RuntimeException('The race input could not be read.');
}
$input = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
if (! is_array($input) || ! is_string($input['label'] ?? null) || ! is_array($input['concert'] ?? null) || ! is_array($input['pendingCatalog'] ?? null)) {
    throw new RuntimeException('The race input is malformed.');
}
$kind = $input['kind'] ?? null;
if (! in_array($kind, ['artist', 'work', 'series'], true)) {
    throw new RuntimeException('The race input has an unknown kind.');
}
$label = $input['label'];
[$model, $labelField] = match ($kind) {
    'artist' => [Artist::class, 'name'], 'work' => [Song::class, 'title'], 'series' => [Tour::class, 'name']
};
$model::created(function (Artist|Song|Tour $parent) use ($directory, $slot, $label, $labelField): void {
    if ($parent->getAttribute($labelField) !== $label) {
        return;
    }
    touch($directory.'/'.$slot.'.ready');
    $deadline = microtime(true) + 5;
    while (count(readyMarkers($directory)) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race barrier timed out.');
        }
        usleep(10000);
    }
});
$saved = DB::transaction(fn (): ConcertDraftData => app(CatalogDraftResolver::class)
    ->resolve(ConcertDraftData::from($input['concert']), PendingCatalogData::from($input['pendingCatalog'])));
$id = match ($kind) {
    'artist' => $saved->catalog->artistIds[0], 'work' => $saved->setlist[0]->songCatalogId, 'series' => $saved->catalog->tours[0]->catalogId
};
if (! is_string($id)) {
    throw new RuntimeException('The resolved draft has no catalog identity.');
}
fwrite(STDOUT, $id);

/**
 * @return list<string>
 */
function readyMarkers(string $directory): array
{
    $markers = glob($directory.'/*.ready');
    if ($markers === false) {
        throw new RuntimeException('The race barrier could not be read.');
    }

    return $markers;
}
