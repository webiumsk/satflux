<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Apps\AppItemTemplateNormalizer;
use App\Services\BtcPay\Apps\CrowdfundUpdatePayloadBuilder;
use App\Services\BtcPay\Apps\PosUpdatePayloadBuilder;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Log;

class AppService
{
    /**
     * Greenfield endpoint path segments per app type (lowercased).
     */
    protected const APP_TYPE_ENDPOINTS = [
        'pointofsale' => 'pos',
        'crowdfund' => 'crowdfund',
        'paymentbutton' => 'paymentbutton',
    ];

    public function __construct(
        protected BtcPayClient $client,
        protected CrowdfundUpdatePayloadBuilder $crowdfundPayloadBuilder,
        protected PosUpdatePayloadBuilder $posPayloadBuilder,
    ) {}

    /**
     * List all apps for a store.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string|null  $userApiKey  User-level API key (optional, uses server-level if not provided)
     * @return array List of apps
     */
    public function listApps(string $storeId, ?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, function () use ($storeId) {
            try {
                try {
                    return $this->client->get("/stores/{$storeId}/apps");
                } catch (BtcPayException $e) {
                    if ($e->getStatusCode() === 404) {
                        return $this->client->get("/api/v1/stores/{$storeId}/apps");
                    }
                    throw $e;
                }
            } catch (BtcPayException $e) {
                Log::warning('BTCPay apps listing failed - endpoint may not exist', [
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ]);

                // Return empty array instead of throwing to allow app creation to continue
                return [];
            }
        });
    }

    /**
     * Create a new app for a store.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string  $appType  App type (PointOfSale, Crowdfund, PaymentButton, LightningAddress)
     * @param  array  $config  App-specific configuration
     * @param  string|null  $userApiKey  User-level API key (optional)
     * @return array Created app data
     *
     * @throws BtcPayException
     */
    public function createApp(string $storeId, string $appType, array $config, ?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, function () use ($storeId, $appType, $config, $userApiKey) {
            try {
                $requestBody = $this->buildCreateRequestBody($appType, $config);
                $endpoint = "/api/v1/stores/{$storeId}/apps/".$this->endpointPathFor($appType);

                Log::info('Creating BTCPay app', [
                    'store_id' => $storeId,
                    'app_type' => $appType,
                    'endpoint' => $endpoint,
                    'request_body' => $requestBody,
                ]);

                // Raw response needed: for crowdfunds BTCPay often returns the new
                // app ID only in the Location header, not in the response body.
                $responseObj = $this->client->postForResponse($endpoint, $requestBody);

                if (! $responseObj->successful()) {
                    // Let BtcPayClient map the error (throws) or retry transparently
                    $response = $this->client->post($endpoint, $requestBody);
                    $locationHeader = null;
                } else {
                    $response = $responseObj->json() ?? [];
                    $locationHeader = $responseObj->header('Location');
                }

                if ($locationHeader && preg_match('#/apps/([^/]+)#', $locationHeader, $matches)) {
                    $response['id'] = $matches[1];

                    return $response;
                }

                if (! empty($response['id'])) {
                    return $response;
                }

                // No ID anywhere - fall back to locating the app in the apps list
                return $this->findCreatedApp($storeId, $appType, $requestBody, $response, $userApiKey);
            } catch (BtcPayException $e) {
                Log::error('BTCPay app creation failed', [
                    'store_id' => $storeId,
                    'app_type' => $appType,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get app details.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string  $appId  BTCPay app ID
     * @param  string|null  $appType  App type (PointOfSale, Crowdfund, etc.) - optional, will try to detect
     * @param  string|null  $userApiKey  User-level API key (optional)
     * @return array App data
     *
     * @throws BtcPayException
     */
    public function getApp(string $storeId, string $appId, ?string $appType = null, ?string $userApiKey = null): array
    {
        return $this->client->withUserKey($userApiKey, function () use ($storeId, $appId, $appType) {
            $endpoints = [];
            if ($appType) {
                $endpoints[] = '/api/v1/apps/'.$this->endpointPathFor($appType)."/{$appId}";
            }
            $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";

            try {
                return $this->tryEndpoints(
                    $endpoints,
                    fn (string $endpoint) => $this->client->get($endpoint),
                    'Failed to get app',
                    retryOnStatuses: [404],
                );
            } catch (BtcPayException $e) {
                Log::error('BTCPay app retrieval failed', [
                    'store_id' => $storeId,
                    'app_id' => $appId,
                    'app_type' => $appType,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Update app settings.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string  $appId  BTCPay app ID
     * @param  array  $config  Updated configuration
     * @param  string|null  $appType  App type (PointOfSale, Crowdfund, etc.) - optional, will try to detect
     * @param  string|null  $userApiKey  User-level API key (optional)
     * @return array Updated app data
     *
     * @throws BtcPayException
     */
    public function updateApp(string $storeId, string $appId, array $config, ?string $appType = null, ?string $userApiKey = null): array
    {
        $appTypeLower = strtolower($appType ?? '');

        // GreenfieldAppsController has no PUT for Payment Button (only POS + Crowdfund typed routes).
        if ($appTypeLower === 'paymentbutton') {
            throw new BtcPayException('Greenfield API has no PUT endpoint for Payment Button apps.', 400);
        }

        if ($appTypeLower === 'crowdfund') {
            // Crowdfund PUT expects a full CrowdfundAppRequest; merge delta onto current app so omitted fields are not cleared.
            $existing = $this->getApp($storeId, $appId, $appType, $userApiKey);
            $config = array_replace_recursive($existing, $config);
            $filteredConfig = $this->crowdfundPayloadBuilder->build($config, $appId, $storeId);
        } else {
            $filteredConfig = $this->posPayloadBuilder->build($config);
        }

        return $this->client->withUserKey($userApiKey, function () use ($storeId, $appId, $appType, $appTypeLower, $filteredConfig) {
            // Typed Greenfield PUT routes exist only for PoS and Crowdfund (see GreenfieldAppsController).
            $endpoints = [];
            if (in_array($appTypeLower, ['pointofsale', 'crowdfund'], true)) {
                $endpoints[] = '/api/v1/apps/'.$this->endpointPathFor($appTypeLower)."/{$appId}";
            }
            $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";

            Log::info('BTCPay app update request', [
                'store_id' => $storeId,
                'app_id' => $appId,
                'app_type' => $appType,
                'endpoints' => $endpoints,
                'filtered_config' => $filteredConfig,
            ]);

            try {
                return $this->tryEndpoints(
                    $endpoints,
                    function (string $endpoint) use ($appId, $appType, $filteredConfig) {
                        $response = $this->client->put($endpoint, $filteredConfig);

                        // Verify response contains the same ID (to detect if a new app was created)
                        $responseId = $response['id'] ?? $response['appId'] ?? $response['app_id'] ?? null;
                        if ($responseId && $responseId !== $appId) {
                            Log::error('CRITICAL: BTCPay returned different ID after update - new app may have been created!', [
                                'expected_id' => $appId,
                                'returned_id' => $responseId,
                                'endpoint' => $endpoint,
                            ]);
                        }

                        Log::info('App update succeeded', [
                            'endpoint' => $endpoint,
                            'app_id' => $appId,
                            'app_type' => $appType,
                            'returned_id' => $responseId,
                        ]);

                        return $response;
                    },
                    'Failed to update app',
                    retryOnStatuses: [404, 405],
                    onError: function (BtcPayException $e, string $endpoint) use ($storeId, $appId, $appType, $filteredConfig) {
                        if ($e->getStatusCode() === 422) {
                            Log::error('BTCPay app update validation error', [
                                'store_id' => $storeId,
                                'app_id' => $appId,
                                'app_type' => $appType,
                                'endpoint' => $endpoint,
                                'config_sent' => $filteredConfig,
                                'error_message' => $e->getMessage(),
                            ]);
                        }
                    },
                );
            } catch (BtcPayException $e) {
                Log::error('BTCPay app update failed', [
                    'store_id' => $storeId,
                    'app_id' => $appId,
                    'app_type' => $appType,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Delete an app.
     *
     * @param  string  $storeId  BTCPay store ID
     * @param  string  $appId  BTCPay app ID
     * @param  string|null  $appType  App type (PointOfSale, Crowdfund, etc.) - optional, will try to detect
     * @param  string|null  $userApiKey  User-level API key (optional)
     * @return bool True if deleted successfully
     *
     * @throws BtcPayException
     */
    public function deleteApp(string $storeId, string $appId, ?string $appType = null, ?string $userApiKey = null): bool
    {
        return $this->client->withUserKey($userApiKey, function () use ($storeId, $appId, $appType) {
            // Standard delete endpoint from docs first, then app-typed, then store-scoped fallback
            $endpoints = ["/api/v1/apps/{$appId}"];
            if ($appType) {
                $endpoints[] = '/api/v1/apps/'.$this->endpointPathFor($appType)."/{$appId}";
            }
            $endpoints[] = "/api/v1/stores/{$storeId}/apps/{$appId}";

            try {
                $this->tryEndpoints(
                    $endpoints,
                    fn (string $endpoint) => $this->client->delete($endpoint),
                    'Failed to delete app',
                    retryOnStatuses: [404, 405],
                );

                Log::info('BTCPay app deleted successfully', [
                    'store_id' => $storeId,
                    'app_id' => $appId,
                    'app_type' => $appType,
                ]);

                return true;
            } catch (BtcPayException $e) {
                Log::error('BTCPay app deletion failed', [
                    'store_id' => $storeId,
                    'app_id' => $appId,
                    'app_type' => $appType,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Greenfield endpoint path segment for an app type (e.g. PointOfSale -> pos).
     */
    protected function endpointPathFor(string $appType): string
    {
        $appTypeLower = strtolower($appType);

        return self::APP_TYPE_ENDPOINTS[$appTypeLower] ?? $appTypeLower;
    }

    /**
     * Try endpoints in order; 404/405 (per $retryOnStatuses) moves to the next
     * endpoint, any other error throws immediately.
     *
     * @param  list<string>  $endpoints
     * @param  \Closure(string): mixed  $attempt
     * @param  list<int>  $retryOnStatuses
     * @param  (\Closure(BtcPayException, string): void)|null  $onError  Extra per-endpoint error logging
     *
     * @throws BtcPayException
     */
    protected function tryEndpoints(array $endpoints, \Closure $attempt, string $failMessage, array $retryOnStatuses = [404], ?\Closure $onError = null): mixed
    {
        $lastException = null;

        foreach ($endpoints as $index => $endpoint) {
            try {
                return $attempt($endpoint);
            } catch (BtcPayException $e) {
                $lastException = $e;

                if ($onError) {
                    $onError($e, $endpoint);
                }

                if (in_array($e->getStatusCode(), $retryOnStatuses, true) && ($index + 1) < count($endpoints)) {
                    Log::info('BTCPay endpoint failed, trying next', [
                        'endpoint' => $endpoint,
                        'status_code' => $e->getStatusCode(),
                        'remaining_endpoints' => count($endpoints) - ($index + 1),
                    ]);

                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new BtcPayException($failMessage);
    }

    /**
     * Build the POST body for app creation (appName mapping + item price type normalization).
     */
    protected function buildCreateRequestBody(string $appType, array $config): array
    {
        $requestBody = [];
        $appTypeLower = strtolower($appType);

        // According to BTCPay API docs, request body uses 'appName' (not 'name');
        // appType lives in the URL, not the body.
        if (isset($config['name'])) {
            $requestBody['appName'] = $config['name'];
        }

        foreach ($config as $key => $value) {
            if ($key === 'appType' || $key === 'name') {
                continue;
            }
            if (($key === 'perks' || $key === 'items' || $key === 'template') && ($appTypeLower === 'crowdfund' || $appTypeLower === 'pointofsale')) {
                if (is_array($value)) {
                    $value = AppItemTemplateNormalizer::normalizePriceTypes($value);
                } elseif (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $value = json_encode(AppItemTemplateNormalizer::normalizePriceTypes($decoded));
                    }
                }
            }
            $requestBody[$key] = $value;
        }

        if (empty($requestBody['appName'])) {
            $requestBody['appName'] = 'Untitled App';
        }

        return $requestBody;
    }

    /**
     * Locate a just-created app in the apps list when BTCPay returned no ID
     * (neither in the body nor the Location header) - common for crowdfunds.
     */
    protected function findCreatedApp(string $storeId, string $appType, array $requestBody, array $response, ?string $userApiKey): array
    {
        // Wait a bit for the app to be created and indexed
        usleep(1000000); // 1 second

        $apps = $this->listApps($storeId, $userApiKey);
        $appName = $requestBody['appName'] ?? 'New '.$appType;

        $matchingApps = array_filter($apps, function ($app) use ($appType, $appName) {
            $appAppType = $app['appType'] ?? $app['type'] ?? null;
            $appNameFromApp = $app['name'] ?? $app['appName'] ?? '';

            return $appNameFromApp === $appName
                && ($appAppType === $appType || strtolower($appAppType ?? '') === strtolower($appType));
        });

        if (! empty($matchingApps)) {
            // Most recent first - the newly created app
            usort($matchingApps, function ($a, $b) {
                $aCreated = $a['created'] ?? $a['createdTime'] ?? 0;
                $bCreated = $b['created'] ?? $b['createdTime'] ?? 0;

                return $bCreated <=> $aCreated;
            });

            $createdApp = reset($matchingApps);
            if (! empty($createdApp['id'])) {
                return array_merge($response, $createdApp);
            }
        }

        Log::warning('Could not find newly created app in apps list', [
            'store_id' => $storeId,
            'app_type' => $appType,
            'app_name' => $appName,
            'apps_count' => count($apps),
        ]);

        return $response;
    }
}
