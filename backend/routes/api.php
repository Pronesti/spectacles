<?php

declare(strict_types=1);

use App\Http\Controllers\Account\LoginController;
use App\Http\Controllers\Account\LogoutController;
use App\Http\Controllers\Account\RegisterController;
use App\Http\Controllers\Account\ShowProfileController;
use App\Http\Controllers\Account\UpdateProfileController;
use App\Http\Controllers\Artists\ListArtistsController;
use App\Http\Controllers\Artists\SearchMusicBrainzArtistsController;
use App\Http\Controllers\Attendances\DestroyAttendanceController;
use App\Http\Controllers\Attendances\ShowCollectionController;
use App\Http\Controllers\Attendances\StoreAttendanceController;
use App\Http\Controllers\Attendances\UpdateAttendanceController;
use App\Http\Controllers\Events\ListEventsController;
use App\Http\Controllers\Events\SearchSetlistFmPerformancesController;
use App\Http\Controllers\Events\ShowEventController;
use App\Http\Controllers\Events\ShowSetlistFmPerformanceController;
use App\Http\Controllers\Songs\ListSongsController;
use App\Http\Controllers\Songs\SearchMusicBrainzWorksController;
use App\Http\Controllers\Tours\ListToursController;
use App\Http\Controllers\Tours\SearchMusicBrainzToursController;
use App\Http\Controllers\Tours\SearchSetlistFmToursController;
use App\Http\Controllers\Venues\ConfirmVenueController;
use App\Http\Controllers\Venues\ListVenuesController;
use App\Http\Controllers\Venues\SearchMusicBrainzPlacesController;
use App\Http\Controllers\Venues\ShowCatalogVenueController;
use App\Http\Controllers\Venues\ShowMusicBrainzPlaceController;
use App\Http\Controllers\Venues\ShowSetlistFmVenueController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/health', fn (): array => ['status' => 'ok', 'service' => 'spectacles-api'])
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', RegisterController::class)->middleware('throttle:registration');
    Route::post('/auth/login', LoginController::class)->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/me', ShowProfileController::class);
        Route::patch('/me', UpdateProfileController::class);

        Route::get('/musicbrainz/artists', SearchMusicBrainzArtistsController::class)->middleware('throttle:musicbrainz');
        Route::get('/musicbrainz/works', SearchMusicBrainzWorksController::class)->middleware('throttle:musicbrainz');
        Route::get('/musicbrainz/tours', SearchMusicBrainzToursController::class)->middleware('throttle:musicbrainz');
        Route::middleware('throttle:catalog-providers')->group(function (): void {
            Route::get('/providers/setlistfm/tours', SearchSetlistFmToursController::class);
            Route::get('/providers/setlistfm/performances', SearchSetlistFmPerformancesController::class);
            Route::get('/providers/setlistfm/performances/{id}', ShowSetlistFmPerformanceController::class)->where('id', '[a-zA-Z0-9_-]{1,128}');
            Route::get('/providers/setlistfm/venues/{id}', ShowSetlistFmVenueController::class)->where('id', '[a-zA-Z0-9_-]{1,128}');
            Route::get('/providers/venues/{uuid}', ShowCatalogVenueController::class)->whereUuid('uuid');
            Route::post('/providers/venues/confirm', ConfirmVenueController::class);
            Route::get('/musicbrainz/places', SearchMusicBrainzPlacesController::class);
            Route::get('/musicbrainz/places/{mbid}', ShowMusicBrainzPlaceController::class)->whereUuid('mbid');
        });
        Route::get('/collection', ShowCollectionController::class);
        Route::post('/attendances', StoreAttendanceController::class);
        Route::patch('/attendances/{attendance}', UpdateAttendanceController::class)->whereUuid('attendance');
        Route::delete('/attendances/{attendance}', DestroyAttendanceController::class)->whereUuid('attendance');

        Route::get('/events', ListEventsController::class)->name('catalog.events.index');
        Route::get('/events/{event}', ShowEventController::class)->whereUuid('event')->name('catalog.events.show');
        Route::get('/artists', ListArtistsController::class)->name('catalog.artists.index');
        Route::get('/venues', ListVenuesController::class)->name('catalog.venues.index');
        Route::get('/songs', ListSongsController::class)->name('catalog.songs.index');
        Route::get('/tours', ListToursController::class)->name('catalog.tours.index');
    });
});
