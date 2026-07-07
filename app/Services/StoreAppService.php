<?php

namespace App\Services;

use App\Models\App;
use App\Models\Store;
use App\Services\BtcPay\AppService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Business logic for store apps (PoS, Crowdfund, Payment Button, LN Address):
 * listing with local-record import, creation with BTCPay app-ID recovery,
 * config merging for updates, deletion. Extracted from AppController -
 * behavior preserved 1:1; HTTP concerns (validation, guest gates, response
 * codes) stay in the controllers.
 */
class StoreAppService
{
    public function __construct(
        protected AppService $appService,
        protected AppResponseFormatter $formatter,
    ) {}

    /**
     * Apps list for a store, importing local records for BTCPay apps we don't know yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForStore(Store $store): array
    {
        $localApps = App::where('store_id', $store->id)
            ->get()
            ->keyBy('btcpay_app_id');

        if ($localApps->isNotEmpty()) {
            try {
                $userApiKey = $store->user->getBtcPayApiKeyOrFail();
                $btcpayApps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
                $btcpayAppsMap = collect($btcpayApps)->keyBy('id');

                return $localApps->map(function ($localApp) use ($btcpayAppsMap) {
                    $btcpayApp = $btcpayAppsMap->get($localApp->btcpay_app_id);

                    return $this->formatter->format($localApp, $btcpayApp);
                })->values()->all();
            } catch (BtcPayException $e) {
                Log::warning('BTCPay API failed when listing apps, using local apps only', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);

                return $localApps->map(fn ($app) => $this->formatter->format($app))->values()->all();
            }
        }

        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $btcpayApps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
            if (empty($btcpayApps)) {
                return [];
            }

            return collect($btcpayApps)->map(function ($btcpayApp) use ($store) {
                $btcpayAppId = $btcpayApp['id'] ?? null;
                if (! $btcpayAppId) {
                    return null;
                }
                $localApp = App::create([
                    'id' => (string) Str::uuid(),
                    'store_id' => $store->id,
                    'btcpay_app_id' => $btcpayAppId,
                    'app_type' => $this->determineAppType($btcpayApp),
                    'name' => $btcpayApp['appName'] ?? $btcpayApp['name'] ?? 'Untitled App',
                    'config' => $btcpayApp,
                ]);

                return $this->formatter->format($localApp, $btcpayApp);
            })->filter()->values()->all();
        } catch (BtcPayException $e) {
            Log::warning('BTCPay API failed when listing apps, no local apps found', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Formatted app for a store; null if the app does not belong to the store.
     *
     * @return array<string, mixed>|null
     */
    public function getForStore(Store $store, App $app): ?array
    {
        if ($app->store_id !== $store->id) {
            return null;
        }
        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $btcpayApp = $this->appService->getApp(
                $store->btcpay_store_id,
                $app->btcpay_app_id,
                $app->app_type,
                $userApiKey
            );

            return $this->formatter->format($app, $btcpayApp);
        } catch (BtcPayException $e) {
            Log::warning('BTCPay API failed when loading app, using local fallback', [
                'app_id' => $app->id,
                'btcpay_app_id' => $app->btcpay_app_id,
                'error' => $e->getMessage(),
            ]);

            return $this->formatter->format($app);
        }
    }

    /**
     * Create the app in BTCPay and the local record. The BTCPay app ID is
     * recovered from the apps list when the create response omits it - a
     * local record without btcpay_app_id would make later updates create
     * duplicate apps instead of updating.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed> Formatted app payload
     *
     * @throws \Exception when no BTCPay app ID could be resolved
     */
    public function create(Store $store, string $appType, string $name, array $config): array
    {
        return DB::transaction(function () use ($store, $appType, $name, $config) {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();

            $config['name'] = $name;
            if (! isset($config['currency'])) {
                $config['currency'] = $store->default_currency ?? 'EUR';
            }
            // Default view for PointOfSale apps is Light (Keypad)
            if ($appType === 'PointOfSale' && ! isset($config['defaultView'])) {
                $config['defaultView'] = 'Light';
            }

            $btcpayApp = $this->appService->createApp($store->btcpay_store_id, $appType, $config, $userApiKey);

            $btcpayAppId = $btcpayApp['id']
                ?? $btcpayApp['appId']
                ?? $btcpayApp['app_id']
                ?? null;

            if (empty($btcpayAppId)) {
                Log::error('BTCPay app creation response missing ID - trying apps list', [
                    'store_id' => $store->btcpay_store_id,
                    'app_type' => $appType,
                    'app_name' => $name,
                    'response_keys' => array_keys($btcpayApp),
                ]);
                $btcpayAppId = $this->findAppIdByNameAndType($store, $appType, $name, $userApiKey, waitSeconds: 2);
            }

            if (empty($btcpayAppId)) {
                Log::error('CRITICAL: Cannot create local app record without btcpay_app_id', [
                    'store_id' => $store->id,
                    'app_type' => $appType,
                    'app_name' => $name,
                ]);
                throw new \Exception('Failed to create app: BTCPay app ID is missing. Please try again.');
            }

            $app = App::create([
                'id' => (string) Str::uuid(),
                'store_id' => $store->id,
                'btcpay_app_id' => $btcpayAppId,
                'app_type' => $appType,
                'name' => $name,
                'config' => $btcpayApp,
            ]);

            Log::info('Local app record created with btcpay_app_id', [
                'app_id' => $app->id,
                'btcpay_app_id' => $btcpayAppId,
                'app_name' => $name,
                'app_type' => $appType,
            ]);

            return $this->formatter->format($app, $btcpayApp);
        });
    }

    /**
     * Legacy path: local record exists without btcpay_app_id - create the app
     * in BTCPay first, then persist the resolved ID.
     *
     * @param  array<string, mixed>|null  $requestConfig
     * @return array<string, mixed> Formatted app payload
     */
    public function createInBtcPayForLegacyApp(Store $store, App $app, ?string $name, ?array $requestConfig): array
    {
        Log::warning('App update called but btcpay_app_id is missing - creating app in BTCPay first', [
            'app_id' => $app->id,
            'store_id' => $store->id,
            'app_type' => $app->app_type,
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        $config = $app->config ?? [];

        // Map 'name' to 'appName' for BTCPay API
        if ($name !== null) {
            $config['appName'] = $name;
            unset($config['name']);
        }

        if ($requestConfig !== null) {
            if (isset($requestConfig['name']) && ! isset($requestConfig['appName'])) {
                $requestConfig['appName'] = $requestConfig['name'];
                unset($requestConfig['name']);
            }
            $config = array_merge($config, $requestConfig);
        }

        $btcpayApp = $this->appService->createApp($store->btcpay_store_id, $app->app_type, $config, $userApiKey);

        $btcpayAppId = $btcpayApp['id'] ?? $btcpayApp['appId'] ?? $btcpayApp['app_id'] ?? null;

        if ($btcpayAppId) {
            $app->update([
                'btcpay_app_id' => $btcpayAppId,
                'name' => $name ?? $app->name,
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

        return $this->formatter->format($app->fresh(), $btcpayApp);
    }

    /**
     * Build the BTCPay update config: request config takes priority, DB config
     * fills the gaps, metadata fields must never be sent (they would make
     * BTCPay create a new app instead of updating).
     *
     * @param  array<string, mixed>|null  $requestConfig
     * @return array<string, mixed>
     */
    public function buildUpdateConfig(App $app, ?array $requestConfig, ?string $name, ?bool $archived): array
    {
        $config = [];

        if ($requestConfig !== null) {
            $config = $requestConfig;
            $this->stripBtcPayMetadataFields($config);

            if (isset($config['name']) && ! isset($config['appName'])) {
                $config['appName'] = $config['name'];
                unset($config['name']);
            }
        }

        $dbConfig = $app->config ?? [];
        $this->stripBtcPayMetadataFields($dbConfig);

        // Remove old title/appName from DB config when the request carries new
        // ones - old values must not override new ones.
        if ($requestConfig !== null) {
            if (isset($config['displayTitle']) || isset($config['title'])) {
                unset($dbConfig['title'], $dbConfig['displayTitle']);
            }
            if (isset($config['appName']) || $name !== null) {
                unset($dbConfig['appName'], $dbConfig['name']);
            }
        }

        // DB config as fallback (request config takes priority)
        $config = array_merge($dbConfig, $config);

        // id MUST come from the btcpay_app_id parameter, never the config
        unset($config['id']);

        if ($name !== null) {
            $config['appName'] = $name;
            unset($config['name']);
        }

        if ($archived !== null) {
            $config['archived'] = $archived;
        }

        return $config;
    }

    /**
     * BTCPay metadata fields must never be sent in update payloads - they can
     * make BTCPay create a new app instead of updating the existing one.
     *
     * @param  array<string, mixed>  $config
     */
    protected function stripBtcPayMetadataFields(array &$config): void
    {
        unset(
            $config['id'],
            $config['appType'],
            $config['app_type'],
            $config['storeId'],
            $config['store_id'],
            $config['archived'],
            $config['created'],
            $config['btcpay_app_id'],
        );
    }

    /**
     * Try to restore a missing btcpay_app_id by matching name+type in the
     * BTCPay apps list. Returns true when the app now has an ID.
     */
    public function recoverMissingBtcpayAppId(Store $store, App $app): bool
    {
        if (! empty($app->btcpay_app_id)) {
            return true;
        }

        Log::error('Cannot update app: btcpay_app_id is missing in DB', [
            'app_id' => $app->id,
            'store_id' => $store->id,
            'app_name' => $app->name,
            'app_type' => $app->app_type,
        ]);

        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $foundAppId = $this->findAppIdByNameAndType($store, $app->app_type, $app->name, $userApiKey);

            if ($foundAppId) {
                $app->update(['btcpay_app_id' => $foundAppId]);
                Log::info('Found and restored btcpay_app_id from BTCPay apps list', [
                    'app_id' => $app->id,
                    'btcpay_app_id' => $foundAppId,
                ]);
                $app->refresh();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to find app in BTCPay apps list', [
                'app_id' => $app->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ! empty($app->btcpay_app_id);
    }

    /**
     * Update the app in BTCPay, verify no duplicate was created, persist and
     * reload the fresh config.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed> Formatted app payload
     */
    public function update(Store $store, App $app, array $config, ?string $name): array
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        Log::info('App update - calling updateApp with btcpay_app_id', [
            'app_id' => $app->id,
            'btcpay_app_id' => $app->btcpay_app_id,
            'app_type' => $app->app_type,
            'store_id' => $store->btcpay_store_id,
            'config_keys' => array_keys($config),
        ]);

        $btcpayApp = $this->appService->updateApp(
            $store->btcpay_store_id,
            $app->btcpay_app_id, // MUST be set and correct, otherwise BTCPay creates a new app
            $config,
            $app->app_type,
            $userApiKey
        );

        // Verify that update didn't create a new app
        $returnedAppId = $btcpayApp['id'] ?? $btcpayApp['appId'] ?? $btcpayApp['app_id'] ?? null;
        if ($returnedAppId && $returnedAppId !== $app->btcpay_app_id) {
            Log::error('CRITICAL: BTCPay returned different app ID - new app was created instead of update!', [
                'expected_btcpay_app_id' => $app->btcpay_app_id,
                'returned_app_id' => $returnedAppId,
                'local_app_id' => $app->id,
                'app_name' => $app->name,
            ]);
            // Adopt the new ID to prevent further duplicates
            $app->update(['btcpay_app_id' => $returnedAppId]);
        }

        $finalBtcpayAppId = $returnedAppId ?: $app->btcpay_app_id;

        $app->update([
            'name' => $name ?? $app->name,
            'btcpay_app_id' => $finalBtcpayAppId, // Always preserve btcpay_app_id
            'config' => $btcpayApp,
        ]);

        // Reload fresh data from BTCPay so template/products are current and
        // correctly formatted (JSON string from BTCPay)
        try {
            $freshBtcpayApp = $this->appService->getApp(
                $store->btcpay_store_id,
                $finalBtcpayAppId,
                $app->app_type,
                $userApiKey
            );
            $app->update([
                'config' => $freshBtcpayApp,
                'btcpay_app_id' => $finalBtcpayAppId,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to reload app data after update', [
                'app_id' => $app->id,
                'btcpay_app_id' => $finalBtcpayAppId,
                'error' => $e->getMessage(),
            ]);
            $freshBtcpayApp = $btcpayApp;
        }

        return $this->formatter->format($app->fresh(), $freshBtcpayApp);
    }

    /**
     * Payment Button has no Greenfield PUT - only the local archived flag is updated.
     *
     * @return array<string, mixed> Formatted app payload
     */
    public function archivePaymentButtonLocally(App $app, bool $archived): array
    {
        $mergedConfig = array_merge($app->config ?? [], ['archived' => $archived]);
        $app->update(['config' => $mergedConfig]);

        return $this->formatter->format($app->fresh());
    }

    /**
     * Delete the app in BTCPay first; the local record only goes when that succeeds.
     *
     * @throws BtcPayException
     */
    public function delete(Store $store, App $app): void
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $this->appService->deleteApp(
            $store->btcpay_store_id,
            $app->btcpay_app_id,
            $app->app_type,
            $userApiKey
        );

        $app->delete();
    }

    public function countActivePointOfSaleApps(Store $store): int
    {
        return App::query()
            ->where('store_id', $store->id)
            ->where('app_type', 'PointOfSale')
            ->get()
            ->filter(function (App $app) {
                $archived = data_get($app->config, 'archived', false);
                if (is_string($archived)) {
                    return ! (strtolower($archived) === 'true' || $archived === '1');
                }

                return ! (bool) $archived;
            })
            ->count();
    }

    /**
     * Find a BTCPay app ID by matching name and type (most recent first).
     */
    protected function findAppIdByNameAndType(Store $store, string $appType, string $appName, string $userApiKey, int $waitSeconds = 0): ?string
    {
        try {
            if ($waitSeconds > 0) {
                sleep($waitSeconds); // Give BTCPay time to index the new app
            }

            $apps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);

            $matchingApps = array_filter($apps, function ($a) use ($appName, $appType) {
                $nameMatches = ($a['name'] ?? $a['appName'] ?? '') === $appName;
                $typeMatches = ($a['appType'] ?? $a['type'] ?? '') === $appType;

                return $nameMatches && $typeMatches;
            });

            if (empty($matchingApps)) {
                Log::warning('No matching app found in apps list', [
                    'app_name' => $appName,
                    'app_type' => $appType,
                    'total_apps' => count($apps),
                ]);

                return null;
            }

            usort($matchingApps, function ($a, $b) {
                $aCreated = $a['created'] ?? $a['createdTime'] ?? 0;
                $bCreated = $b['created'] ?? $b['createdTime'] ?? 0;

                return $bCreated <=> $aCreated; // Most recent first
            });

            $foundApp = reset($matchingApps);

            return $foundApp['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch app ID from apps list', [
                'app_name' => $appName,
                'app_type' => $appType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Determine app type from BTCPay app data.
     */
    protected function determineAppType(array $btcpayApp): string
    {
        return $btcpayApp['appType'] ?? 'PointOfSale';
    }
}
