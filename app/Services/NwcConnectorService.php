<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NwcConnectorService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.nwc_connector.base_url', 'http://nwc-connector:8082'), '/');
        $this->apiKey = config('services.nwc_connector.api_key');
    }

    /**
     * Create a new NWC connector for the store.
     * Optionally pass merchant's NWC URI (from their Alby/Phoenix wallet) to enable receive-only forwarding.
     * Returns ['connector_id' => uuid, 'connection_string' => string, 'nwc_uri' => string] or throws.
     */
    public function createConnector(Store $store, ?string $backendNwcUri = null): array
    {
        $payload = [
            'store_id' => $store->id,
            'btcpay_store_id' => $store->btcpay_store_id,
        ];
        if ($backendNwcUri !== null && trim($backendNwcUri) !== '') {
            $payload['backend_nwc_uri'] = trim($backendNwcUri);
        }
        $response = Http::timeout(10)
            ->when($this->apiKey, fn ($r) => $r->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]))
            ->post($this->baseUrl . '/connectors', $payload);

        if (! $response->successful()) {
            Log::warning('NWC Connector create failed', [
                'store_id' => $store->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('NWC Connector: ' . ($response->json('message') ?? $response->body() ?: 'create failed'));
        }

        return $response->json();
    }

    /**
     * Get connector info (masked).
     */
    public function getConnector(string $connectorId): ?array
    {
        $response = Http::timeout(5)
            ->when($this->apiKey, fn ($r) => $r->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]))
            ->get($this->baseUrl . '/connectors/' . $connectorId);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Revoke connector.
     */
    public function revokeConnector(string $connectorId): bool
    {
        $response = Http::timeout(5)
            ->when($this->apiKey, fn ($r) => $r->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey]))
            ->post($this->baseUrl . '/connectors/' . $connectorId . '/revoke');

        return $response->successful();
    }

    /**
     * Health check.
     */
    public function health(): bool
    {
        $response = Http::timeout(3)->get($this->baseUrl . '/health');

        return $response->successful();
    }
}
