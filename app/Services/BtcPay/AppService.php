<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Log;

class AppService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all apps for a store.
     * 
     * @param string $storeId BTCPay store ID
     * @param string|null $userApiKey User-level API key (optional, uses server-level if not provided)
     * @return array List of apps
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function listApps(string $storeId, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // Try both endpoints - user confirmed /stores/{storeId}/apps/create/{appType} works
            // So listing might be /stores/{storeId}/apps
            try {
                $apps = $this->client->get("/stores/{$storeId}/apps");
                Log::info('BTCPay apps list fetched successfully', [
                    'store_id' => $storeId,
                    'endpoint' => "/stores/{$storeId}/apps",
                    'apps_count' => is_array($apps) ? count($apps) : 0,
                ]);
                return $apps;
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                if ($e->getCode() === 404) {
                    // Fallback to /api/v1/stores/{storeId}/apps
                    Log::info('Trying alternative endpoint for apps list', [
                        'store_id' => $storeId,
                        'endpoint' => "/api/v1/stores/{$storeId}/apps",
                    ]);
                    $apps = $this->client->get("/api/v1/stores/{$storeId}/apps");
                    Log::info('BTCPay apps list fetched successfully', [
                        'store_id' => $storeId,
                        'endpoint' => "/api/v1/stores/{$storeId}/apps",
                        'apps_count' => is_array($apps) ? count($apps) : 0,
                    ]);
                    return $apps;
                }
                throw $e;
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::warning('BTCPay apps listing failed - endpoint may not exist', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            // Return empty array instead of throwing to allow app creation to continue
            return [];
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Create a new app for a store.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $appType App type (PointOfSale, Crowdfund, PaymentButton, LightningAddress)
     * @param array $config App-specific configuration
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Created app data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function createApp(string $storeId, string $appType, array $config, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // BTCPay Greenfield API endpoint: POST /api/v1/stores/{storeId}/apps/create/{appType}
            // appType is in URL path with exact case (PointOfSale, Crowdfund, PaymentButton)
            
            // Build request body - start with minimal required fields
            $requestBody = [];
            
            // According to BTCPay API docs, request body uses 'appName' field
            // Map 'name' from config to 'appName' for API
            if (isset($config['name'])) {
                $requestBody['appName'] = $config['name'];
            }
            
            // Include other config fields from docs (id, storeId, appType, etc.)
            // Filter out 'name' since we use 'appName', and 'appType' since it's in URL
            foreach ($config as $key => $value) {
                if ($key !== 'appType' && $key !== 'name') {
                    $requestBody[$key] = $value;
                }
            }
            
            // If no name provided, use default
            if (empty($requestBody['appName'])) {
                $requestBody['appName'] = 'Untitled App';
            }
            
            // Map app types to their endpoint paths
            // According to BTCPay API docs:
            // - PointOfSale -> POST /api/v1/stores/{storeId}/apps/pos
            // - Other types may follow similar pattern: /api/v1/stores/{storeId}/apps/{type}
            $appTypeLower = strtolower($appType);
            $appTypeMap = [
                'pointofsale' => 'pos',
                'crowdfund' => 'crowdfund',
                'paymentbutton' => 'paymentbutton',
            ];
            
            $endpointPath = $appTypeMap[$appTypeLower] ?? $appTypeLower;
            
            // Standard API format from BTCPay docs: POST /api/v1/stores/{storeId}/apps/{type}
            $endpoints = [
                "/api/v1/stores/{$storeId}/apps/{$endpointPath}", // Standard API format from docs
            ];
            
            $lastException = null;
            
            foreach ($endpoints as $endpoint) {
                try {
                    // Log what we're sending for debugging
                    Log::info('Trying BTCPay app creation endpoint', [
                        'store_id' => $storeId,
                        'app_type' => $appType,
                        'endpoint' => $endpoint,
                        'request_body' => $requestBody,
                    ]);
                    
                    // Use reflection to access performRequest directly so we can read Location header
                    $reflection = new \ReflectionClass($this->client);
                    $performRequestMethod = $reflection->getMethod('performRequest');
                    $performRequestMethod->setAccessible(true);
                    $responseObj = $performRequestMethod->invoke($this->client, 'POST', $endpoint, ['json' => $requestBody]);
                    
                    if (!$responseObj->successful()) {
                        // Let BtcPayClient handle the error by calling post normally
                        $response = $this->client->post($endpoint, $requestBody);
                    } else {
                        $response = $responseObj->json() ?? [];
                    }
                    
                    $locationHeader = $responseObj->successful() ? $responseObj->header('Location') : null;
                    
                    Log::info('BTCPay app creation response details', [
                        'store_id' => $storeId,
                        'app_type' => $appType,
                        'response_data' => $response,
                        'location_header' => $locationHeader,
                        'status_code' => $responseObj->status(),
                    ]);
                    
                    // If we have Location header, extract app ID from URL
                    if ($locationHeader && preg_match('#/apps/([^/]+)#', $locationHeader, $matches)) {
                        $appId = $matches[1];
                        Log::info('Extracted app ID from Location header', [
                            'store_id' => $storeId,
                            'app_id' => $appId,
                            'location' => $locationHeader,
                        ]);
                        return array_merge($response, ['id' => $appId]);
                    }
                    
                    // BTCPay may return empty array, but app ID might be in Location header
                    // Or we may need to fetch apps list to find the newly created one
                    // If response is empty, try to get app ID from Location header or fetch apps
                    if (empty($response)) {
                        // Try to get from headers (if BtcPayClient exposes response object)
                        // For now, we'll fetch apps list and find the most recent one with matching name
                        Log::info('BTCPay app creation returned empty response, fetching apps list', [
                            'store_id' => $storeId,
                            'app_type' => $appType,
                        ]);
                        
                        // Wait a bit longer for app to be created and indexed
                        usleep(1000000); // 1 second
                        
                        // Fetch apps list to find the newly created app
                        $apps = $this->listApps($storeId, $userApiKey);
                        $appName = $requestBody['name'] ?? 'New ' . $appType;
                        
                        // Find app with matching name and type (most recent)
                        $matchingApps = array_filter($apps, function($app) use ($appType, $appName) {
                            $appAppType = $app['appType'] ?? $app['type'] ?? null;
                            $nameMatches = ($app['name'] ?? '') === $appName;
                            $typeMatches = $appAppType === $appType || strtolower($appAppType ?? '') === strtolower($appType);
                            return $nameMatches && $typeMatches;
                        });
                        
                        if (!empty($matchingApps)) {
                            // Get the first matching app (should be the newly created one)
                            $createdApp = reset($matchingApps);
                            Log::info('Found newly created app in apps list', [
                                'store_id' => $storeId,
                                'app_id' => $createdApp['id'] ?? null,
                            ]);
                            return $createdApp;
                        }
                        
                        Log::warning('Could not find newly created app in apps list', [
                            'store_id' => $storeId,
                            'app_type' => $appType,
                            'app_name' => $appName,
                            'apps_count' => count($apps),
                        ]);
                    }
                    
                    // If successful, log the response
                    Log::info('BTCPay app creation successful', [
                        'store_id' => $storeId,
                        'app_type' => $appType,
                        'endpoint' => $endpoint,
                        'response_data' => $response,
                    ]);
                    
                    return $response;
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    $lastException = $e;
                    // If 404, try next endpoint
                    if ($e->getCode() === 404) {
                        Log::info('Endpoint returned 404, trying next', [
                            'endpoint' => $endpoint,
                            'next_endpoint_index' => array_search($endpoint, $endpoints) + 1,
                        ]);
                        continue;
                    }
                    // For other errors, throw immediately
                    throw $e;
                }
            }
            
            // If all endpoints failed with 404, throw last exception
            throw $lastException ?? new \App\Services\BtcPay\Exceptions\BtcPayException('All endpoints returned 404');
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay app creation failed', [
                'store_id' => $storeId,
                'app_type' => $appType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Get app details.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $appId BTCPay app ID
     * @param string|null $appType App type (PointOfSale, Crowdfund, etc.) - optional, will try to detect
     * @param string|null $userApiKey User-level API key (optional)
     * @return array App data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function getApp(string $storeId, string $appId, ?string $appType = null, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // Map app types to endpoint paths
            $appTypeMap = [
                'pointofsale' => 'pos',
                'crowdfund' => 'crowdfund',
                'paymentbutton' => 'paymentbutton',
            ];
            
            $endpoints = [];
            
            // If appType is provided, try app-specific endpoint first
            if ($appType) {
                $appTypeLower = strtolower($appType);
                $endpointPath = $appTypeMap[$appTypeLower] ?? $appTypeLower;
                $endpoints[] = "/api/v1/apps/{$endpointPath}/{$appId}";
            }
            
            // Fallback to store-based endpoint
            $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";
            
            // Try endpoints in order
            $lastException = null;
            foreach ($endpoints as $endpoint) {
                try {
                    return $this->client->get($endpoint);
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    $lastException = $e;
                    if ($e->getCode() === 404 && count($endpoints) > 1) {
                        // Try next endpoint
                        continue;
                    }
                    throw $e;
                }
            }
            
            throw $lastException ?? new \App\Services\BtcPay\Exceptions\BtcPayException('Failed to get app');
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay app retrieval failed', [
                'store_id' => $storeId,
                'app_id' => $appId,
                'app_type' => $appType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Update app settings.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $appId BTCPay app ID
     * @param array $config Updated configuration
     * @param string|null $appType App type (PointOfSale, Crowdfund, etc.) - optional, will try to detect
     * @param string|null $userApiKey User-level API key (optional)
     * @return array Updated app data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function updateApp(string $storeId, string $appId, array $config, ?string $appType = null, ?string $userApiKey = null): array
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // Map app types to endpoint paths
            $appTypeMap = [
                'pointofsale' => 'pos',
                'crowdfund' => 'crowdfund',
                'paymentbutton' => 'paymentbutton',
            ];
            
            $endpoints = [];
            
            // If appType is provided, try app-specific endpoint first
            if ($appType) {
                $appTypeLower = strtolower($appType);
                $endpointPath = $appTypeMap[$appTypeLower] ?? $appTypeLower;
                $endpoints[] = "/api/v1/apps/{$endpointPath}/{$appId}";
            }
            
            // Fallback to store-based endpoint
            $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";
            
            // Try endpoints in order
            $lastException = null;
            foreach ($endpoints as $endpoint) {
                try {
                    return $this->client->put($endpoint, $config);
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    $lastException = $e;
                    if ($e->getCode() === 404 && count($endpoints) > 1) {
                        // Try next endpoint
                        continue;
                    }
                    throw $e;
                }
            }
            
            throw $lastException ?? new \App\Services\BtcPay\Exceptions\BtcPayException('Failed to update app');
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay app update failed', [
                'store_id' => $storeId,
                'app_id' => $appId,
                'app_type' => $appType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * Delete an app.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $appId BTCPay app ID
     * @param string|null $appType App type (PointOfSale, Crowdfund, etc.) - optional, will try to detect
     * @param string|null $userApiKey User-level API key (optional)
     * @return bool True if deleted successfully
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function deleteApp(string $storeId, string $appId, ?string $appType = null, ?string $userApiKey = null): bool
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            // According to BTCPay API docs, delete endpoint is: DELETE /api/v1/apps/{appId}
            // Try simple /api/v1/apps/{appId} first (without appType in path)
            $endpoints = [
                "/api/v1/apps/{$appId}", // Standard delete endpoint from docs
            ];
            
            // If that doesn't work, try app-specific endpoint
            if ($appType) {
                $appTypeMap = [
                    'pointofsale' => 'pos',
                    'crowdfund' => 'crowdfund',
                    'paymentbutton' => 'paymentbutton',
                ];
                $appTypeLower = strtolower($appType);
                $endpointPath = $appTypeMap[$appTypeLower] ?? $appTypeLower;
                $endpoints[] = "/api/v1/apps/{$endpointPath}/{$appId}";
            }
            
            // Fallback to store-based endpoint
            $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";
            
            // Try endpoints in order
            $lastException = null;
            foreach ($endpoints as $endpoint) {
                try {
                    $this->client->delete($endpoint);
                    Log::info('BTCPay app deleted successfully', [
                        'store_id' => $storeId,
                        'app_id' => $appId,
                        'app_type' => $appType,
                        'endpoint' => $endpoint,
                    ]);
                    return true;
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    $lastException = $e;
                    // Try next endpoint if 404 or 405 (Method Not Allowed) and more endpoints available
                    if (($e->getCode() === 404 || $e->getCode() === 405) && count($endpoints) > 1) {
                        Log::info('Endpoint returned error, trying next', [
                            'endpoint' => $endpoint,
                            'error_code' => $e->getCode(),
                            'remaining_endpoints' => count($endpoints) - 1,
                        ]);
                        continue;
                    }
                    throw $e;
                }
            }
            
            throw $lastException ?? new \App\Services\BtcPay\Exceptions\BtcPayException('Failed to delete app');
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay app deletion failed', [
                'store_id' => $storeId,
                'app_id' => $appId,
                'app_type' => $appType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }
}


