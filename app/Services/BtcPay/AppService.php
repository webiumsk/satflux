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
                        'response_has_id' => isset($response['id']),
                    ]);
                    
                    // CRITICAL: Always try to extract app ID from Location header first
                    // For crowdfunds, BTCPay often returns the ID only in Location header, not in response body
                    if ($locationHeader && preg_match('#/apps/([^/]+)#', $locationHeader, $matches)) {
                        $appId = $matches[1];
                        Log::info('Extracted app ID from Location header', [
                            'store_id' => $storeId,
                            'app_id' => $appId,
                            'location' => $locationHeader,
                        ]);
                        // Merge Location header ID into response (overwrites if response already has id)
                        $response['id'] = $appId;
                        return $response;
                    }
                    
                    // If no Location header but response has id, use it
                    if (isset($response['id']) && !empty($response['id'])) {
                        Log::info('Using app ID from response body', [
                            'store_id' => $storeId,
                            'app_id' => $response['id'],
                        ]);
                        return $response;
                    }
                    
                    // If response doesn't have ID (even if not empty), try to fetch from apps list
                    // This is important for crowdfunds where BTCPay might not return ID in response
                    if (empty($response) || !isset($response['id']) || empty($response['id'])) {
                        Log::info('BTCPay app creation response missing ID, fetching apps list', [
                            'store_id' => $storeId,
                            'app_type' => $appType,
                            'response_empty' => empty($response),
                            'response_has_id' => isset($response['id']),
                        ]);
                        
                        // Wait a bit for app to be created and indexed
                        usleep(1000000); // 1 second
                        
                        // Fetch apps list to find the newly created app
                        $apps = $this->listApps($storeId, $userApiKey);
                        $appName = $requestBody['appName'] ?? $requestBody['name'] ?? 'New ' . $appType;
                        
                        // Find app with matching name and type (most recent)
                        $matchingApps = array_filter($apps, function($app) use ($appType, $appName) {
                            $appAppType = $app['appType'] ?? $app['type'] ?? null;
                            $appNameFromApp = $app['name'] ?? $app['appName'] ?? '';
                            $nameMatches = $appNameFromApp === $appName;
                            $typeMatches = $appAppType === $appType || strtolower($appAppType ?? '') === strtolower($appType);
                            return $nameMatches && $typeMatches;
                        });
                        
                        if (!empty($matchingApps)) {
                            // Sort by created date (most recent first) to get the newly created app
                            usort($matchingApps, function($a, $b) {
                                $aCreated = $a['created'] ?? $a['createdTime'] ?? 0;
                                $bCreated = $b['created'] ?? $b['createdTime'] ?? 0;
                                return $bCreated <=> $aCreated; // Descending order
                            });
                            
                            $createdApp = reset($matchingApps);
                            $foundAppId = $createdApp['id'] ?? null;
                            
                            if ($foundAppId) {
                                Log::info('Found newly created app in apps list', [
                                    'store_id' => $storeId,
                                    'app_id' => $foundAppId,
                                    'app_name' => $appName,
                                ]);
                                // Merge found app data with original response (if any)
                                return array_merge($response ?? [], $createdApp);
                            }
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
            
            // For Crowdfund apps, BTCPay API doesn't support PUT - must use POST with id in body
            // POST /api/v1/stores/{storeId}/apps/crowdfund with id in body should update existing app
            // PUT endpoints return 404/405, so we can't use them
            if ($appType && strtolower($appType) === 'crowdfund') {
                // Use POST endpoint with id in body (BTCPay may update if id matches existing app)
                $endpoints[] = "/api/v1/stores/{$storeId}/apps/crowdfund";
            } else {
                // For other app types (PoS, etc.), try app-specific endpoint first
                if ($appType) {
                    $appTypeLower = strtolower($appType);
                    $endpointPath = $appTypeMap[$appTypeLower] ?? $appTypeLower;
                    $endpoints[] = "/api/v1/apps/{$endpointPath}/{$appId}";
                }
                
                // Fallback to store-based endpoint
                $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";
            }
            
            // Filter and map config to only include fields that BTCPay API accepts
            // According to BTCPay API docs and response structure:
            // - tipText (not tipsMessage)
            // - request (not requestCustomerData) - format: "email", "name", or "email,name"
            // - template must be valid JSON string or array (not double-encoded)
            
            $filteredConfig = [];
            
            // For Crowdfund, add id and appType to config (BTCPay uses create endpoint for updates too)
            // Note: appType should match the app type being created/updated
            // IMPORTANT: For updates, id must be present and correct, otherwise BTCPay will create a new app
            if ($appType && strtolower($appType) === 'crowdfund') {
                // CRITICAL: Remove id from config MULTIPLE TIMES to ensure it's gone
                // Config may contain old/wrong id from previous BTCPay responses or DB merge
                if (isset($config['id'])) {
                    Log::warning('Found id in config for Crowdfund update - removing it, will use appId parameter', [
                        'appId_parameter' => $appId,
                        'config_id' => $config['id'],
                    ]);
                    unset($config['id']); // Remove from config so it doesn't interfere
                }
                
                // Remove id from filteredConfig if it somehow got there
                unset($filteredConfig['id']);
                
                // Always use the $appId parameter (from method signature) for updates, NEVER from config
                // This prevents accidentally creating a new app if config contains wrong or missing id
                if ($appId) {
                    $filteredConfig['id'] = $appId;
                    // CRITICAL: Also add storeId to body (required by BTCPay API for POST requests)
                    $filteredConfig['storeId'] = $storeId;
                    Log::info('Setting Crowdfund id and storeId from parameters (required for POST update)', [
                        'id' => $appId,
                        'storeId' => $storeId,
                        'appId_parameter_type' => gettype($appId),
                        'appId_parameter_length' => strlen($appId),
                    ]);
                } else {
                    // No id provided - this will create a new app (OK for create, but should not happen for update)
                    Log::error('No appId parameter provided for Crowdfund update - this will create a new app instead of updating!', [
                        'appId_parameter' => $appId,
                        'appId_parameter_type' => gettype($appId),
                    ]);
                    throw new BtcPayException('Cannot update Crowdfund app: appId parameter is required. Without it, a new app would be created instead.', 400);
                }
                // CRITICAL: appType must be 'Crowdfund', not 'PointOfSale' (documentation has typo)
                $filteredConfig['appType'] = 'Crowdfund';
            }
            
            // Map our field names to BTCPay API field names based on app type
            $appTypeLower = strtolower($appType ?? '');
            
            if ($appTypeLower === 'crowdfund') {
                // Crowdfund-specific field mapping - use exact BTCPay API field names
                // Based on BTCPay API response structure
                
                // Direct mapping for fields that match BTCPay API exactly
                $directFields = [
                    'appName', 'displayTitle', 'tagline', 'description', 
                    'targetAmount', 'targetCurrency', 
                    'mainImageUrl', 'htmlLang', 'htmlMetaTags', 'notificationUrl',
                    'disqusShortname', 'resetEvery',
                    'displayPerksValue', 'displayPerksRanking', 'sortPerksByPopularity',
                    'animationColors', 'formId'
                ];
                
                // Handle sounds separately - only keep one sound URL
                if (isset($config['sounds'])) {
                    $sounds = $config['sounds'];
                    if (is_array($sounds) && !empty($sounds)) {
                        // Keep only the first sound (doublekill.wav)
                        $filteredSounds = array_filter($sounds, function($sound) {
                            return strpos($sound, 'doublekill.wav') !== false;
                        });
                        
                        // If doublekill.wav not found, use first sound
                        if (empty($filteredSounds)) {
                            $filteredConfig['sounds'] = [reset($sounds)];
                        } else {
                            $filteredConfig['sounds'] = [reset($filteredSounds)];
                        }
                    } else {
                        // If sounds is empty or not array, set default
                        $filteredConfig['sounds'] = ['https://github.com/ClaudiuHKS/AdvancedQuakeSounds/tree/master/sound/AQS/doublekill.wav'];
                    }
                } else {
                    // No sounds in config, set default
                    $filteredConfig['sounds'] = ['https://github.com/ClaudiuHKS/AdvancedQuakeSounds/tree/master/sound/AQS/doublekill.wav'];
                }
                
                // Handle date fields separately - they need to be integers (UNIX timestamps)
                if (isset($config['startDate']) && $config['startDate'] !== null && $config['startDate'] !== '') {
                    $startDate = $config['startDate'];
                    if (is_string($startDate)) {
                        // If it's a date string, convert to timestamp
                        $timestamp = strtotime($startDate);
                        if ($timestamp !== false) {
                            $filteredConfig['startDate'] = (int) $timestamp;
                        }
                    } elseif (is_numeric($startDate)) {
                        $filteredConfig['startDate'] = (int) $startDate;
                    }
                }
                
                if (isset($config['endDate']) && $config['endDate'] !== null && $config['endDate'] !== '') {
                    $endDate = $config['endDate'];
                    if (is_string($endDate)) {
                        // If it's a date string, convert to timestamp
                        $timestamp = strtotime($endDate);
                        if ($timestamp !== false) {
                            $filteredConfig['endDate'] = (int) $timestamp;
                        }
                    } elseif (is_numeric($endDate)) {
                        $filteredConfig['endDate'] = (int) $endDate;
                    }
                }
                
                // If resetEveryAmount is set (not 0), startDate is required by BTCPay API
                // Set default startDate to current time if not provided
                $hasResetEveryAmount = isset($filteredConfig['resetEveryAmount']) && $filteredConfig['resetEveryAmount'] != 0;
                if ($hasResetEveryAmount && !isset($filteredConfig['startDate'])) {
                    $filteredConfig['startDate'] = (int) time(); // Use current timestamp as default
                    Log::warning('startDate was required but missing, using current timestamp', [
                        'resetEveryAmount' => $filteredConfig['resetEveryAmount'] ?? null,
                    ]);
                }
                
                // Boolean fields that need explicit conversion
                $booleanFields = [
                    'enabled', 'enforceTargetAmount', 'soundsEnabled', 'animationsEnabled', 
                    'disqusEnabled', 'resetEveryAmount', 'displayPerksValue', 'displayPerksRanking', 
                    'sortPerksByPopularity'
                ];
                
                foreach ($directFields as $field) {
                    if (array_key_exists($field, $config)) {
                        $value = $config[$field];
                        if ($value !== null) {
                            $filteredConfig[$field] = $value;
                        }
                    }
                }
                
                // Handle boolean fields separately to ensure proper type
                foreach ($booleanFields as $field) {
                    if (array_key_exists($field, $config)) {
                        $value = $config[$field];
                        if ($value !== null) {
                            $filteredConfig[$field] = (bool) $value;
                        }
                    }
                }
                
                // Map our internal field names to BTCPay API field names
                $fieldMapping = [
                    'featuredImageUrl' => 'mainImageUrl',
                    'makePublic' => 'enabled',
                    'currency' => 'targetCurrency',
                    'enableSounds' => 'soundsEnabled',
                    'enableAnimations' => 'animationsEnabled',
                    'enableDiscussion' => 'disqusEnabled',
                    'callbackNotificationUrl' => 'notificationUrl',
                ];
                
                foreach ($fieldMapping as $ourField => $btcpayField) {
                    if (array_key_exists($ourField, $config) && !isset($filteredConfig[$btcpayField])) {
                        $value = $config[$ourField];
                        if ($value !== null) {
                            // Special handling for makePublic -> enabled
                            if ($ourField === 'makePublic') {
                                $filteredConfig[$btcpayField] = (bool) $value;
                            } else {
                                $filteredConfig[$btcpayField] = $value;
                            }
                        }
                    }
                }
                
                // Handle perks/items field - BTCPay expects 'perksTemplate' as JSON string (not array)
                $perksSource = $config['perks'] ?? $config['items'] ?? $config['template'] ?? null;
                if ($perksSource !== null && $perksSource !== '') {
                    if (is_array($perksSource)) {
                        // It's an array, encode it to JSON string
                        $filteredConfig['perksTemplate'] = json_encode($perksSource);
                    } elseif (is_string($perksSource)) {
                        // If it's a string, check if it's valid JSON
                        $decoded = json_decode($perksSource, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            // It's valid JSON string, use it as is
                            $filteredConfig['perksTemplate'] = $perksSource;
                        } else {
                            // Invalid JSON string, try to encode it as array
                            Log::warning('Invalid JSON in perks field, attempting to fix', [
                                'perks' => substr($perksSource, 0, 100),
                            ]);
                            $filteredConfig['perksTemplate'] = json_encode([]);
                        }
                    }
                } else {
                    // No perks specified, use empty JSON array string
                    $filteredConfig['perksTemplate'] = '[]';
                }
                
                // FINAL SAFETY CHECK: Ensure id is never overwritten by config values
                // This check runs after all config processing, right before sending to BTCPay
                if ($appType && strtolower($appType) === 'crowdfund' && $appId) {
                    // Force id to be correct - if it's wrong or missing, fix it
                    if (!isset($filteredConfig['id']) || $filteredConfig['id'] !== $appId) {
                        Log::error('CRITICAL: Crowdfund id was wrong or missing in filteredConfig - fixing it!', [
                            'appId_parameter' => $appId,
                            'filteredConfig_id' => $filteredConfig['id'] ?? 'MISSING',
                        ]);
                        $filteredConfig['id'] = $appId;
                    }
                }
                
                // Handle contributions settings - map to BTCPay field names
                if (isset($config['contributions']) && is_array($config['contributions'])) {
                    $contributions = $config['contributions'];
                    if (isset($contributions['sortByPopularity'])) {
                        $filteredConfig['sortPerksByPopularity'] = (bool) $contributions['sortByPopularity'];
                    }
                    if (isset($contributions['displayRanking'])) {
                        $filteredConfig['displayPerksRanking'] = (bool) $contributions['displayRanking'];
                    }
                    if (isset($contributions['displayValue'])) {
                        $filteredConfig['displayPerksValue'] = (bool) $contributions['displayValue'];
                    }
                }
                
                // Handle resetEveryAmount and resetEvery from root config or crowdfundBehavior
                // resetEveryAmount should be a number (0 or positive integer), not boolean
                $resetEveryAmount = null;
                if (isset($config['resetEveryAmount'])) {
                    $resetValue = $config['resetEveryAmount'];
                    // Convert to number: true/1 -> 1, false/0/string "0" -> 0
                    if (is_bool($resetValue)) {
                        $resetEveryAmount = $resetValue ? 1 : 0;
                    } elseif (is_numeric($resetValue)) {
                        $resetEveryAmount = (int) $resetValue;
                    } else {
                        $resetEveryAmount = 0;
                    }
                }
                
                // Get resetEvery from config (should be 'Day', 'Hour', 'Week', 'Month', 'Year', or 'Never')
                $resetEvery = null;
                if (isset($config['resetEvery'])) {
                    $resetEveryValue = $config['resetEvery'];
                    if (is_string($resetEveryValue) && in_array($resetEveryValue, ['Day', 'Hour', 'Week', 'Month', 'Year', 'Never'])) {
                        $resetEvery = $resetEveryValue;
                    }
                }
                
                // Handle crowdfund behavior - only set resetEvery if not already set and if countAllInvoices is set
                if ($resetEvery === null && isset($config['crowdfundBehavior']) && is_array($config['crowdfundBehavior'])) {
                    if (isset($config['crowdfundBehavior']['countAllInvoices'])) {
                        // If countAllInvoices is true, resetEvery should be 'Never', otherwise 'Day'
                        $resetEvery = (bool) $config['crowdfundBehavior']['countAllInvoices'] ? 'Never' : 'Day';
                    }
                }
                
                // Ensure consistency: if resetEveryAmount is 0, resetEvery must be 'Never'
                // If resetEveryAmount > 0, resetEvery cannot be 'Never'
                if ($resetEveryAmount !== null) {
                    if ($resetEveryAmount === 0) {
                        $resetEvery = 'Never';
                        $resetEveryAmount = 0;
                    } elseif ($resetEveryAmount > 0) {
                        // If resetEveryAmount > 0 but resetEvery is 'Never', default to 'Day'
                        if ($resetEvery === 'Never' || $resetEvery === null) {
                            $resetEvery = 'Day';
                        }
                        // Ensure minimum value of 1
                        if ($resetEveryAmount < 1) {
                            $resetEveryAmount = 1;
                        }
                    }
                } else {
                    // If resetEveryAmount is not set but resetEvery is set and not 'Never', default resetEveryAmount to 1
                    if ($resetEvery !== null && $resetEvery !== 'Never') {
                        $resetEveryAmount = 1;
                    } else {
                        $resetEveryAmount = 0;
                        $resetEvery = 'Never';
                    }
                }
                
                // Apply to filtered config
                $filteredConfig['resetEveryAmount'] = $resetEveryAmount;
                if ($resetEvery !== null) {
                    $filteredConfig['resetEvery'] = $resetEvery;
                }
                
                // If resetEveryAmount > 0 and resetEvery is not 'Never', ensure startDate is set
                if ($resetEveryAmount > 0 && $resetEvery !== 'Never' && $resetEvery !== null) {
                    if (!isset($filteredConfig['startDate']) || $filteredConfig['startDate'] === null) {
                        // Set default startDate to current timestamp if missing
                        $filteredConfig['startDate'] = now()->timestamp;
                        Log::info('Auto-setting startDate for recurring goal', [
                            'resetEveryAmount' => $resetEveryAmount,
                            'resetEvery' => $resetEvery,
                            'startDate' => $filteredConfig['startDate'],
                        ]);
                    }
                }
                
                // Handle checkout settings - BTCPay uses 'request' field (required, must be empty string, not null)
                // Set request field - use empty string if not specified, or 'email' if requested
                $filteredConfig['request'] = '';
                if (isset($config['checkout']) && is_array($config['checkout'])) {
                    if (isset($config['checkout']['requestContributorData']) && (bool) $config['checkout']['requestContributorData']) {
                        $filteredConfig['request'] = 'email';
                    }
                }
                
                // Ensure all boolean fields are actually boolean (not strings) at the end
                // Do this AFTER all other processing to ensure clean boolean values
                // NOTE: resetEveryAmount is NOT a boolean, it's a number (0 or 1)!
                $booleanFieldNames = ['enabled', 'enforceTargetAmount', 'soundsEnabled', 'animationsEnabled', 
                    'disqusEnabled', 'displayPerksValue', 'displayPerksRanking', 
                    'sortPerksByPopularity'];
                foreach ($booleanFieldNames as $field) {
                    if (array_key_exists($field, $filteredConfig)) {
                        // Get original value before conversion
                        $originalValue = $filteredConfig[$field];
                        // Remove the field first
                        unset($filteredConfig[$field]);
                        // Then set it as proper boolean
                        if (is_string($originalValue)) {
                            $filteredConfig[$field] = in_array(strtolower($originalValue), ['true', '1', 'yes'], true);
                        } else {
                            $filteredConfig[$field] = (bool) $originalValue;
                        }
                    }
                }
                
                // Convert resetEveryAmount to number (0 or 1), not boolean
                if (array_key_exists('resetEveryAmount', $filteredConfig)) {
                    $originalValue = $filteredConfig['resetEveryAmount'];
                    unset($filteredConfig['resetEveryAmount']);
                    // Convert to number: true/1 -> 1, false/0 -> 0
                    if (is_string($originalValue)) {
                        $filteredConfig['resetEveryAmount'] = in_array(strtolower($originalValue), ['true', '1', 'yes'], true) ? 1 : 0;
                    } else {
                        $filteredConfig['resetEveryAmount'] = (bool) $originalValue ? 1 : 0;
                    }
                }
                
                // Remove 'request' field if it exists (BTCPay uses 'formId' instead, or doesn't need it)
                unset($filteredConfig['request']);
                
                // Handle advanced settings
                if (isset($config['advanced']) && is_array($config['advanced'])) {
                    $advanced = $config['advanced'];
                    if (isset($advanced['htmlLanguage'])) {
                        $filteredConfig['htmlLang'] = $advanced['htmlLanguage'];
                    }
                    if (isset($advanced['htmlMetaTags'])) {
                        $filteredConfig['htmlMetaTags'] = $advanced['htmlMetaTags'];
                    }
                    if (isset($advanced['enableSounds'])) {
                        $filteredConfig['soundsEnabled'] = (bool) $advanced['enableSounds'];
                    }
                    if (isset($advanced['enableAnimations'])) {
                        $filteredConfig['animationsEnabled'] = (bool) $advanced['enableAnimations'];
                    }
                    if (isset($advanced['enableDiscussion'])) {
                        $filteredConfig['disqusEnabled'] = (bool) $advanced['enableDiscussion'];
                    }
                    if (isset($advanced['callbackNotificationUrl'])) {
                        $filteredConfig['notificationUrl'] = $advanced['callbackNotificationUrl'];
                    }
                }
            } else {
                // PointOfSale and other app types - original mapping
                $fieldMapping = [
                    'appName' => 'appName',
                    'title' => 'title',
                    'description' => 'description',
                    'defaultView' => 'defaultView',
                    'currency' => 'currency',
                    'showItems' => 'showItems',
                    'showCustomAmount' => 'showCustomAmount',
                    'showDiscount' => 'showDiscount',
                    'showSearch' => 'showSearch',
                    'showCategories' => 'showCategories',
                    'enableTips' => 'enableTips',
                    'tipsMessage' => 'tipText', // BTCPay uses 'tipText'
                    'defaultTaxRate' => 'defaultTaxRate', // Keep as is for now
                    'fixedAmountPayButtonText' => 'fixedAmountPayButtonText',
                    'customAmountPayButtonText' => 'customAmountPayButtonText',
                    'htmlLang' => 'htmlLang',
                    'htmlMetaTags' => 'htmlMetaTags',
                    'redirectUrl' => 'redirectUrl',
                    'redirectAutomatically' => 'redirectAutomatically',
                    'notificationUrl' => 'notificationUrl',
                ];
                
                // Map basic fields
                foreach ($fieldMapping as $ourField => $btcpayField) {
                    if (array_key_exists($ourField, $config)) {
                        $value = $config[$ourField];
                        // Skip null values (but keep empty strings, 0, false, and empty arrays)
                        if ($value !== null) {
                            $filteredConfig[$btcpayField] = $value;
                        }
                    }
                }
                
                // Map requestCustomerData to request field
                // BTCPay expects: "email", "name", or "email,name" (comma-separated)
                // The 'request' field is required, so always include it
                if (isset($config['requestCustomerData'])) {
                    $requestValue = $config['requestCustomerData'];
                    // Map our values to BTCPay format
                    $requestMapping = [
                        'email' => 'email',
                        'name' => 'name',
                        'email_name' => 'email,name',
                        '' => '', // Empty string for no request
                    ];
                    $filteredConfig['request'] = $requestMapping[$requestValue] ?? ($requestValue ?: '');
                } else {
                    // Always include request field (required by BTCPay API)
                    $filteredConfig['request'] = '';
                }
                
                // Handle template field - must be valid JSON string or array
                if (isset($config['template'])) {
                    $template = $config['template'];
                    if ($template !== null && $template !== '') {
                        // If it's already a string, check if it's valid JSON
                        if (is_string($template)) {
                            // Try to decode to check if it's valid JSON
                            $decoded = json_decode($template, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                // It's valid JSON string, use it as is
                                $filteredConfig['template'] = $template;
                            } else {
                                // Invalid JSON string, try to encode it as array
                                Log::warning('Invalid JSON in template field, attempting to fix', [
                                    'template' => substr($template, 0, 100), // Log first 100 chars
                                ]);
                                $filteredConfig['template'] = json_encode([$template]); // Wrap in array
                            }
                        } elseif (is_array($template)) {
                            // It's an array, encode it to JSON string
                            $filteredConfig['template'] = json_encode($template);
                        }
                    }
                }
            }
            
            // Log what we're sending for debugging
            $baseUrl = config('services.btcpay.base_url', env('BTCPAY_BASE_URL', 'https://pay.dvadsatjeden.org'));
            
            Log::info('BTCPay app update request', [
                'base_url' => $baseUrl,
                'store_id' => $storeId,
                'app_id' => $appId,
                'app_type' => $appType,
                'method' => 'PUT', // Always use PUT for updates (POST creates new apps)
                'endpoints' => $endpoints,
                'full_urls' => array_map(function($ep) use ($baseUrl, $storeId, $appId) {
                    return rtrim($baseUrl, '/') . str_replace(['{$storeId}', '{$appId}'], [$storeId, $appId], $ep);
                }, $endpoints),
                'original_config_keys' => array_keys($config),
                'filtered_config' => $filteredConfig,
            ]);
            
            // Try endpoints in order
            // For Crowdfund: use POST with id and storeId in body (PUT doesn't work - 404/405)
            // For other app types: use PUT method
            $lastException = null;
            
            foreach ($endpoints as $endpointIndex => $endpoint) {
                try {
                    // For all app types (including Crowdfund), use PUT method
                    // Replace placeholders in endpoint
                    $endpointWithId = str_replace('{$appId}', $appId, $endpoint);
                    $endpointWithId = str_replace('{$storeId}', $storeId, $endpointWithId);
                    
                    Log::info('Attempting app update with PUT method', [
                        'endpoint' => $endpoint,
                        'endpoint_with_id' => $endpointWithId,
                        'app_id' => $appId,
                        'app_type' => $appType,
                        'config_has_id' => isset($filteredConfig['id']),
                        'config_appType' => $filteredConfig['appType'] ?? null,
                    ]);
                    
                    $response = $this->client->put($endpointWithId, $filteredConfig);
                    
                    Log::info('App update with PUT succeeded', [
                        'endpoint' => $endpointWithId,
                        'app_id' => $appId,
                        'app_type' => $appType,
                    ]);
                    
                    return $response;
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    $lastException = $e;
                    
                    // Method used for logging
                    $isCrowdfund = ($appType && strtolower($appType) === 'crowdfund');
                    $methodUsed = $isCrowdfund ? 'POST' : 'PUT';
                    
                    // Get status code - use getStatusCode() method, not getCode()
                    $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
                    
                    // Log the error with more details
                    if ($statusCode === 422) {
                        Log::error('BTCPay app update validation error', [
                            'store_id' => $storeId,
                            'app_id' => $appId,
                            'app_type' => $appType,
                            'endpoint' => $endpoint,
                            'method' => $methodUsed,
                            'status_code' => $statusCode,
                            'config_sent' => $filteredConfig,
                            'error_message' => $e->getMessage(),
                        ]);
                    }
                    
                    // Get status code - use getStatusCode() method, not getCode()
                    $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
                    
                    // Log all errors for debugging
                    Log::info('BTCPay app update endpoint failed', [
                        'endpoint' => $endpoint,
                        'method' => $methodUsed,
                        'status_code' => $statusCode,
                        'status_code_from_getCode' => $e->getCode(),
                        'status_code_from_getStatusCode' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 'N/A',
                        'error_message' => $e->getMessage(),
                        'endpoint_index' => $endpointIndex,
                        'total_endpoints' => count($endpoints),
                        'has_more_endpoints' => ($endpointIndex + 1 < count($endpoints)),
                    ]);
                    
                    // If 404 (Not Found) or 405 (Method Not Allowed), try next endpoint
                    // This allows us to fall back from PUT to POST if PUT is not supported
                    if (($statusCode === 404 || $statusCode === 405)) {
                        if ($endpointIndex + 1 < count($endpoints)) {
                            Log::info('BTCPay endpoint failed with 404/405, trying next endpoint', [
                                'failed_endpoint' => $endpoint,
                                'failed_method' => $methodUsed,
                                'status_code' => $statusCode,
                                'remaining_endpoints' => count($endpoints) - ($endpointIndex + 1),
                                'next_endpoint' => $endpoints[$endpointIndex + 1] ?? null,
                            ]);
                            continue; // Try next endpoint
                        } else {
                            Log::warning('BTCPay endpoint failed with 404/405, but no more endpoints to try', [
                                'failed_endpoint' => $endpoint,
                                'failed_method' => $methodUsed,
                                'status_code' => $statusCode,
                            ]);
                        }
                    }
                    
                    // For other errors (like 422) or if this is the last endpoint, throw immediately
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
            $baseUrl = config('services.btcpay.base_url', env('BTCPAY_BASE_URL'));
            
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
            
            Log::info('BTCPay app deletion attempt', [
                'base_url' => $baseUrl,
                'store_id' => $storeId,
                'app_id' => $appId,
                'app_type' => $appType,
                'endpoints' => $endpoints,
                'full_urls' => array_map(function($ep) use ($baseUrl, $appId) {
                    return rtrim($baseUrl, '/') . str_replace('{$appId}', $appId, $ep);
                }, $endpoints),
            ]);
            
            // Try endpoints in order
            $lastException = null;
            foreach ($endpoints as $index => $endpoint) {
                try {
                    Log::info('Trying delete endpoint', [
                        'endpoint' => $endpoint,
                        'attempt' => $index + 1,
                        'total' => count($endpoints),
                    ]);
                    
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
                    Log::warning('Delete endpoint failed', [
                        'endpoint' => $endpoint,
                        'error_code' => $e->getCode(),
                        'error_message' => $e->getMessage(),
                        'attempt' => $index + 1,
                        'total' => count($endpoints),
                    ]);
                    
                    // Try next endpoint if 404 or 405 (Method Not Allowed) and more endpoints available
                    if (($e->getCode() === 404 || $e->getCode() === 405) && ($index + 1) < count($endpoints)) {
                        Log::info('Trying next endpoint', [
                            'current_endpoint' => $endpoint,
                            'remaining_attempts' => count($endpoints) - ($index + 1),
                        ]);
                        continue;
                    }
                    // If this is the last endpoint or error is not 404/405, throw the exception
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


