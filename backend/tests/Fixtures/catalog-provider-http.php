<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$database = (string) config('database.connections.sqlite.database');
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains($database, '/qa-output/catalog-providers-') || ! is_file($database)) {
    http_response_code(503);
    exit;
}
$state = json_decode((string) @file_get_contents(dirname($database).'/fixture-state.json'), true) ?: [];
if (isset($state['advanceHours']) && is_int($state['advanceHours']) && abs($state['advanceHours']) <= 48) {
    Carbon::setTestNow(now()->addHours($state['advanceHours']));
}
config([
    'session.lifetime' => 4320,
    'services.setlistfm.enabled' => true,
    'services.setlistfm.user_agent' => 'SpectaclesOfflineFixture/1.0',
    'services.setlistfm.html_daily_limit' => 10000,
    'services.setlistfm.html_interval_ms' => 0,
    'services.setlistfm.cache_seconds' => 0,
    'services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesOfflineFixture/1.0',
    'services.venue_images.enabled' => true, 'services.venue_images.wikidata_daily_limit' => 10000,
    'services.venue_images.commons_daily_limit' => 10000, 'services.venue_images.wikidata_interval_ms' => 0,
    'services.venue_images.commons_interval_ms' => 0, 'services.venue_images.cache_seconds' => 0,
]);
Http::preventStrayRequests();
if (($state['upstream503'] ?? false) === true) {
    Http::fake(fn () => Http::response(['message' => 'Fixture unavailable'], 503));
} else {
    Http::fake(require __DIR__.'/catalog-provider-http-responses.php');
}
$request = Request::capture();
$isSave = $request->isMethod('POST') && $request->path() === 'api/v1/attendances';
if ($isSave && ($state['failSave'] ?? false) === true) {
    $response = new JsonResponse(['message' => 'Fixture save failure'], 503);
} else {
    $response = $kernel->handle($request);
}
if ($isSave && ($state['dropSaveResponse'] ?? false) === true && $response->isSuccessful()) {
    $response = new JsonResponse(['message' => 'Fixture could not confirm response'], 503);
}
if ($isSave && (($state['failSave'] ?? false) || ($state['dropSaveResponse'] ?? false))) {
    $origin = $request->headers->get('Origin', '');
    if (preg_match('~^http://(?:localhost|127\.0\.0\.1):[0-9]+$~D', $origin)) {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Vary', 'Origin');
    }
}
$response->send();
$kernel->terminate($request, $response);
