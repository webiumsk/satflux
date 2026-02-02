<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\WalletConnection;
use App\Services\BtcPay\LightningService;
use App\Services\NwcConnectorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class NwcConnectorController extends Controller
{
    public function __construct(
        protected NwcConnectorService $nwcService,
        protected LightningService $lightningService
    ) {}

    /**
     * Create NWC connector for store and optionally set Lightning in BTCPay.
     */
    public function store(Request $request)
    {
        $store = $request->route('store');
        $user = $request->user();

        $store->load('user');
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $backendNwcUri = $request->input('backend_nwc_uri');

        try {
            $result = $this->nwcService->createConnector($store, $backendNwcUri);
        } catch (\Throwable $e) {
            Log::warning('NWC connector create failed', ['store_id' => $store->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create Lightning connector: ' . $e->getMessage(),
            ], 422);
        }

        $store->update(['nwc_connector_id' => $result['connector_id']]);

        // Create or update wallet_connections record; start as needs_support until BTCPay is configured
        WalletConnection::updateOrCreate(
            ['store_id' => $store->id],
            [
                'type' => 'nwc',
                'secret_encrypted' => null,
                'status' => 'needs_support',
                'submitted_by_user_id' => $user->id,
            ]
        );

        // Try to set NWC connection string in BTCPay (Nostr plugin format). Only then mark connected.
        $connectionString = $result['connection_string'] ?? null;
        $btcpayOk = false;
        if ($connectionString) {
            try {
                $btcpayResult = $this->lightningService->connectLightningNode(
                    $store->btcpay_store_id,
                    'BTC',
                    $connectionString,
                    $userApiKey
                );
                $btcpayOk = (bool) ($btcpayResult['success'] ?? false);
                if (! $btcpayOk) {
                    Log::info('BTCPay Lightning NWC set failed; connector created', [
                        'store_id' => $store->id,
                        'message' => $btcpayResult['message'] ?? 'unknown',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('BTCPay Lightning NWC set error; connector created', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        if ($btcpayOk) {
            WalletConnection::where('store_id', $store->id)->where('type', 'nwc')->update(['status' => 'connected']);
        }

        AuditLog::log(
            'nwc_connector.created',
            'nwc_connector',
            $result['connector_id'],
            ['store_id' => $store->id],
            $user->id
        );

        return response()->json([
            'data' => [
                'connector_id' => $result['connector_id'],
                'connection_string' => $result['connection_string'] ?? null,
                'nwc_uri' => $result['nwc_uri'] ?? null,
                'btcpay_configured' => $btcpayOk,
            ],
            'message' => $btcpayOk
                ? 'Lightning connector created and BTCPay configured.'
                : 'Lightning connector created. Add the connection string to your BTCPay Server (Lightning → Nostr wallet) to finish.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Get connector info for store (masked).
     */
    public function show(Request $request)
    {
        $store = $request->route('store');

        if (! $store->nwc_connector_id) {
            return response()->json(['data' => null]);
        }

        $info = $this->nwcService->getConnector($store->nwc_connector_id);

        if (! $info) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $info]);
    }

    /**
     * Revoke NWC connector for store.
     */
    public function revoke(Request $request)
    {
        $store = $request->route('store');
        $user = $request->user();

        if (! $store->nwc_connector_id) {
            return response()->json(['message' => 'No connector for this store'], 404);
        }

        $connectorId = $store->nwc_connector_id;

        if (! $this->nwcService->revokeConnector($connectorId)) {
            return response()->json(['message' => 'Failed to revoke connector'], 422);
        }

        $store->update(['nwc_connector_id' => null]);

        // Remove wallet_connections record so store no longer shows NWC connection
        WalletConnection::where('store_id', $store->id)->where('type', 'nwc')->delete();

        AuditLog::log(
            'nwc_connector.revoked',
            'nwc_connector',
            $connectorId,
            ['store_id' => $store->id],
            $user->id
        );

        return response()->json([
            'message' => 'Connector revoked',
        ]);
    }

    /**
     * Health check for NWC Connector service.
     */
    public function health(Request $request)
    {
        $ok = $this->nwcService->health();

        return response()->json([
            'status' => $ok ? 'ok' : 'unavailable',
        ], $ok ? 200 : 503);
    }
}
