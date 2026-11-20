<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Saloon\Config;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ProviderHttp;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
if (! $app instanceof Application) {
    throw new UnexpectedValueException('bootstrap/app.php did not return the application.');
}
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$database = config('database.connections.sqlite.database');
if (! is_string($database) || ! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains($database, '/qa-output/catalog-providers-') || ! is_file($database)) {
    http_response_code(503);
    exit;
}
$decodedState = json_decode((string) @file_get_contents(dirname($database).'/fixture-state.json'), true);
$state = is_array($decodedState) ? $decodedState : [];
if (isset($state['advanceHours']) && is_int($state['advanceHours']) && abs($state['advanceHours']) <= 48) {
    Carbon::setTestNow(now()->addHours($state['advanceHours']));
}
config([
    'session.lifetime' => 4320,
    'services.setlistfm.enabled' => true,
    'services.setlistfm.user_agent' => 'SpectaclesOfflineFixture/1.0',
    'services.setlistfm.cache_seconds' => 0,
    'services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesOfflineFixture/1.0',
    'services.venue_images.enabled' => true, 'services.venue_images.wikidata_daily_limit' => 10000,
    'services.venue_images.commons_daily_limit' => 10000, 'services.venue_images.wikidata_interval_ms' => 0,
    'services.venue_images.commons_interval_ms' => 0, 'services.venue_images.cache_seconds' => 0,
]);
Config::preventStrayRequests();
if (($state['upstream503'] ?? false) === true) {
    ProviderHttp::fake(fn (): MockResponse => ProviderHttp::response(['message' => 'Fixture unavailable'], 503));
} else {
    $responses = require __DIR__.'/catalog-provider-http-responses.php';
    if (! is_array($responses)) {
        throw new UnexpectedValueException('The catalog provider HTTP responses fixture must return an array.');
    }
    $stubs = [];
    foreach ($responses as $pattern => $response) {
        if (! is_string($pattern) || ! $response instanceof MockResponse) {
            throw new UnexpectedValueException('The catalog provider HTTP responses fixture must map URL patterns to mock responses.');
        }
        $stubs[$pattern] = $response;
    }
    ProviderHttp::fake($stubs);
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
    $origin = $request->headers->get('Origin') ?? '';
    if (preg_match('~^http://(?:localhost|127\.0\.0\.1):[0-9]+$~D', $origin)) {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Vary', 'Origin');
    }
}
$response->send();
/**
 * Terminate through the HTTP kernel contract, which accepts any Symfony response, including the fixture's substituted JSON failures.
 */
$terminate = static function (Kernel $kernel, Request $request, Response $response): void {
    $kernel->terminate($request, $response);
};
$terminate($kernel, $request, $response);
