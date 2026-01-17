<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Store;
use App\Services\BtcPay\AppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AppController extends Controller
{
    protected AppService $appService;

    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
    }

    /**
     * List all apps for a store.
     */
    public function index(Request $request, Store $store)
    {
        // Get local apps first (they should always exist)
        $localApps = App::where('store_id', $store->id)
            ->get()
            ->keyBy('btcpay_app_id');
        
        // If we have local apps, return them (with BTCPay data if API available)
        if ($localApps->isNotEmpty()) {
            try {
                // Try to fetch BTCPay apps to merge with local data
                $userApiKey = $store->user->getBtcPayApiKeyOrFail();
                $btcpayApps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
                
                // Create a map of BTCPay apps by ID
                $btcpayAppsMap = collect($btcpayApps)->keyBy('id');
                
                // Merge local apps with BTCPay data
                $apps = $localApps->map(function ($localApp) use ($btcpayAppsMap) {
                    $btcpayApp = $btcpayAppsMap->get($localApp->btcpay_app_id);
                    return $this->formatApp($localApp, $btcpayApp);
                })->values();
                
                return response()->json(['data' => $apps]);
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                // If API fails, return local apps without BTCPay data
                Log::warning('BTCPay API failed when listing apps, using local apps only', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
                
                $apps = $localApps->map(function ($app) {
                    return $this->formatApp($app);
                })->values();
                
                return response()->json(['data' => $apps]);
            }
        }
        
        // If no local apps, try to fetch from BTCPay and create local records
        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $btcpayApps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
            
            if (empty($btcpayApps)) {
                // No apps in BTCPay either, return empty array
                return response()->json(['data' => []]);
            }
            
            // Create local app records for BTCPay apps
            $apps = collect($btcpayApps)->map(function ($btcpayApp) use ($store) {
                $btcpayAppId = $btcpayApp['id'] ?? null;
                if (!$btcpayAppId) {
                    return null;
                }
                
                $appType = $this->determineAppType($btcpayApp);
                $localApp = App::create([
                    'id' => (string) Str::uuid(),
                    'store_id' => $store->id,
                    'btcpay_app_id' => $btcpayAppId,
                    'app_type' => $appType,
                    'name' => $btcpayApp['appName'] ?? $btcpayApp['name'] ?? 'Untitled App',
                    'config' => $btcpayApp,
                ]);
                
                return $this->formatApp($localApp, $btcpayApp);
            })->filter()->values();
            
            return response()->json(['data' => $apps]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If API fails and no local apps, return empty
            Log::warning('BTCPay API failed when listing apps, no local apps found', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['data' => []]);
        }
    }

    /**
     * Create a new app.
     */
    public function store(Request $request, Store $store)
    {
        $request->validate([
            'app_type' => ['required', 'string', 'in:PointOfSale,Crowdfund,PaymentButton,LightningAddress'],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
        ]);

        return DB::transaction(function () use ($request, $store) {
            // Load merchant API key from store owner
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            
            // Prepare app configuration
            $config = $request->config ?? [];
            $config['name'] = $request->name;
            
            // Create app in BTCPay
            $btcpayApp = $this->appService->createApp(
                $store->btcpay_store_id,
                $request->app_type,
                $config,
                $userApiKey
            );

            // BTCPay may return app ID in different formats - check multiple possibilities
            $btcpayAppId = $btcpayApp['id'] 
                ?? $btcpayApp['appId'] 
                ?? $btcpayApp['app_id'] 
                ?? (is_string($btcpayApp) ? $btcpayApp : null)
                ?? null;
            
            // If app ID is not available, we'll create the record without it
            // and update it later when we can fetch the apps list
            if (!$btcpayAppId) {
                \Log::warning('BTCPay app creation response missing ID - creating record without btcpay_app_id', [
                    'store_id' => $store->btcpay_store_id,
                    'app_type' => $request->app_type,
                    'response' => $btcpayApp,
                ]);
                
                // Store will be created without btcpay_app_id - we'll need to update it later
                // This is a temporary workaround until we can reliably get the app ID
            }

            // Create local app record
            $app = App::create([
                'id' => (string) Str::uuid(),
                'store_id' => $store->id,
                'btcpay_app_id' => $btcpayAppId, // May be null if BTCPay didn't return ID
                'app_type' => $request->app_type,
                'name' => $request->name,
                'config' => $btcpayApp,
            ]);
            
            // If btcpay_app_id is missing, try to fetch it from apps list
            if (!$btcpayAppId) {
                try {
                    $apps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
                    $appName = $request->name;
                    
                    // Find app with matching name
                    $matchingApps = array_filter($apps, function($a) use ($appName) {
                        return ($a['name'] ?? '') === $appName;
                    });
                    
                    if (!empty($matchingApps)) {
                        $foundApp = reset($matchingApps);
                        $foundAppId = $foundApp['id'] ?? null;
                        if ($foundAppId) {
                            $app->update(['btcpay_app_id' => $foundAppId]);
                            \Log::info('Updated app with BTCPay app ID from apps list', [
                                'app_id' => $app->id,
                                'btcpay_app_id' => $foundAppId,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to fetch app ID from apps list after creation', [
                        'app_id' => $app->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'data' => $this->formatApp($app, $btcpayApp),
                'message' => 'App created successfully',
            ], 201);
        });
    }

    /**
     * Get a specific app.
     */
    public function show(Request $request, Store $store, App $app)
    {
        try {
            // Verify app belongs to store
            if ($app->store_id !== $store->id) {
                return response()->json([
                    'message' => 'App not found for this store.',
                ], 404);
            }

            // Load merchant API key from store owner
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $btcpayApp = $this->appService->getApp(
                $store->btcpay_store_id,
                $app->btcpay_app_id,
                $app->app_type, // Pass app_type for correct endpoint
                $userApiKey
            );

            return response()->json([
                'data' => $this->formatApp($app, $btcpayApp)
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If API fails, return app from local DB as fallback
            Log::warning('BTCPay API failed when loading app, using local fallback', [
                'app_id' => $app->id,
                'btcpay_app_id' => $app->btcpay_app_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['data' => $this->formatApp($app)]);
        }
    }

    /**
     * Update app settings.
     */
    public function update(Request $request, Store $store, App $app)
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
        ]);

        // Verify app belongs to store
        if ($app->store_id !== $store->id) {
            return response()->json([
                'message' => 'App not found for this store.',
            ], 404);
        }

        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Update app in BTCPay
        // Start with existing BTCPay config from local DB, or empty array
        $config = $app->config ?? [];
        
        // Map 'name' to 'appName' for BTCPay API
        if ($request->has('name')) {
            $config['appName'] = $request->name;
            // Remove 'name' if it exists
            unset($config['name']);
        }
        
        // Merge in any additional config fields from request
        if ($request->has('config')) {
            $requestConfig = $request->config;
            // Map 'name' to 'appName' in request config too
            if (isset($requestConfig['name']) && !isset($requestConfig['appName'])) {
                $requestConfig['appName'] = $requestConfig['name'];
                unset($requestConfig['name']);
            }
            $config = array_merge($config, $requestConfig);
        }

        $btcpayApp = $this->appService->updateApp(
            $store->btcpay_store_id,
            $app->btcpay_app_id,
            $config,
            $app->app_type, // Pass app_type for correct endpoint
            $userApiKey
        );

        // Update local record
        $app->update([
            'name' => $request->input('name', $app->name),
            'config' => $btcpayApp,
        ]);

        return response()->json([
            'data' => $this->formatApp($app->fresh(), $btcpayApp),
            'message' => 'App updated successfully',
        ]);
    }

    /**
     * Delete an app.
     */
    public function destroy(Request $request, Store $store, App $app)
    {
        // Verify app belongs to store
        if ($app->store_id !== $store->id) {
            return response()->json([
                'message' => 'App not found for this store.',
            ], 404);
        }

        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Delete app from BTCPay
        $this->appService->deleteApp(
            $store->btcpay_store_id,
            $app->btcpay_app_id,
            $app->app_type, // Pass app_type for correct endpoint
            $userApiKey
        );

        // Delete local record
        $app->delete();

        return response()->json([
            'message' => 'App deleted successfully',
        ]);
    }

    /**
     * Format app for API response (never expose btcpay_app_id).
     */
    protected function formatApp(App $app, ?array $btcpayApp = null): array
    {
        $data = [
            'id' => $app->id,
            'name' => $app->name,
            'app_type' => $app->app_type,
            'config' => $app->config,
            'metadata' => $app->metadata,
            'created_at' => $app->created_at,
            'updated_at' => $app->updated_at,
        ];

        // Merge BTCPay data if available
        if ($btcpayApp) {
            // Add BTCPay-specific fields that are safe to expose
            $data['btcpay_app_url'] = config('services.btcpay.base_url') . '/apps/' . ($btcpayApp['id'] ?? $app->btcpay_app_id);
        }

        return $data;
    }

    /**
     * Determine app type from BTCPay app data.
     */
    protected function determineAppType(array $btcpayApp): string
    {
        // Try to determine from BTCPay app data
        // This may need adjustment based on actual BTCPay API response structure
        if (isset($btcpayApp['appType'])) {
            return $btcpayApp['appType'];
        }
        
        // Default fallback
        return 'PointOfSale';
    }
}


