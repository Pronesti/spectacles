<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::getAccessTokenFromRequestUsing(fn (Request $request): ?string => null);
        RateLimiter::for('musicbrainz', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()->external_id));
        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');

            return [
                Limit::perMinute(20)->by($request->ip()),
                Limit::perMinute(5)->by((is_string($email) ? strtolower(trim($email)) : '').'|'.$request->ip()),
            ];
        });
    }
}
