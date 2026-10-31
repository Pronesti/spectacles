<?php

use App\Http\CatalogDraftResolver;
use App\Models\Artist;
use App\Models\Song;
use App\Models\Tour;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'pgsql'
    || DB::connection()->getDatabaseName() !== 'spectacles_testing') {
    exit(2);
}
Http::preventStrayRequests();
[$script, $directory, $slot] = $argv;
$input = json_decode(file_get_contents($directory.'/input.json'), true, 512, JSON_THROW_ON_ERROR);
[$model, $labelField] = match ($input['kind']) {
    'artist' => [Artist::class, 'name'], 'work' => [Song::class, 'title'], 'series' => [Tour::class, 'name']
};
$model::created(function (Artist|Song|Tour $parent) use ($directory, $slot, $input, $labelField): void {
    if ($parent->{$labelField} !== $input['label']) {
        return;
    }
    touch($directory.'/'.$slot.'.ready');
    $deadline = microtime(true) + 5;
    while (count(glob($directory.'/*.ready')) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race barrier timed out.');
        }
        usleep(10000);
    }
});
$saved = DB::transaction(fn (): array => app(CatalogDraftResolver::class)
    ->resolve($input['concert'], $input['pendingCatalog']));
fwrite(STDOUT, match ($input['kind']) {
    'artist' => $saved['catalog']['artistIds'][0], 'work' => $saved['setlist'][0]['songCatalogId'], 'series' => $saved['catalog']['tours'][0]['catalogId']
});
