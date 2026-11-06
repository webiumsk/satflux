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
        // PoS app lists contain metadata only; retain settings omitted by those responses.
        $config = $btcpayApp ?? $app->config ?? [];
        if (strtolower($app->app_type) === 'pointofsale' && $btcpayApp !== null) {
            $config = $this->mergePointOfSaleConfig($app->config ?? [], $btcpayApp);
        }

        // Greenfield responses use 'items'; requests and local configs can use 'template'.
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
        if (strtolower($app->app_type) === 'pointofsale') {
            $source = $btcpayApp ?? $app->config ?? [];
            foreach (['items', 'template'] as $key) {
                if (array_key_exists($key, $source) && ! $this->isPointOfSaleProductList($source[$key])) {
                    $data['config_read_errors'] = ['products'];
                    break;
                }
            }
        }

        // Merge BTCPay data if available
        if ($btcpayApp) {
            // Side effect kept from the original controller: persist the latest
            // BTCPay config onto the local record so template/products stay fresh.
            $app->config = strtolower($app->app_type) === 'pointofsale'
                ? $this->mergePointOfSaleConfig($app->config ?? [], $btcpayApp)
                : array_merge($app->config ?? [], $btcpayApp);
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
     * Overlay supplied PoS fields, replacing the items/template aliases as one value.
     *
     * @param  array<string, mixed>  $cached
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergePointOfSaleConfig(array $cached, array $incoming): array
    {
        foreach (['items', 'template'] as $key) {
            if (array_key_exists($key, $incoming) && ! $this->isPointOfSaleProductList($incoming[$key])) {
                // A malformed read cannot replace the last usable product list.
                unset($incoming[$key]);
            }
        }

        if (array_key_exists('items', $incoming)) {
            unset($cached['template'], $incoming['template']);
        } elseif (array_key_exists('template', $incoming)) {
            unset($cached['items']);
        }
        if (array_key_exists('tipText', $incoming)) {
            unset($cached['tipsMessage'], $incoming['tipsMessage']);
        } elseif (array_key_exists('tipsMessage', $incoming)) {
            unset($cached['tipText']);
        }

        // A shallow overlay preserves explicit false, null and empty product lists.
        return array_replace($cached, $incoming);
    }

    /**
     * Accept product lists and serialized JSON lists, including an intentional [].
     */
    private function isPointOfSaleProductList(mixed $value): bool
    {
        if (is_string($value)) {
            if (! str_starts_with(ltrim($value), '[')) {
                return false;
            }
            $value = json_decode($value, true);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
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
