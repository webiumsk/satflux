<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\StoreChecklistController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreDashboardController;
use App\Http\Controllers\LightningAddressController;
use App\Http\Controllers\StoreSettingsController;
use App\Http\Controllers\WalletConnectionController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\EshopIntegrationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Middleware\AuditLog;
use App\Http\Middleware\EnsureStoreOwnership;
use App\Http\Middleware\EnsureSupportRole;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
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
        'version' => $version,
        'name' => 'UZOL21',
    ]);
});

// Debug route for local development - verify stores exist in DB
Route::get('/debug/stores', function (\Illuminate\Http\Request $request) {
    if (!app()->environment('local')) {
        abort(404);
    }
    
    if (!auth()->check()) {
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
        'has_api_key' => !empty($user->btcpay_api_key),
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
        'btcpay_stores' => is_array($btcpayStores) ? array_map(function($s) {
            return [
                'id' => $s['id'] ?? $s['storeId'] ?? null,
                'name' => $s['name'] ?? null,
            ];
        }, $btcpayStores) : [],
        'matching_stores' => $stores->filter(function($localStore) use ($btcpayStores) {
            if (!is_array($btcpayStores)) return false;
            $localBtcpayId = $localStore->btcpay_store_id;
            return collect($btcpayStores)->contains(function($bs) use ($localBtcpayId) {
                $btcpayStoreId = $bs['id'] ?? $bs['storeId'] ?? null;
                return $btcpayStoreId === $localBtcpayId;
            });
        })->map(function($store) {
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
    
    // Password reset
    Route::post('/auth/password/reset-link', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/auth/password/reset', [PasswordResetController::class, 'reset']);
    
    // Email verification
    Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);
    
    // LNURL-auth
    Route::post('/lnurl-auth/challenge', [LnurlAuthController::class, 'challenge']);
    Route::get('/lnurl-auth/verify', [LnurlAuthController::class, 'verify']);
    Route::post('/lnurl-auth/complete-registration', [LnurlAuthController::class, 'completeRegistration']);
    Route::post('/lnurl-auth/check-email', [LnurlAuthController::class, 'checkEmailExists']);
});

// Email verification (GET request from email link - no auth required, separate from main auth group)
Route::get('/auth/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['throttle:6,1'])
    ->name('verification.verify');

// LNURL-auth challenge status (separate rate limiter for polling - needs higher limit)
Route::get('/lnurl-auth/challenge-status/{k1}', [LnurlAuthController::class, 'challengeStatus'])
    ->middleware(['throttle:20,1']); // 20 requests per minute for polling

// Authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    // User/Account routes
    Route::get('/user', [AccountController::class, 'user']);
    Route::put('/user', [AccountController::class, 'updateProfile']);
    Route::put('/user/password', [AccountController::class, 'updatePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::post('/stores', [StoreController::class, 'store'])
        ->middleware(AuditLog::class . ':store.created');
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
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':store.updated']);

    // Store Logo
    Route::post('/stores/{store}/logo', [StoreController::class, 'uploadLogo'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':store.logo.uploaded']);
    Route::delete('/stores/{store}/logo', [StoreController::class, 'deleteLogo'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':store.logo.deleted']);

    // Delete Store
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':store.deleted']);

    // Invoices
    Route::get('/stores/{store}/invoices', [InvoiceController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}/invoices/export', [InvoiceController::class, 'exportCsv'])
        ->middleware(EnsureStoreOwnership::class);

    // Exports
    Route::get('/stores/{store}/exports', [ExportController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/exports', [ExportController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':export.created']);
    Route::get('/exports', [ExportController::class, 'all']);
    Route::get('/exports/{export}/download', [ExportController::class, 'download']);
    Route::post('/exports/{export}/retry', [ExportController::class, 'retry']);

    // Apps
    Route::get('/stores/{store}/apps', [AppController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/apps', [AppController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':app.created']);
    Route::get('/stores/{store}/apps/{app}', [AppController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::put('/stores/{store}/apps/{app}', [AppController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':app.updated']);
    Route::delete('/stores/{store}/apps/{app}', [AppController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':app.deleted']);

    // Lightning Addresses
    Route::get('/stores/{store}/lightning-addresses', [LightningAddressController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::get('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':lightning-address.created']);
    Route::put('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'update'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':lightning-address.updated']);
    Route::delete('/stores/{store}/lightning-addresses/{username}', [LightningAddressController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':lightning-address.deleted']);

    // Store API Keys
    Route::get('/stores/{store}/api-keys', [\App\Http\Controllers\StoreApiKeyController::class, 'index'])
        ->middleware(EnsureStoreOwnership::class);
    Route::post('/stores/{store}/api-keys', [\App\Http\Controllers\StoreApiKeyController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':api-key.created']);
    Route::get('/stores/{store}/api-keys/{apiKey}', [\App\Http\Controllers\StoreApiKeyController::class, 'show'])
        ->middleware(EnsureStoreOwnership::class);
    Route::delete('/stores/{store}/api-keys/{apiKey}', [\App\Http\Controllers\StoreApiKeyController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':api-key.deleted']);
    Route::post('/stores/{store}/api-keys/{apiKey}/regenerate', [\App\Http\Controllers\StoreApiKeyController::class, 'regenerate'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':api-key.regenerated']);
    Route::post('/stores/{store}/api-keys/token', [\App\Http\Controllers\StoreApiKeyController::class, 'generateToken'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':api-key.token.generated']);

    // Product Images
    Route::post('/stores/{store}/products/image', [\App\Http\Controllers\ProductImageController::class, 'upload'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':product.image.uploaded']);

    // Wallet Connections (Merchant)
    Route::get('/stores/{store}/wallet-connection', [WalletConnectionController::class, 'show'])
        ->middleware([EnsureStoreOwnership::class]);
    Route::post('/stores/{store}/wallet-connection', [WalletConnectionController::class, 'store'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':wallet_connection.created']);
    Route::post('/stores/{store}/wallet-connection/test', [WalletConnectionController::class, 'testConnection'])
        ->middleware([EnsureStoreOwnership::class]);
    Route::post('/stores/{store}/wallet-connection/configure', [WalletConnectionController::class, 'configureLightning'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':wallet_connection.configured']);
    Route::delete('/stores/{store}/wallet-connection', [WalletConnectionController::class, 'destroy'])
        ->middleware([EnsureStoreOwnership::class, AuditLog::class . ':wallet_connection.deleted']);

    // Support routes
    Route::middleware([EnsureSupportRole::class])->group(function () {
        Route::get('/support/wallet-connections', [WalletConnectionController::class, 'indexSupport']);
        Route::get('/support/count', [WalletConnectionController::class, 'getSupportCount']);
        Route::post('/support/wallet-connections/{connection}/reveal', [WalletConnectionController::class, 'reveal'])
            ->middleware(AuditLog::class . ':wallet_connection.revealed');
        Route::get('/support/wallet-connections/{connection}/btcpay-store-url', [WalletConnectionController::class, 'getBtcPayStoreUrl']);
        Route::put('/support/wallet-connections/{connection}/mark-connected', [WalletConnectionController::class, 'markConnected'])
            ->middleware(AuditLog::class . ':wallet_connection.marked_connected');
    });
});

// Subscription checkout (auth handled in controller based on feature flag)
Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
