<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogProviderController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\MusicBrainzController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/health', fn (): array => ['status' => 'ok', 'service' => 'spectacles-api'])
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);

        Route::get('/musicbrainz/artists', [MusicBrainzController::class, 'artists'])->middleware('throttle:musicbrainz');
        Route::get('/musicbrainz/works', [MusicBrainzController::class, 'works'])->middleware('throttle:musicbrainz');
        Route::get('/musicbrainz/tours', [MusicBrainzController::class, 'tours'])->middleware('throttle:musicbrainz');
        Route::middleware('throttle:catalog-providers')->group(function (): void {
            Route::get('/providers/setlistfm/tours', [CatalogProviderController::class, 'tours']);
            Route::get('/providers/setlistfm/performances', [CatalogProviderController::class, 'performances']);
            Route::get('/providers/setlistfm/performances/{id}', [CatalogProviderController::class, 'preview'])->where('id', '[a-zA-Z0-9_-]{1,128}');
            Route::get('/providers/setlistfm/venues/{id}', [CatalogProviderController::class, 'venue'])->where('id', '[a-zA-Z0-9_-]{1,128}');
            Route::get('/providers/venues/{uuid}', [CatalogProviderController::class, 'catalogVenue'])->whereUuid('uuid');
            Route::post('/providers/venues/confirm', [CatalogProviderController::class, 'confirmVenue']);
            Route::get('/musicbrainz/places', [CatalogProviderController::class, 'places']);
            Route::get('/musicbrainz/places/{mbid}', [CatalogProviderController::class, 'place'])->whereUuid('mbid');
        });
        Route::get('/collection', [CollectionController::class, 'index']);
        Route::post('/attendances', [CollectionController::class, 'store']);
        Route::patch('/attendances/{attendance}', [CollectionController::class, 'update'])->whereUuid('attendance');
        Route::delete('/attendances/{attendance}', [CollectionController::class, 'destroy'])->whereUuid('attendance');

        Route::get('/events', [CatalogController::class, 'index'])->name('catalog.events.index');
        Route::get('/events/{event}', [CatalogController::class, 'show'])->whereUuid('event')->name('catalog.events.show');
        Route::get('/artists', [CatalogController::class, 'index'])->name('catalog.artists.index');
        Route::get('/venues', [CatalogController::class, 'index'])->name('catalog.venues.index');
        Route::get('/songs', [CatalogController::class, 'index'])->name('catalog.songs.index');
        Route::get('/tours', [CatalogController::class, 'index'])->name('catalog.tours.index');
    });
});
