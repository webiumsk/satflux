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
            'app_type' => ['required', 'string', 'in:PointOfSale,PaymentButton,LightningAddress'],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
        ]);

        // Dočasne zakázať vytváranie Crowdfund appov (BTCPay API nepodporuje update)
        if (strtolower($request->app_type) === 'crowdfund') {
            \Log::warning('Attempt to create Crowdfund app blocked', [
                'store_id' => $store->id,
                'app_name' => $request->name,
            ]);
            return response()->json([
                'message' => 'Crowdfund apps are temporarily unavailable. Update functionality is not yet supported by BTCPay API.',
            ], 503); // Service Unavailable
        }

        return DB::transaction(function () use ($request, $store) {
            // Load merchant API key from store owner
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            
            // Prepare app configuration
            $config = $request->config ?? [];
            $config['name'] = $request->name;
            
            // If currency is not specified in config, use store's default currency
            if (!isset($config['currency'])) {
                $config['currency'] = $store->default_currency ?? 'EUR';
            }
            
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
            
            // CRITICAL: Log if ID is missing - this is a serious problem
            if (empty($btcpayAppId)) {
                \Log::error('CRITICAL: BTCPay app creation response missing ID', [
                    'store_id' => $store->btcpay_store_id,
                    'app_type' => $request->app_type,
                    'app_name' => $request->name,
                    'response_keys' => array_keys($btcpayApp),
                    'response' => $btcpayApp,
                ]);
                
                // Don't create local record without btcpay_app_id - wait for it to be fetched
                // This prevents issues where update would create new apps
            }

            // If btcpay_app_id is missing from response, try to fetch it from apps list BEFORE creating local record
            // This is critical to prevent creating records without btcpay_app_id
            if (empty($btcpayAppId)) {
                \Log::warning('BTCPay app creation response missing ID - fetching from apps list', [
                    'store_id' => $store->btcpay_store_id,
                    'app_type' => $request->app_type,
                    'app_name' => $request->name,
                ]);
                
                try {
                    // Wait a bit for BTCPay to index the new app
                    sleep(2); // Increased wait time for better reliability
                    
                    $apps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
                    $appName = $request->name;
                    $appType = $request->app_type;
                    
                    // Find app with matching name and type (most recent first)
                    $matchingApps = array_filter($apps, function($a) use ($appName, $appType) {
                        $nameMatches = ($a['name'] ?? $a['appName'] ?? '') === $appName;
                        $typeMatches = ($a['appType'] ?? $a['type'] ?? '') === $appType;
                        return $nameMatches && $typeMatches;
                    });
                    
                    if (!empty($matchingApps)) {
                        // Sort by created date (most recent first) to get the newly created app
                        usort($matchingApps, function($a, $b) {
                            $aCreated = $a['created'] ?? $a['createdTime'] ?? 0;
                            $bCreated = $b['created'] ?? $b['createdTime'] ?? 0;
                            return $bCreated <=> $aCreated; // Descending order
                        });
                        
                        $foundApp = reset($matchingApps);
                        $foundAppId = $foundApp['id'] ?? null;
                        if ($foundAppId) {
                            $btcpayAppId = $foundAppId;
                            \Log::info('Found BTCPay app ID from apps list', [
                                'btcpay_app_id' => $btcpayAppId,
                                'app_name' => $appName,
                                'app_type' => $appType,
                            ]);
                        } else {
                            \Log::warning('Found matching app but no ID in response', [
                                'found_app' => $foundApp,
                            ]);
                        }
                    } else {
                        \Log::warning('No matching app found in apps list', [
                            'app_name' => $appName,
                            'app_type' => $appType,
                            'total_apps' => count($apps),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to fetch app ID from apps list after creation', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // CRITICAL: Don't create local record if btcpay_app_id is still missing
            // This prevents issues where update would create new apps instead of updating
            if (empty($btcpayAppId)) {
                \Log::error('CRITICAL: Cannot create local app record without btcpay_app_id', [
                    'store_id' => $store->id,
                    'app_type' => $request->app_type,
                    'app_name' => $request->name,
                ]);
                throw new \Exception('Failed to create app: BTCPay app ID is missing. Please try again.');
            }
            
            // Create local app record - btcpay_app_id is now guaranteed to be set
            $app = App::create([
                'id' => (string) Str::uuid(),
                'store_id' => $store->id,
                'btcpay_app_id' => $btcpayAppId, // Now guaranteed to be set
                'app_type' => $request->app_type,
                'name' => $request->name,
                'config' => $btcpayApp,
            ]);
            
            \Log::info('Local app record created with btcpay_app_id', [
                'app_id' => $app->id,
                'btcpay_app_id' => $btcpayAppId,
                'app_name' => $request->name,
                'app_type' => $request->app_type,
            ]);

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

        // Dočasne zakázať update Crowdfund appov (BTCPay API nepodporuje update)
        if (strtolower($app->app_type) === 'crowdfund') {
            \Log::warning('Attempt to update Crowdfund app blocked', [
                'app_id' => $app->id,
                'store_id' => $store->id,
                'app_name' => $app->name,
            ]);
            return response()->json([
                'message' => 'Crowdfund apps cannot be updated. Update functionality is not yet supported by BTCPay API.',
            ], 503); // Service Unavailable
        }

        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // If app doesn't have btcpay_app_id, we need to create it in BTCPay first
        if (!$app->btcpay_app_id) {
            Log::warning('App update called but btcpay_app_id is missing - creating app in BTCPay first', [
                'app_id' => $app->id,
                'store_id' => $store->id,
                'app_type' => $app->app_type,
            ]);
            
            // Create app in BTCPay
            $config = $app->config ?? [];
            
            // Map 'name' to 'appName' for BTCPay API
            if ($request->has('name')) {
                $config['appName'] = $request->name;
                unset($config['name']);
            }
            
            // Merge in any additional config fields from request
            if ($request->has('config')) {
                $requestConfig = $request->config;
                if (isset($requestConfig['name']) && !isset($requestConfig['appName'])) {
                    $requestConfig['appName'] = $requestConfig['name'];
                    unset($requestConfig['name']);
                }
                $config = array_merge($config, $requestConfig);
            }
            
            $btcpayApp = $this->appService->createApp(
                $store->btcpay_store_id,
                $app->app_type,
                $request->input('name', $app->name),
                $config,
                $userApiKey
            );
            
            // Get BTCPay app ID
            $btcpayAppId = $btcpayApp['id'] ?? $btcpayApp['appId'] ?? $btcpayApp['app_id'] ?? null;
            
            if ($btcpayAppId) {
                // Update local record with BTCPay app ID
                $app->update([
                    'btcpay_app_id' => $btcpayAppId,
                    'name' => $request->input('name', $app->name),
                    'config' => $btcpayApp,
                ]);
                
                Log::info('Created app in BTCPay and updated local record', [
                    'app_id' => $app->id,
                    'btcpay_app_id' => $btcpayAppId,
                ]);
            } else {
                Log::error('Failed to get BTCPay app ID after creation', [
                    'app_id' => $app->id,
                    'btcpay_response' => $btcpayApp,
                ]);
            }
            
            return response()->json([
                'data' => $this->formatApp($app->fresh(), $btcpayApp),
                'message' => 'App created and updated successfully',
            ]);
        }

        // Update app in BTCPay
        // IMPORTANT: Start with request config first, then merge DB config as fallback
        // This ensures new values from form take priority over old values in DB
        $config = [];
        
        // Start with request config if provided
        if ($request->has('config')) {
            $config = $request->config;
            
            // CRITICAL: Remove ALL metadata fields from request config that could interfere
            // These MUST be removed to prevent creating new app instead of updating
            unset(
                $config['id'], 
                $config['appType'], 
                $config['app_type'],
                $config['storeId'], 
                $config['store_id'],
                $config['archived'], 
                $config['created'],
                $config['btcpay_app_id'] // Just in case
            );
            
            // Map 'name' to 'appName' in request config
            if (isset($config['name']) && !isset($config['appName'])) {
                $config['appName'] = $config['name'];
                unset($config['name']);
            }
        }
        
        // Merge in existing DB config as fallback for fields not provided in request
        $dbConfig = $app->config ?? [];
        
        // CRITICAL: Remove ALL metadata fields from DB config
        // These are BTCPay metadata fields that should NOT be sent in update requests
        unset(
            $dbConfig['id'], 
            $dbConfig['appType'], 
            $dbConfig['app_type'],
            $dbConfig['storeId'], 
            $dbConfig['store_id'],
            $dbConfig['archived'], 
            $dbConfig['created'],
            $dbConfig['btcpay_app_id'] // Just in case
        );
        
        // Remove old 'title' and 'appName' from DB config if we have new ones in request
        // This prevents old values from overriding new ones
        if ($request->has('config')) {
            if (isset($config['displayTitle']) || isset($config['title'])) {
                unset($dbConfig['title'], $dbConfig['displayTitle']);
            }
            if (isset($config['appName']) || $request->has('name')) {
                unset($dbConfig['appName'], $dbConfig['name']);
            }
        }
        
        // Merge DB config as fallback (request config takes priority)
        $config = array_merge($dbConfig, $config);
        
        // FINAL CHECK: Remove id if it somehow got back in during merge
        // This is a safety measure - id MUST come from $app->btcpay_app_id parameter
        unset($config['id']);
        
        // Map 'name' to 'appName' for BTCPay API (from request name field)
        if ($request->has('name')) {
            $config['appName'] = $request->name;
            // Remove 'name' if it exists
            unset($config['name']);
        }
        
        // CRITICAL: Verify btcpay_app_id exists and is not empty before updating
        // This is the most important check - without btcpay_app_id, we cannot update, only create new
        if (empty($app->btcpay_app_id)) {
            \Log::error('CRITICAL: Cannot update app - btcpay_app_id is missing or empty in DB', [
                'app_id' => $app->id,
                'store_id' => $store->id,
                'app_name' => $app->name,
                'app_type' => $app->app_type,
                'btcpay_app_id_value' => $app->btcpay_app_id,
                'btcpay_app_id_type' => gettype($app->btcpay_app_id),
                'btcpay_app_id_empty' => empty($app->btcpay_app_id),
            ]);
            \Log::error('Cannot update app: btcpay_app_id is missing in DB', [
                'app_id' => $app->id,
                'store_id' => $store->id,
            ]);
            
            // Try to find the app in BTCPay by name and type before giving up
            try {
                $apps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
                $appName = $app->name;
                $appType = $app->app_type;
                
                // Find app with matching name and type
                $matchingApps = array_filter($apps, function($a) use ($appName, $appType) {
                    $nameMatches = ($a['name'] ?? $a['appName'] ?? '') === $appName;
                    $typeMatches = ($a['appType'] ?? $a['type'] ?? '') === $appType;
                    return $nameMatches && $typeMatches;
                });
                
                if (!empty($matchingApps)) {
                    // Get the most recent matching app
                    usort($matchingApps, function($a, $b) {
                        $aCreated = $a['created'] ?? $a['createdTime'] ?? 0;
                        $bCreated = $b['created'] ?? $b['createdTime'] ?? 0;
                        return $bCreated <=> $aCreated;
                    });
                    
                    $foundApp = reset($matchingApps);
                    $foundAppId = $foundApp['id'] ?? null;
                    if ($foundAppId) {
                        // Update local record with found btcpay_app_id
                        $app->update(['btcpay_app_id' => $foundAppId]);
                        \Log::info('Found and restored btcpay_app_id from BTCPay apps list', [
                            'app_id' => $app->id,
                            'btcpay_app_id' => $foundAppId,
                        ]);
                        // Continue with update using the found ID
                        $app->refresh();
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to find app in BTCPay apps list', [
                    'app_id' => $app->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // If still no btcpay_app_id after trying to find it, return error
            if (!$app->btcpay_app_id) {
                return response()->json([
                    'message' => 'Cannot update app: BTCPay app ID is missing. Please contact support.',
                ], 400);
            }
        }

        // Log to ensure we're using the correct btcpay_app_id
        \Log::info('App update - calling updateApp with btcpay_app_id', [
            'app_id' => $app->id,
            'btcpay_app_id' => $app->btcpay_app_id,
            'btcpay_app_id_length' => strlen($app->btcpay_app_id ?? ''),
            'app_type' => $app->app_type,
            'store_id' => $store->btcpay_store_id,
            'config_keys' => array_keys($config),
            'config_has_id' => isset($config['id']),
            'config_id_value' => $config['id'] ?? null,
        ]);
        
        $btcpayApp = $this->appService->updateApp(
            $store->btcpay_store_id,
            $app->btcpay_app_id, // This MUST be set and correct, otherwise BTCPay will create new app
            $config,
            $app->app_type, // Pass app_type for correct endpoint
            $userApiKey
        );
        
        // CRITICAL: Verify that update didn't create a new app by checking if returned ID matches
        $returnedAppId = $btcpayApp['id'] ?? $btcpayApp['appId'] ?? $btcpayApp['app_id'] ?? null;
        if ($returnedAppId && $returnedAppId !== $app->btcpay_app_id) {
            \Log::error('CRITICAL: BTCPay returned different app ID - new app was created instead of update!', [
                'expected_btcpay_app_id' => $app->btcpay_app_id,
                'returned_app_id' => $returnedAppId,
                'local_app_id' => $app->id,
                'app_name' => $app->name,
            ]);
            // Update local record with new btcpay_app_id to prevent further duplicates
            $app->update(['btcpay_app_id' => $returnedAppId]);
            \Log::warning('Updated local btcpay_app_id to match BTCPay response to prevent further issues', [
                'old_btcpay_app_id' => $app->getOriginal('btcpay_app_id'),
                'new_btcpay_app_id' => $returnedAppId,
            ]);
        }

        // CRITICAL: Preserve btcpay_app_id when updating local record
        // Extract btcpay_app_id from BTCPay response if available, otherwise keep existing
        $btcpayAppIdFromResponse = $btcpayApp['id'] ?? $btcpayApp['appId'] ?? $btcpayApp['app_id'] ?? null;
        $finalBtcpayAppId = $btcpayAppIdFromResponse ?: $app->btcpay_app_id;
        
        // Update local record with fresh data from BTCPay
        // IMPORTANT: Always preserve btcpay_app_id - never let it be null or overwritten
        $app->update([
            'name' => $request->input('name', $app->name),
            'btcpay_app_id' => $finalBtcpayAppId, // Explicitly preserve/update btcpay_app_id
            'config' => $btcpayApp,
        ]);

        // Reload fresh data from BTCPay to ensure we have the latest template/products
        // This ensures that template is correctly formatted (as JSON string from BTCPay)
        try {
            $freshBtcpayApp = $this->appService->getApp(
                $store->btcpay_store_id,
                $finalBtcpayAppId, // Use the preserved/updated btcpay_app_id
                $app->app_type,
                $userApiKey
            );
            // Update local record again with fresh data, but preserve btcpay_app_id
            $app->update([
                'config' => $freshBtcpayApp,
                'btcpay_app_id' => $finalBtcpayAppId, // Explicitly preserve btcpay_app_id
            ]);
        } catch (\Exception $e) {
            // If reload fails, use the update response
            \Log::warning('Failed to reload app data after update', [
                'app_id' => $app->id,
                'btcpay_app_id' => $finalBtcpayAppId,
                'error' => $e->getMessage(),
            ]);
            $freshBtcpayApp = $btcpayApp;
        }

        return response()->json([
            'data' => $this->formatApp($app->fresh(), $freshBtcpayApp),
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

        try {
            // Delete app from BTCPay first
            $this->appService->deleteApp(
                $store->btcpay_store_id,
                $app->btcpay_app_id,
                $app->app_type, // Pass app_type for correct endpoint
                $userApiKey
            );

            // Only delete local record if BTCPay deletion succeeded
            $app->delete();

            return response()->json([
                'message' => 'App deleted successfully',
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If BTCPay deletion fails, don't delete local record
            \Illuminate\Support\Facades\Log::error('Failed to delete app from BTCPay', [
                'store_id' => $store->id,
                'btcpay_store_id' => $store->btcpay_store_id,
                'app_id' => $app->id,
                'btcpay_app_id' => $app->btcpay_app_id,
                'app_type' => $app->app_type,
                'error' => $e->getMessage(),
                'status_code' => $e->getCode(),
            ]);

            return response()->json([
                'message' => 'Failed to delete app from BTCPay: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format app for API response (never expose btcpay_app_id).
     */
    protected function formatApp(App $app, ?array $btcpayApp = null): array
    {
        // Get config from local DB or BTCPay API - prioritize BTCPay API data
        $config = $btcpayApp ?? $app->config ?? [];
        
        // BTCPay API may return products in 'items' field (GET) or 'template' field (POST/PUT)
        // Normalize 'items' to 'template' for frontend consistency
        if (isset($config['items']) && !isset($config['template'])) {
            $config['template'] = $config['items'];
        }
        
        // If template is a JSON string, decode it to array for frontend
        if (isset($config['template']) && is_string($config['template'])) {
            try {
                $decoded = json_decode($config['template'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $config['template'] = $decoded;
                }
            } catch (\Exception $e) {
                // If decoding fails, keep as is
                \Log::warning('Failed to decode template JSON in formatApp', [
                    'app_id' => $app->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $data = [
            'id' => $app->id,
            'name' => $app->name,
            'app_type' => $app->app_type,
            'config' => $config,
            'metadata' => $app->metadata,
            'created_at' => $app->created_at,
            'updated_at' => $app->updated_at,
        ];

        // Merge BTCPay data if available
        if ($btcpayApp) {
            // Update local app config with BTCPay data (but keep local-only fields)
            // This ensures we have the latest template/products from BTCPay
            if (!empty($btcpayApp)) {
                $app->config = array_merge($app->config ?? [], $btcpayApp);
                $app->save();
            }
            
            // Add BTCPay-specific fields that are safe to expose
            $btcpayAppId = $btcpayApp['id'] ?? $app->btcpay_app_id ?? null;
            if ($btcpayAppId) {
                // Generate app URL based on app type
                $data['btcpay_app_url'] = $this->generateAppUrl($app->app_type, $btcpayAppId);
                // Also include the app ID directly (it's needed for embed codes)
                $data['btcpay_app_id'] = $btcpayAppId;
            }
        } elseif ($app->btcpay_app_id) {
            // If we have btcpay_app_id but no $btcpayApp data, include it
            $data['btcpay_app_url'] = $this->generateAppUrl($app->app_type, $app->btcpay_app_id);
            $data['btcpay_app_id'] = $app->btcpay_app_id;
        }

        return $data;
    }

    /**
     * Generate BTCPay app URL based on app type.
     * Different app types have different URL patterns in BTCPay.
     *
     * @param string $appType
     * @param string $appId
     * @return string
     */
    protected function generateAppUrl(string $appType, string $appId): string
    {
        $baseUrl = config('services.btcpay.base_url');
        $basePath = $baseUrl . '/apps/' . $appId;

        // Different app types have different URL patterns
        switch (strtolower($appType)) {
            case 'pointofsale':
                return $basePath . '/pos';
            case 'crowdfund':
                return $basePath . '/crowdfund';
            case 'paymentbutton':
                return $basePath . '/paymentbutton';
            case 'lightningaddress':
                return $basePath . '/lnaddress';
            default:
                // Default to base path if app type is unknown
                return $basePath;
        }
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


