<?php

namespace App\Providers;

use App\Contracts\Invoicing\UsSalesTaxCalculator;
use App\Models\Store;
use App\Policies\StorePolicy;
use App\Services\Invoicing\UsSalesTax\StripeTaxUsSalesTaxCalculator;
use App\Services\Invoicing\UsSalesTax\UsSalesTaxCalculationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $this->app->singleton(UsSalesTaxCalculationService::class, function ($app) {
            return new UsSalesTaxCalculationService([
                $app->make(StripeTaxUsSalesTaxCalculator::class),
            ]);
        });

        $this->app->bind(UsSalesTaxCalculator::class, StripeTaxUsSalesTaxCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Authorization policies
        Gate::policy(Store::class, StorePolicy::class);

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

        Route::bind('company', function ($value) {
            return \App\Models\Company::where('id', $value)->firstOrFail();
        });

        Route::bind('businessDocument', function ($value, $route) {
            $company = $route->parameter('company');
            if ($company instanceof \App\Models\Company) {
                return \App\Models\BusinessDocument::where('id', $value)
                    ->where('company_id', $company->id)
                    ->firstOrFail();
            }

            return \App\Models\BusinessDocument::where('id', $value)->firstOrFail();
        });

        Route::bind('contact', function ($value, $route) {
            $company = $route->parameter('company');
            if ($company instanceof \App\Models\Company) {
                return \App\Models\CompanyContact::where('id', $value)
                    ->where('company_id', $company->id)
                    ->firstOrFail();
            }

            return \App\Models\CompanyContact::where('id', $value)->firstOrFail();
        });

        // Define rate limiters
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        // Separate limiter for password reset (so first attempt isn't throttled by other auth)
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        // Per-user limiter for authenticated API endpoints (avoids shared-IP throttling)
        RateLimiter::for('api-user', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by('user:'.$request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}
