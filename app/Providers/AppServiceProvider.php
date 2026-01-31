<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
        // Custom route model binding for Store (UUID)
        Route::bind('store', function ($value) {
            return \App\Models\Store::where('id', $value)->firstOrFail();
        });

        // Custom route model binding for App (UUID)
        Route::bind('app', function ($value) {
            return \App\Models\App::where('id', $value)->firstOrFail();
        });

        // Custom route model binding for WalletConnection (UUID)
        Route::bind('connection', function ($value) {
            return \App\Models\WalletConnection::where('id', $value)->firstOrFail();
        });

        // Define rate limiters
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        // Separate limiter for password reset (so first attempt isn't throttled by other auth)
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}



