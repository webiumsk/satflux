<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DocumentationArticleController;
use App\Http\Controllers\Admin\DocumentationCategoryController;
use App\Http\Controllers\Admin\DocumentationImageController;
use App\Http\Controllers\Admin\FaqCategoryController;
use App\Http\Controllers\Admin\FaqItemController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NostrAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CashuController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EshopIntegrationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LightningAddressController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PosOrderController;
use App\Http\Controllers\PosTerminalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportSettingsController;
use App\Http\Controllers\SamRockController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StoreChecklistController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreDashboardController;
use App\Http\Controllers\StoreSettingsController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WalletConnectionController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\AuditLog;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureApiKeyLimit;
use App\Http\Middleware\EnsurePlanAllowsExportsAccess;
use App\Http\Middleware\EnsurePlanAllowsLnAddressCreation;
use App\Http\Middleware\EnsurePlanAllowsOfflinePaymentMethods;
use App\Http\Middleware\EnsurePlanAllowsStripe;
use App\Http\Middleware\EnsurePlanAllowsUserApiKeyCreation;
use App\Http\Middleware\EnsureStoreLimit;
use App\Http\Middleware\EnsureStoreOwnership;
use App\Http\Middleware\EnsureSupportOrAdminRole;
use App\Http\Middleware\EnsureSupportRole;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Locale endpoints (public - no auth required, but need session) (public - no auth required, but need session)
Route::middleware([\Illuminate\Session\Middleware\StartSession::class])->group(function () {
    Route::get('/locale', [LocaleController::class, 'index']);
    Route::post('/locale', [LocaleController::class, 'setLocale']);
});

// Public pricing (single source: config/pricing.php)
Route::get('/pricing', function () {
    $pricing = config('pricing', []);

    return response()->json([
        'free' => [
            'sats_per_year' => (int) ($pricing['free']['sats_per_year'] ?? 0),
        ],
        'pro' => [
            'sats_per_year' => (int) ($pricing['pro']['sats_per_year'] ?? 0),
            'sats_per_month_display' => (int) ($pricing['pro']['sats_per_month_display'] ?? 0),
        ],
    ]);
});

// Public plan features (single source: config/plans.php)
Route::get('/plan-features', function () {
    $plans = config('plans', []);

    return response()->json([
        'free' => ['feature_keys' => $plans['free']['feature_keys'] ?? []],
        'pro' => ['feature_keys' => $plans['pro']['feature_keys'] ?? []],
        'enterprise' => ['feature_keys' => $plans['enterprise']['feature_keys'] ?? []],
    ]);
});

// Version endpoint (public - no auth required)
Route::get('/version', function () {
    // Try to read version from package.json
    $packageJsonPath = base_path('package.json');
    $version = '1.0.0'; // Default fallback

    if (file_exists($packageJsonPath)) {
        try {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            if (isset($packageJson['version'])) {
                $version = $packageJson['version'];
            }
        } catch (\Exception $e) {
            // Fallback to default version
        }
    }

    return response()->json([
        'version' => $version.' (beta)',
        'name' => 'satflux.io',
    ]);
});

// Debug route for local development - verify stores exist in DB
Route::get('/debug/stores', function (\Illuminate\Http\Request $request) {
    if (! app()->environment('local')) {
        abort(404);
    }

    if (! auth()->check()) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }

    $user = auth()->user();

    $stores = \App\Models\Store::where('user_id', $user->id)
        ->with('user:id,email,btcpay_user_id')
        ->get();

    // Try to get BTCPay stores if API key exists
    $btcpayStores = [];
    $btcpayError = null;
    if ($user->btcpay_api_key) {
        try {
            $storeService = app(\App\Services\BtcPay\StoreService::class);
            $btcpayStores = $storeService->listStores($user->btcpay_api_key);
        } catch (\Exception $e) {
            $btcpayError = $e->getMessage();
        }
    }

    return response()->json([
        'user_id' => $user->id,
        'user_email' => $user->email,
        'btcpay_user_id' => $user->btcpay_user_id,
        'has_api_key' => ! empty($user->btcpay_api_key),
        'local_stores_count' => $stores->count(),
        'btcpay_stores_count' => is_array($btcpayStores) ? count($btcpayStores) : 0,
        'btcpay_error' => $btcpayError,
        'local_stores' => $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'btcpay_store_id' => $store->btcpay_store_id,
                'wallet_type' => $store->wallet_type,
                'created_at' => $store->created_at,
            ];
        }),
        'btcpay_stores' => is_array($btcpayStores) ? array_map(function ($s) {
            return [
                'id' => $s['id'] ?? $s['storeId'] ?? null,
                'name' => $s['name'] ?? null,
            ];
        }, $btcpayStores) : [],
        'matching_stores' => $stores->filter(function ($localStore) use ($btcpayStores) {
            if (! is_array($btcpayStores)) {
                return false;
            }
            $localBtcpayId = $localStore->btcpay_store_id;

            return collect($btcpayStores)->contains(function ($bs) use ($localBtcpayId) {
                $btcpayStoreId = $bs['id'] ?? $bs['storeId'] ?? null;

                return $btcpayStoreId === $localBtcpayId;
            });
        })->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'btcpay_store_id' => $store->btcpay_store_id,
            ];
        })->values(),
    ]);
})->middleware('auth:sanctum');

// Webhooks (no auth required - verified via signature)
Route::post('/webhooks/btcpay', [WebhookController::class, 'handle']);

// Public E-shop Integration API (rate limited, no auth required - uses tokens)
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/public/eshop/connect', [EshopIntegrationController::class, 'connect']);
    Route::get('/public/eshop/token/{token}', [EshopIntegrationController::class, 'getToken']);
});

// Authentication routes (rate limited)
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/auth/register', [RegisterController::class, 'register']);
    Route::post('/auth/login', [LoginController::class, 'login']);
    Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

    // Password reset: only in web.php (POST /api/auth/password/reset-link) so no Sanctum 401

    // Email verification
    Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);

    // LNURL-auth
    Route::post('/lnurl-auth/challenge', [LnurlAuthController::class, 'challenge']);
    Route::get('/lnurl-auth/verify', [LnurlAuthController::class, 'verify']);
    Route::post('/lnurl-auth/complete-registration', [LnurlAuthController::class, 'completeRegistration']);
    Route::post('/lnurl-auth/check-email', [LnurlAuthController::class, 'checkEmailExists']);

    // Nostr auth
    Route::post('/nostr-auth/challenge', [NostrAuthController::class, 'challenge']);
    Route::post('/nostr-auth/verify', [NostrAuthController::class, 'verify']);
    Route::post('/nostr-auth/complete-registration', [NostrAuthController::class, 'completeRegistration']);
    Route::post('/nostr-auth/check-email', [NostrAuthController::class, 'checkEmailExists']);
});

// Email verification (GET request from email link - no auth required, separate from main auth group)
Route::get('/auth/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['throttle:6,1'])
    ->name('verification.verify');

// LNURL-auth: public config (so frontend can always respect LNURL_AUTH_ENABLED without rebuild)
Route::get('/lnurl-auth/enabled', [LnurlAuthController::class, 'enabled']);
// LNURL-auth challenge status (polling every 1s = 60/min)
Route::get('/lnurl-auth/challenge-status/{k1}', [LnurlAuthController::class, 'challengeStatus'])
    ->middleware(['throttle:60,1']);
Route::get('/nostr-auth/enabled', [NostrAuthController::class, 'enabled']);
Route::get('/nostr-auth/challenge-status/{id}', [NostrAuthController::class, 'challengeStatus'])
    ->middleware(['throttle:60,1']);

// Authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    // User/Account routes
    Route::get('/user', [AccountController::class, 'user']);
    Route::post('/lnurl-auth/link-challenge', [LnurlAuthController::class, 'linkChallenge']);
    Route::post('/lnurl-auth/reveal-confirm-challenge', [LnurlAuthController::class, 'revealConfirmChallenge']);
    Route::post('/nostr-auth/link-challenge', [NostrAuthController::class, 'linkChallenge']);
    Route::post('/nostr-auth/reveal-confirm-challenge', [NostrAuthController::class, 'revealConfirmChallenge']);
    Route::get('/user/limits', [AccountController::class, 'limits']);
    Route::put('/user', [AccountController::class, 'updateProfile']);
    Route::put('/user/password', [AccountController::class, 'updatePassword']);

    // Panel API keys (for our API, not BTCPay)
    Route::get('/user/api-keys', [\App\Http\Controllers\UserApiKeyController::class, 'index']);
    Route::post('/user/api-keys', [\App\Http\Controllers\UserApiKeyController::class, 'store'])
        ->middleware(EnsurePlanAllowsUserApiKeyCreation::class);
    Route::delete('/user/api-keys/{user_api_key}', [\App\Http\Controllers\UserApiKeyController::class, 'destroy']);

    // Messages (notifications)
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/count', [MessageController::class, 'count']);
    Route::patch('/messages/{id}/read', [MessageController::class, 'markAsRead'])->where('id', '[a-zA-Z0-9_-]+');
    Route::post('/messages/mark-all-read', [MessageController::class, 'markAllAsRead']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::post('/stores', [StoreController::class, 'store'])
        ->middleware([EnsureStoreLimit::class, AuditLog::class.':store.created']);
    Route::patch('/stores/{store}/wallet-type', [StoreController::class, 'setWalletType'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}', [StoreController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);

    // Store Checklist
    Route::get('/stores/{store}/checklist', [StoreChecklistController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/checklist/{itemKey}', [StoreChecklistController::class, 'update'])
        ->middleware(EnsureStoreOwnership::class);

    // Store Dashboard
    Route::get('/stores/{store}/dashboard', [StoreDashboardController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);

    // Store Settings
    Route::get('/stores/{store}/settings', [StoreSettingsController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/settings', [StoreSettingsController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':store.updated']);

    // Cashu (wallet_type=cashu)
    Route::middleware([EnsureStoreOwnership::class])->prefix('stores/{store}/cashu')->group(function () {
        Route::get('settings', [CashuController::class, 'getSettings']);
        Route::put('settings', [CashuController::class, 'updateSettings']);

        Route::get('payments', [CashuController::class, 'listPayments']);
        Route::post('payments/{quoteId}/retry', [CashuController::class, 'retryPayment']);
    });

    // SamRock (Aqua + Boltz)
    Route::middleware([EnsureStoreOwnership::class])->prefix('stores/{store}/samrock')->group(function () {
        Route::post('otps', [SamRockController::class, 'createOtp']);
        Route::get('otps/{otp}', [SamRockController::class, 'getOtpStatus']);
        Route::get('otps/{otp}/qr', [SamRockController::class, 'getOtpQr']);
        Route::delete('otps/{otp}', [SamRockController::class, 'deleteOtp']);
        Route::post('complete', [SamRockController::class, 'complete']);
    });

    // Store Logo
    Route::post('/stores/{store}/logo', [StoreController::class, 'uploadLogo'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':store.logo.uploaded']);
    Route::delete('/stores/{store}/logo', [StoreController::class, 'deleteLogo'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':store.logo.deleted']);

    // Delete Store
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':store.deleted']);

    // Invoices
    Route::get('/stores/{store}/invoices', [InvoiceController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}/invoices/export', [InvoiceController::class, 'exportCsv'])
        ->middleware(EnsureStoreOwnership::class);

    // Reports (list visible to all; download/retry require Pro+ or admin/support)
    Route::get('/stores/{store}/exports', [ExportController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/exports', [ExportController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, EnsurePlanAllowsExportsAccess::class, AuditLog::class.':export.created']);
    Route::get('/exports', [ExportController::class, 'all']);
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])
        ->middleware(EnsurePlanAllowsExportsAccess::class);
    Route::post('/exports/{export}/retry', [ExportController::class, 'retry'])
        ->middleware(EnsurePlanAllowsExportsAccess::class);
    Route::delete('/exports/{export}', [ExportController::class, 'destroy'])
        ->middleware(EnsurePlanAllowsExportsAccess::class);

    // PDF report (Pro+ or admin/support only)
    Route::get('/stores/{store}/report/pdf', [ReportController::class, 'pdf'])
        ->middleware([EnsureStoreOwnership::class, EnsurePlanAllowsExportsAccess::class]);

    // Report settings (GET for all, PUT requires Pro+)
    Route::get('/stores/{store}/report-settings', [ReportSettingsController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/report-settings', [ReportSettingsController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, EnsurePlanAllowsExportsAccess::class]);

    // Apps
    Route::get('/stores/{store}/apps', [AppController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/apps', [AppController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':app.created']);
    Route::get('/stores/{store}/apps/{app}', [AppController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/apps/{app}', [AppController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':app.updated']);
    Route::delete('/stores/{store}/apps/{app}', [AppController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':app.deleted']);

    // Lightning Addresses
    Route::get('/stores/{store}/lightning-addresses', [LightningAddressController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, EnsurePlanAllowsLnAddressCreation::class, AuditLog::class.':lightning-address.created']);
    Route::put('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, EnsurePlanAllowsLnAddressCreation::class, AuditLog::class.':lightning-address.updated']);
    Route::delete('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':lightning-address.deleted']);

    // Stripe (Pro+ only)
    Route::middleware([EnsureStoreOwnership::class, EnsurePlanAllowsStripe::class])->group(function () {
        Route::get('/stores/{store}/stripe/settings', [StripeController::class, 'getSettings']);
        Route::put('/stores/{store}/stripe/settings', [StripeController::class, 'updateSettings']);
        Route::delete('/stores/{store}/stripe/settings', [StripeController::class, 'deleteSettings']);
        Route::post('/stores/{store}/stripe/test', [StripeController::class, 'testConnection']);
        Route::post('/stores/{store}/stripe/webhook/register', [StripeController::class, 'registerWebhook']);
        Route::get('/stores/{store}/stripe/webhook/status', [StripeController::class, 'getWebhookStatus']);
    });

    // Store API Keys
    Route::get('/stores/{store}/api-keys', [\App\Http\Controllers\StoreApiKeyController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/api-keys', [\App\Http\Controllers\StoreApiKeyController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, EnsureApiKeyLimit::class, AuditLog::class.':api-key.created']);
    Route::get('/stores/{store}/api-keys/{apiKey}', [\App\Http\Controllers\StoreApiKeyController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::delete('/stores/{store}/api-keys/{apiKey}', [\App\Http\Controllers\StoreApiKeyController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':api-key.deleted']);
    Route::post('/stores/{store}/api-keys/{apiKey}/regenerate', [\App\Http\Controllers\StoreApiKeyController::class, 'regenerate'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':api-key.regenerated']);
    Route::post('/stores/{store}/api-keys/token', [\App\Http\Controllers\StoreApiKeyController::class, 'generateToken'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':api-key.token.generated']);

    // Product Images
    Route::post('/stores/{store}/products/image', [\App\Http\Controllers\ProductImageController::class, 'upload'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':product.image.uploaded']);

    // Plans (public pricing)
    Route::get('/plans', [PlanController::class, 'index']);

    // Stats
    Route::get('/stores/{store}/stats', [StatsController::class, 'store'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stats/advanced', [StatsController::class, 'advanced']);

    // PoS terminals and orders
    Route::get('/stores/{store}/pos-terminals', [PosTerminalController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/pos-terminals', [PosTerminalController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':pos-terminal.created']);
    Route::put('/stores/{store}/pos-terminals/{pos_terminal}', [PosTerminalController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, EnsurePlanAllowsOfflinePaymentMethods::class, AuditLog::class.':pos-terminal.updated']);
    Route::delete('/stores/{store}/pos-terminals/{pos_terminal}', [PosTerminalController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':pos-terminal.deleted']);
    Route::get('/stores/{store}/pos-orders', [PosOrderController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/pos-orders', [PosOrderController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':pos-order.created']);

    // Wallet Connections (Merchant)
    Route::get('/stores/{store}/wallet-connection', [WalletConnectionController::class, 'show'])
        ->middleware([EnsureStoreOwnership::class]);
    Route::post('/stores/{store}/wallet-connection/reveal', [WalletConnectionController::class, 'revealForOwner'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':wallet_connection.revealed']);
    Route::post('/stores/{store}/wallet-connection', [WalletConnectionController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':wallet_connection.created']);
    Route::post('/stores/{store}/wallet-connection/check-duplicate', [WalletConnectionController::class, 'checkDuplicate'])
        ->middleware([EnsureStoreOwnership::class]);
    // Endpoint for checking duplicates when creating new stores (no store ID yet)
    Route::post('/wallet-connection/check-duplicate', [WalletConnectionController::class, 'checkDuplicateNew'])
        ->middleware('auth:sanctum');
    Route::post('/stores/{store}/wallet-connection/test', [WalletConnectionController::class, 'testConnection'])
        ->middleware([EnsureStoreOwnership::class]);
    Route::post('/stores/{store}/wallet-connection/configure', [WalletConnectionController::class, 'configureLightning'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':wallet_connection.configured']);
    Route::delete('/stores/{store}/wallet-connection', [WalletConnectionController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class.':wallet_connection.deleted']);

    // SatoshiTickets (Events, Ticket Types, Tickets, Orders)
    Route::prefix('stores/{store}/tickets')->middleware(EnsureStoreOwnership::class)->group(function () {
        Route::post('/events/image', [\App\Http\Controllers\TicketEventImageController::class, 'upload']);
        Route::post('/events/{eventId}/logo', [\App\Http\Controllers\TicketEventImageController::class, 'uploadLogo']);
        Route::delete('/events/{eventId}/logo', [\App\Http\Controllers\TicketEventImageController::class, 'deleteLogo']);
        Route::get('/events', [TicketController::class, 'listEvents']);
        Route::get('/events/{eventId}', [TicketController::class, 'getEvent']);
        Route::post('/events', [TicketController::class, 'createEvent']);
        Route::put('/events/{eventId}', [TicketController::class, 'updateEvent']);
        Route::delete('/events/{eventId}', [TicketController::class, 'deleteEvent']);
        Route::put('/events/{eventId}/toggle', [TicketController::class, 'toggleEvent']);
        Route::get('/events/{eventId}/ticket-types', [TicketController::class, 'listTicketTypes']);
        Route::post('/events/{eventId}/ticket-types', [TicketController::class, 'createTicketType']);
        Route::put('/events/{eventId}/ticket-types/{ticketTypeId}', [TicketController::class, 'updateTicketType']);
        Route::delete('/events/{eventId}/ticket-types/{ticketTypeId}', [TicketController::class, 'deleteTicketType']);
        Route::put('/events/{eventId}/ticket-types/{ticketTypeId}/toggle', [TicketController::class, 'toggleTicketType']);
        Route::get('/events/{eventId}/tickets', [TicketController::class, 'listTickets']);
        Route::post('/events/{eventId}/tickets/{ticketNumber}/check-in', [TicketController::class, 'checkInTicket']);
        Route::get('/events/{eventId}/orders', [TicketController::class, 'listOrders']);
        Route::post('/events/{eventId}/orders/{orderId}/tickets/{ticketId}/send-reminder', [TicketController::class, 'sendReminder']);
    });

    // Support routes
    Route::middleware([EnsureSupportRole::class])->group(function () {
        Route::get('/support/wallet-connections', [WalletConnectionController::class, 'indexSupport']);
        Route::get('/support/count', [WalletConnectionController::class, 'getSupportCount']);
        Route::post('/support/wallet-connections/{connection}/reveal', [WalletConnectionController::class, 'reveal'])
            ->middleware(AuditLog::class.':wallet_connection.revealed');
        Route::get('/support/wallet-connections/{connection}/btcpay-store-url', [WalletConnectionController::class, 'getBtcPayStoreUrl']);
        Route::put('/support/wallet-connections/{connection}/mark-connected', [WalletConnectionController::class, 'markConnected'])
            ->middleware(AuditLog::class.':wallet_connection.marked_connected');
        Route::post('/support/wallet-connections/{connection}/bot-failed', [WalletConnectionController::class, 'botFailed']);
    });

    // Admin routes
    Route::middleware([EnsureAdminRole::class])->group(function () {
        Route::get('/admin/stats', [AdminController::class, 'stats']);
        Route::get('/admin/stats/export', [AdminController::class, 'statsExport']);
        Route::get('/admin/users', [AdminController::class, 'index']);
        Route::get('/admin/users/{user}', [AdminController::class, 'show']);
        Route::put('/admin/users/{user}', [AdminController::class, 'update'])
            ->middleware(AuditLog::class.':admin.user.updated');
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroy'])
            ->middleware(AuditLog::class.':admin.user.deleted');
    });
});

// Subscription checkout (auth handled in controller based on feature flag)
Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
Route::get('/subscriptions/success', [SubscriptionController::class, 'success']);
Route::get('/subscriptions/details', [SubscriptionController::class, 'details'])->middleware('auth:sanctum');
Route::get('/subscriptions/credits', [SubscriptionController::class, 'getCredits'])->middleware('auth:sanctum');
Route::post('/subscriptions/credits', [SubscriptionController::class, 'addCredits'])->middleware('auth:sanctum');

// Documentation (public - no auth required)
Route::get('/documentation', [DocumentationController::class, 'index']);
Route::get('/documentation/{slug}', [DocumentationController::class, 'show']);

// FAQ (public - no auth required)
Route::get('/faq', [FaqController::class, 'index']);
Route::get('/faq/{slug}', [FaqController::class, 'show']);
Route::post('/faq/{slug}/helpful', [FaqController::class, 'markHelpful']);

// Public Ticket Check-In (no auth required — URL acts as the secret)
Route::middleware(['throttle:30,1'])->prefix('public/ticket-checkin')->group(function () {
    Route::get('/{store}/events/{eventId}', [TicketController::class, 'publicEventInfo']);
    Route::post('/{store}/events/{eventId}/tickets/{ticketNumber}/check-in', [TicketController::class, 'publicCheckIn']);
});

// Admin Documentation (requires support or admin role)
Route::middleware(['auth:sanctum', EnsureSupportOrAdminRole::class])->prefix('admin/documentation')->group(function () {
    // Articles
    Route::get('/articles', [DocumentationArticleController::class, 'index']);
    Route::post('/articles', [DocumentationArticleController::class, 'store'])
        ->middleware(AuditLog::class.':documentation_article.created');
    Route::get('/articles/{article}', [DocumentationArticleController::class, 'show']);
    Route::put('/articles/{article}', [DocumentationArticleController::class, 'update'])
        ->middleware(AuditLog::class.':documentation_article.updated');
    Route::delete('/articles/{article}', [DocumentationArticleController::class, 'destroy'])
        ->middleware(AuditLog::class.':documentation_article.deleted');
    Route::post('/upload-image', [DocumentationImageController::class, 'upload']);

    // Categories
    Route::get('/categories', [DocumentationCategoryController::class, 'index']);
    Route::post('/categories', [DocumentationCategoryController::class, 'store'])
        ->middleware(AuditLog::class.':documentation_category.created');
    Route::get('/categories/{category}', [DocumentationCategoryController::class, 'show']);
    Route::put('/categories/{category}', [DocumentationCategoryController::class, 'update'])
        ->middleware(AuditLog::class.':documentation_category.updated');
    Route::delete('/categories/{category}', [DocumentationCategoryController::class, 'destroy'])
        ->middleware(AuditLog::class.':documentation_category.deleted');
});

// Admin FAQ (requires support or admin role)
Route::middleware(['auth:sanctum', EnsureSupportOrAdminRole::class])->prefix('admin/faq')->group(function () {
    // Items
    Route::get('/items', [FaqItemController::class, 'index']);
    Route::post('/items', [FaqItemController::class, 'store'])
        ->middleware(AuditLog::class.':faq_item.created');
    Route::get('/items/{item}', [FaqItemController::class, 'show']);
    Route::put('/items/{item}', [FaqItemController::class, 'update'])
        ->middleware(AuditLog::class.':faq_item.updated');
    Route::delete('/items/{item}', [FaqItemController::class, 'destroy'])
        ->middleware(AuditLog::class.':faq_item.deleted');

    // Categories
    Route::get('/categories', [FaqCategoryController::class, 'index']);
    Route::post('/categories', [FaqCategoryController::class, 'store'])
        ->middleware(AuditLog::class.':faq_category.created');
    Route::get('/categories/{category}', [FaqCategoryController::class, 'show']);
    Route::put('/categories/{category}', [FaqCategoryController::class, 'update'])
        ->middleware(AuditLog::class.':faq_category.updated');
    Route::delete('/categories/{category}', [FaqCategoryController::class, 'destroy'])
        ->middleware(AuditLog::class.':faq_category.deleted');
});
