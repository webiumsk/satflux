<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletConnectionStoreRequest;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\WalletConnection;
use App\Services\BtcPay\LightningService;
use App\Services\WalletConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WalletConnectionController extends Controller
{
    protected WalletConnectionService $service;
    protected LightningService $lightningService;

    public function __construct(WalletConnectionService $service, LightningService $lightningService)
    {
        $this->service = $service;
        $this->lightningService = $lightningService;
    }

    /**
     * Get wallet connection for a store (masked).
     */
    public function show(Request $request)
    {
        $store = $request->route('store');
        $connection = WalletConnection::where('store_id', $store->id)->first();

        if (!$connection) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $connection->id,
                'type' => $connection->type,
                'status' => $connection->status,
                'masked_secret' => $connection->masked_secret,
                'submitted_at' => $connection->created_at,
            ],
        ]);
    }

    /**
     * Create or update wallet connection.
     */
    public function store(WalletConnectionStoreRequest $request)
    {
        $store = $request->route('store');
        $user = $request->user();

        $connection = $this->service->createOrUpdate(
            $store,
            $request->type,
            $request->secret,
            $user
        );

        // Audit log
        AuditLog::log(
            'wallet_connection.created',
            'wallet_connection',
            $connection->id,
            [
                'store_id' => $store->id,
                'type' => $connection->type,
            ],
            $user->id
        );

        return response()->json([
            'data' => [
                'id' => $connection->id,
                'type' => $connection->type,
                'status' => $connection->status,
                'masked_secret' => $connection->masked_secret,
            ],
            'message' => 'Wallet connection saved successfully',
        ], 201);
    }

    /**
     * Delete wallet connection (only if status is pending).
     */
    public function destroy(Request $request)
    {
        $store = $request->route('store');
        $connection = WalletConnection::where('store_id', $store->id)->first();

        if (!$connection) {
            return response()->json(['message' => 'Wallet connection not found'], 404);
        }

        // Only allow deletion if status is pending
        if ($connection->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete wallet connection. Only pending connections can be deleted.',
            ], 422);
        }

        $connectionId = $connection->id;
        $connection->delete();

        // Audit log
        AuditLog::log(
            'wallet_connection.deleted',
            'wallet_connection',
            $connectionId,
            [
                'store_id' => $store->id,
                'type' => $connection->type,
            ],
            $request->user()->id
        );

        return response()->json(['message' => 'Wallet connection deleted successfully']);
    }

    /**
     * List all wallet connections needing support (support role only).
     */
    public function indexSupport(Request $request)
    {
        $connections = WalletConnection::where('status', 'needs_support')
            ->with(['store', 'submittedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $connections->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'store_id' => $connection->store_id,
                    'store_name' => $connection->store->name ?? 'Unknown',
                    'type' => $connection->type,
                    'status' => $connection->status,
                    'masked_secret' => $connection->masked_secret,
                    'submitted_by' => $connection->submittedBy->email ?? 'Unknown',
                    'submitted_at' => $connection->created_at,
                    'revealed_last_at' => $connection->revealed_last_at,
                ];
            }),
        ]);
    }

    /**
     * Get count of items needing support (support role only).
     */
    public function getSupportCount(Request $request)
    {
        $walletConnectionsCount = WalletConnection::where('status', 'needs_support')->count();

        return response()->json([
            'data' => [
                'wallet_connections' => $walletConnectionsCount,
                'total' => $walletConnectionsCount, // For now only wallet connections, can add stores later
            ],
        ]);
    }

    /**
     * Reveal wallet connection secret (support role only, requires password confirmation).
     */
    public function reveal(Request $request, WalletConnection $connection)
    {
        // Validate password
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        $plaintext = $this->service->reveal($connection, $user);

        return response()->json([
            'data' => [
                'secret' => $plaintext,
                'masked_secret' => $connection->masked_secret,
                'revealed_at' => $connection->revealed_last_at,
            ],
            'message' => 'Secret revealed (will auto-hide after 30 seconds)',
        ]);
    }

    /**
     * Mark wallet connection as connected (support role only).
     */
    public function markConnected(Request $request, WalletConnection $connection)
    {
        $this->service->markConnected($connection, $request->user());

        return response()->json([
            'data' => [
                'id' => $connection->id,
                'status' => $connection->status,
            ],
            'message' => 'Wallet connection marked as connected',
        ]);
    }

    /**
     * Get BTCPay Store Settings URL for wallet connection (support role only).
     */
    public function getBtcPayStoreUrl(Request $request, WalletConnection $connection)
    {
        // Load store relationship if not already loaded
        if (!$connection->relationLoaded('store')) {
            $connection->load('store');
        }
        
        $store = $connection->store;
        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }
        
        $baseUrl = config('services.btcpay.base_url');
        
        return response()->json([
            'data' => [
                'url' => "{$baseUrl}/stores/{$store->btcpay_store_id}/lightning/BTC/setup",
                'store_id' => $store->btcpay_store_id,
                'store_name' => $store->name,
            ],
        ]);
    }

    /**
     * Test Lightning connection.
     * 
     * Validates connection string format and attempts to verify Lightning configuration.
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'connection_string' => ['required', 'string'],
            'crypto_code' => ['nullable', 'string', 'in:BTC,LTC'],
        ]);

        $store = $request->route('store');
        $cryptoCode = $request->input('crypto_code', 'BTC');

        // Get merchant API key
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $result = $this->lightningService->testConnection(
            $store->btcpay_store_id,
            $cryptoCode,
            $request->connection_string,
            $userApiKey
        );

        // Audit log
        AuditLog::log(
            'wallet_connection.test_connection',
            'wallet_connection',
            null,
            [
                'store_id' => $store->id,
                'crypto_code' => $cryptoCode,
                'success' => $result['success'] ?? false,
            ],
            $request->user()->id
        );

        Log::info('Lightning connection test performed', [
            'store_id' => $store->id,
            'crypto_code' => $cryptoCode,
            'success' => $result['success'] ?? false,
        ]);

        return response()->json($result);
    }

    /**
     * Configure Lightning node in BTCPay.
     * 
     * Attempts to configure Lightning node via BTCPay API.
     * If API doesn't support custom connection strings, stores in DB with 'needs_support' status.
     */
    public function configureLightning(Request $request)
    {
        $request->validate([
            'connection_string' => ['required', 'string'],
            'crypto_code' => ['nullable', 'string', 'in:BTC,LTC'],
        ]);

        $store = $request->route('store');
        $user = $request->user();
        $cryptoCode = $request->input('crypto_code', 'BTC');

        // Get merchant API key
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Try to configure via BTCPay API
        $result = $this->lightningService->connectLightningNode(
            $store->btcpay_store_id,
            $cryptoCode,
            $request->connection_string,
            $userApiKey
        );

        // If API doesn't support it, ensure connection is stored in DB
        if (isset($result['requires_manual_config']) && $result['requires_manual_config']) {
            // Find or create wallet connection in DB
            $connection = WalletConnection::where('store_id', $store->id)->first();
            if (!$connection) {
                // Determine type from connection string
                $type = 'blink'; // Default, could be improved to detect type
                if (strpos($request->connection_string, 'descriptor') !== false || 
                    strpos($request->connection_string, 'wpkh') !== false ||
                    strpos($request->connection_string, 'tr(') !== false) {
                    $type = 'aqua_descriptor';
                }

                $connection = $this->service->createOrUpdate(
                    $store,
                    $type,
                    $request->connection_string,
                    $user
                );
            }

            $result['connection_id'] = $connection->id;
            $result['status'] = $connection->status;
        }

        // Audit log
        AuditLog::log(
            'wallet_connection.configured',
            'wallet_connection',
            $result['connection_id'] ?? null,
            [
                'store_id' => $store->id,
                'crypto_code' => $cryptoCode,
                'success' => $result['success'] ?? false,
                'requires_manual_config' => $result['requires_manual_config'] ?? false,
            ],
            $user->id
        );

        Log::info('Lightning node configuration attempted', [
            'store_id' => $store->id,
            'crypto_code' => $cryptoCode,
            'success' => $result['success'] ?? false,
            'requires_manual_config' => $result['requires_manual_config'] ?? false,
        ]);

        return response()->json($result);
    }
}


