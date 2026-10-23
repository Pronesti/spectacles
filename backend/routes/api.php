<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
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

        Route::get('/events', [CatalogController::class, 'index'])->name('catalog.events.index');
        Route::get('/events/{event}', [CatalogController::class, 'show'])->whereUuid('event')->name('catalog.events.show');
        Route::get('/artists', [CatalogController::class, 'index'])->name('catalog.artists.index');
        Route::get('/venues', [CatalogController::class, 'index'])->name('catalog.venues.index');
        Route::get('/songs', [CatalogController::class, 'index'])->name('catalog.songs.index');
        Route::get('/tours', [CatalogController::class, 'index'])->name('catalog.tours.index');
    });
});
