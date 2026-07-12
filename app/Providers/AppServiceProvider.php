<?php

namespace App\Providers;

use App\Contracts\Invoicing\UsSalesTaxCalculator;
use App\Models\Store;
use App\Pdf\DomPdfDriver;
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

        $this->app->singleton('laravel-pdf.driver.dompdf', function () {
            return new DomPdfDriver(config('laravel-pdf.dompdf', []));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceProductionConfig();

        // Authorization policies
        Gate::policy(Store::class, StorePolicy::class);

        $this->registerRouteBindings();

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

    /**
     * Production boot guards (P1 phase 7): generated URLs are forced to
     * https, and missing security-critical configuration refuses to serve
     * HTTP traffic (fail-fast, same posture as the CSP guard). Console
     * commands only log - deploy tooling (key:generate, config:cache) must
     * keep working on a half-configured box.
     */
    protected function enforceProductionConfig(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        \Illuminate\Support\Facades\URL::forceScheme('https');

        $missing = \App\Support\ProductionConfigValidator::missing(fn (string $key) => config($key));
        if ($missing === []) {
            return;
        }

        \Illuminate\Support\Facades\Log::critical('Production configuration is incomplete', [
            'missing' => $missing,
        ]);

        if (! $this->app->runningInConsole()) {
            abort(500, 'Server configuration error.');
        }
    }

    /**
     * Route-model bindings. Nested resources are scoped to their route parent
     * (declarative IDOR protection) - a child belonging to a different
     * company/store/expense 404s at binding time. In-controller ownership
     * asserts stay as defense in depth.
     *
     * Deliberately unscoped: 'connection' (support routes only, role-gated),
     * integration-inbox '{inbox}' (no direct FK - linked via
     * store_integration_id, asserted in the inbox service) and '{export}'
     * (no route parent; ExportController keeps the 403 contract).
     */
    protected function registerRouteBindings(): void
    {
        // Root bindings (UUID lookup; ownership enforced by middleware)
        Route::bind('store', fn ($value) => \App\Models\Store::where('id', $value)->firstOrFail());
        Route::bind('company', fn ($value) => \App\Models\Company::where('id', $value)->firstOrFail());
        Route::bind('connection', fn ($value) => \App\Models\WalletConnection::where('id', $value)->firstOrFail());

        // Children scoped to {company}
        $this->bindScopedToParent('businessDocument', \App\Models\BusinessDocument::class, 'company', 'company_id');
        $this->bindScopedToParent('contact', \App\Models\CompanyContact::class, 'company', 'company_id');
        $this->bindScopedToParent('businessExpense', \App\Models\BusinessExpense::class, 'company', 'company_id');
        $this->bindScopedToParent('bankTransaction', \App\Models\BankTransaction::class, 'company', 'company_id');
        $this->bindScopedToParent('stockItem', \App\Models\CompanyStockItem::class, 'company', 'company_id');
        $this->bindScopedToParent('warehouse', \App\Models\CompanyWarehouse::class, 'company', 'company_id');
        $this->bindScopedToParent('recurringProfile', \App\Models\BusinessRecurringProfile::class, 'company', 'company_id');
        $this->bindScopedToParent('sequence', \App\Models\CompanyDocumentSequence::class, 'company', 'company_id');
        $this->bindScopedToParent('batch', \App\Models\BankImportBatch::class, 'company', 'company_id');

        // Children scoped to {store}
        $this->bindScopedToParent('app', \App\Models\App::class, 'store', 'store_id');
        $this->bindScopedToParent('apiKey', \App\Models\StoreApiKey::class, 'store', 'store_id');
        $this->bindScopedToParent('store_email_rule', \App\Models\StoreEmailRule::class, 'store', 'store_id');
        $this->bindScopedToParent('pos_terminal', \App\Models\PosTerminal::class, 'store', 'store_id');

        // Attachment scoped to {businessExpense}
        $this->bindScopedToParent('businessExpenseAttachment', \App\Models\BusinessExpenseAttachment::class, 'businessExpense', 'business_expense_id');

        // {export} stays unscoped on purpose: it has no route parent and the
        // API contract is 403 for foreign exports (ExportController checks).
    }

    /**
     * Bind a child route parameter scoped by the parent route parameter's id
     * when the parent is present on the route (parents resolve first - they
     * appear earlier in the URI).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function bindScopedToParent(string $param, string $modelClass, string $parentParam, string $foreignKey): void
    {
        Route::bind($param, function ($value, $route) use ($modelClass, $parentParam, $foreignKey) {
            $query = $modelClass::query()->where('id', $value);

            $parent = $route->parameter($parentParam);
            if ($parent instanceof \Illuminate\Database\Eloquent\Model) {
                $query->where($foreignKey, $parent->getKey());
            }

            return $query->firstOrFail();
        });
    }
}
