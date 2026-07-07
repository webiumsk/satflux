<?php

namespace App\Services;

use App\Models\App;
use App\Support\SatfluxStorageUrl;

/**
 * Single place that decides what a BTCPay app looks like in API responses.
 * btcpay_app_id / btcpay_app_url are exposed deliberately - embed codes and
 * BTCPay deep links need them.
 */
class AppResponseFormatter
{
    /**
     * @param  array<string, mixed>|null  $btcpayApp
     * @return array<string, mixed>
     */
    public function format(App $app, ?array $btcpayApp = null): array
    {
        // Get config from local DB or BTCPay API - prioritize BTCPay API data
        $config = $btcpayApp ?? $app->config ?? [];

        // BTCPay API may return products in 'items' field (GET) or 'template' field (POST/PUT)
        // Normalize 'items' to 'template' for frontend consistency
        if (isset($config['items']) && ! isset($config['template'])) {
            $config['template'] = $config['items'];
        }

        // If template is a JSON string, decode it to array for frontend
        if (isset($config['template']) && is_string($config['template'])) {
            $decoded = json_decode($config['template'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $config['template'] = $decoded;
            }
        }

        $config = $this->rewriteSatfluxStorageUrlsInAppConfig($config);

        $archived = $config['archived'] ?? false;
        if (is_string($archived)) {
            $archived = strtolower($archived) === 'true' || $archived === '1';
        }

        $data = [
            'id' => $app->id,
            'name' => $app->name,
            'app_type' => $app->app_type,
            'archived' => (bool) $archived,
            'config' => $config,
            'metadata' => $app->metadata,
            'created_at' => $app->created_at,
            'updated_at' => $app->updated_at,
        ];

        // Merge BTCPay data if available
        if ($btcpayApp) {
            // Side effect kept from the original controller: persist the latest
            // BTCPay config onto the local record so template/products stay fresh.
            $app->config = array_merge($app->config ?? [], $btcpayApp);
            $app->save();

            $btcpayAppId = $btcpayApp['id'] ?? $app->btcpay_app_id ?? null;
            if ($btcpayAppId) {
                $data['btcpay_app_url'] = $this->generateAppUrl($app->app_type, $btcpayAppId);
                // The app ID is needed for embed codes
                $data['btcpay_app_id'] = $btcpayAppId;
            }
        } elseif ($app->btcpay_app_id) {
            $data['btcpay_app_url'] = $this->generateAppUrl($app->app_type, $app->btcpay_app_id);
            $data['btcpay_app_id'] = $app->btcpay_app_id;
        }

        return $data;
    }

    /**
     * Generate BTCPay app URL based on app type.
     */
    public function generateAppUrl(string $appType, string $appId): string
    {
        $basePath = rtrim((string) config('services.btcpay.base_url'), '/').'/apps/'.$appId;

        return match (strtolower($appType)) {
            'pointofsale' => $basePath.'/pos',
            'crowdfund' => $basePath.'/crowdfund',
            'paymentbutton' => $basePath.'/paymentbutton',
            'lightningaddress' => $basePath.'/lnaddress',
            default => $basePath,
        };
    }

    /**
     * Point /storage/… URLs in app config at the current APP_URL (fixes stale host after domain or env changes).
     * Crowdfund perks embed image URLs inside a JSON string (perksTemplate); rewrite those too.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function rewriteSatfluxStorageUrlsInAppConfig(array $config): array
    {
        $config = $this->rewriteStorageUrlsRecursive($config);

        foreach (['perksTemplate'] as $jsonKey) {
            if (! isset($config[$jsonKey]) || ! is_string($config[$jsonKey])) {
                continue;
            }
            $decoded = json_decode($config[$jsonKey], true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                continue;
            }
            $config[$jsonKey] = json_encode($this->rewriteStorageUrlsRecursive($decoded));
        }

        return $config;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    protected function rewriteStorageUrlsRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = SatfluxStorageUrl::rewriteToCurrentApp($v);
            } elseif (is_array($v)) {
                $data[$k] = $this->rewriteStorageUrlsRecursive($v);
            }
        }

        return $data;
    }
}
