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
     * Reveal wallet connection secret for store owner (requires password confirmation).
     * Allows the merchant to view/edit their connection string when changing wallet.
     */
    public function revealForOwner(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $store = $request->route('store');
        $connection = WalletConnection::where('store_id', $store->id)->first();

        if (!$connection) {
            return response()->json(['message' => 'No wallet connection found for this store.'], 404);
        }

        $user = $request->user();
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        try {
            $plaintext = $this->service->reveal($connection, $user);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return response()->json([
                'message' => 'Unable to decrypt the stored secret. Please re-submit your wallet connection.',
            ], 500);
        }

        return response()->json([
            'data' => [
                'secret' => $plaintext,
                'type' => $connection->type,
                'masked_secret' => $connection->masked_secret,
            ],
        ]);
    }

    /**
     * Check if a descriptor is already in use by another store.
     * Used for frontend validation before submission.
     * Works for both existing stores and new stores (when store ID is 'new' or doesn't exist).
     */
    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'descriptor' => 'required|string',
            'type' => 'required|in:aqua_descriptor',
        ]);

        // Only check for aqua_descriptor type
        if ($request->type !== 'aqua_descriptor') {
            return response()->json([
                'duplicate' => false,
                'message' => null,
            ]);
        }

        // Get store from route, but handle case where store doesn't exist yet (for new stores)
        $store = $request->route('store');
        $storeId = null;
        
        // If store is 'new' or doesn't exist, use null to check against all stores
        if ($store && $store !== 'new' && is_object($store) && isset($store->id)) {
            $storeId = $store->id;
        }

        $result = $this->service->checkDescriptorDuplicate(
            $request->descriptor,
            $storeId ?? 'new' // Use 'new' as placeholder for non-existent stores
        );

        return response()->json([
            'duplicate' => $result['exists'],
            'existing_store_id' => $result['existing_store_id'],
            'existing_store_name' => $result['existing_store_name'],
            'message' => $result['exists'] 
                ? "This descriptor is already in use by store: {$result['existing_store_name']}. BTCPay allows each descriptor to be used only once. Please use a different wallet/descriptor."
                : null,
        ]);
    }

    /**
     * Check if a descriptor is already in use by another store (for new stores).
     * Used for frontend validation before store creation.
     */
    public function checkDuplicateNew(Request $request)
    {
        $request->validate([
            'descriptor' => 'required|string',
            'type' => 'required|in:aqua_descriptor',
        ]);

        // Only check for aqua_descriptor type
        if ($request->type !== 'aqua_descriptor') {
            return response()->json([
                'duplicate' => false,
                'message' => null,
            ]);
        }

        // For new stores, check against all existing stores
        $result = $this->service->checkDescriptorDuplicate(
            $request->descriptor,
            null // No current store ID for new stores
        );

        return response()->json([
            'duplicate' => $result['exists'],
            'existing_store_id' => $result['existing_store_id'],
            'existing_store_name' => $result['existing_store_name'],
            'message' => $result['exists'] 
                ? "This descriptor is already in use by store: {$result['existing_store_name']}. BTCPay allows each descriptor to be used only once. Please use a different wallet/descriptor."
                : null,
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
     * List wallet connections (support role only).
     * Query param: status = needs_support (default) | connected | pending | all
     * When status=needs_support, returns both 'pending' and 'needs_support' so the config bot can process new and failed connections.
     */
    public function indexSupport(Request $request)
    {
        $status = $request->query('status', 'needs_support');
        $query = WalletConnection::with(['store', 'submittedBy'])->orderBy('updated_at', 'desc');

        if ($status === 'all') {
            // no filter
        } elseif ($status === 'needs_support') {
            $query->whereIn('status', ['pending', 'needs_support']);
        } else {
            $query->where('status', $status);
        }

        $connections = $query->get();

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
                    'updated_at' => $connection->updated_at,
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

        try {
            $plaintext = $this->service->reveal($connection, $user);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return response()->json([
                'message' => 'Unable to decrypt the stored secret. This usually happens when APP_KEY was changed after the secret was saved. The merchant will need to re-submit their wallet connection.',
            ], 500);
        }

        $connection->loadMissing('store');
        $store = $connection->store;

        return response()->json([
            'data' => [
                'secret' => $plaintext,
                'type' => $connection->type,
                'btcpay_store_id' => $store?->btcpay_store_id,
                'store_name' => $store?->name,
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
     * Report config bot failure: set status to needs_support and send support notifications (support role / bot token).
     */
    public function botFailed(Request $request, WalletConnection $connection)
    {
        $error = $request->input('error', '');
        Log::info('Config bot reported failure', [
            'connection_id' => $connection->id,
            'store_id' => $connection->store_id,
            'error' => $error,
        ]);

        $this->service->markNeedsSupportAndNotify($connection);

        return response()->json([
            'data' => [
                'id' => $connection->id,
                'status' => $connection->fresh()->status,
            ],
            'message' => 'Bot failure recorded; support notified',
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
     * If successful, updates wallet connection status to 'connected'.
     * If API doesn't support custom connection strings, stores in DB with 'needs_support' status.
     * This method can be used to retry connection if automatic connection failed during store creation.
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

        // Find or create wallet connection in DB
        $connection = WalletConnection::where('store_id', $store->id)->first();
        if (!$connection) {
            // Determine type from connection string
            $type = 'blink'; // Default
            if (strpos($request->connection_string, 'ct(') !== false ||
                strpos($request->connection_string, 'wpkh') !== false ||
                strpos($request->connection_string, 'tr(') !== false ||
                strpos($request->connection_string, 'slip77') !== false) {
                $type = 'aqua_descriptor';
            }

            $connection = $this->service->createOrUpdate(
                $store,
                $type,
                $request->connection_string,
                $user
            );
        }

        // Try to configure via BTCPay API
        try {
            $result = $this->lightningService->connectLightningNode(
                $store->btcpay_store_id,
                $cryptoCode,
                $request->connection_string,
                $userApiKey
            );

            // If connection successful, update status
            if ($result['success'] ?? false) {
                $this->service->markConnected($connection, $user);
                $result['status'] = 'connected';
                $result['message'] = 'Lightning node connected successfully to BTCPay.';
                
                Log::info('Lightning node connected successfully via configureLightning', [
                    'store_id' => $store->id,
                    'wallet_connection_id' => $connection->id,
                    'crypto_code' => $cryptoCode,
                ]);
            } else {
                // Connection failed - ensure status is needs_support
                if ($connection->status !== 'needs_support') {
                    $connection->update(['status' => 'needs_support']);
                }
                $result['status'] = $connection->status;
                $result['message'] = $result['message'] ?? 'Failed to connect Lightning node. Support will configure it manually.';
                
                Log::info('Lightning node connection failed via configureLightning', [
                    'store_id' => $store->id,
                    'wallet_connection_id' => $connection->id,
                    'crypto_code' => $cryptoCode,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }

            $result['connection_id'] = $connection->id;
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // BTCPay API error
            $connection->update(['status' => 'needs_support']);
            
            $result = [
                'success' => false,
                'message' => 'Failed to connect Lightning node: ' . $e->getMessage(),
                'requires_manual_config' => true,
                'connection_id' => $connection->id,
                'status' => 'needs_support',
            ];

            Log::error('BTCPay API error when configuring Lightning node', [
                'store_id' => $store->id,
                'wallet_connection_id' => $connection->id,
                'crypto_code' => $cryptoCode,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
        } catch (\Exception $e) {
            // Other errors
            $connection->update(['status' => 'needs_support']);
            
            $result = [
                'success' => false,
                'message' => 'An error occurred while connecting Lightning node: ' . $e->getMessage(),
                'requires_manual_config' => true,
                'connection_id' => $connection->id,
                'status' => 'needs_support',
            ];

            Log::error('Unexpected error when configuring Lightning node', [
                'store_id' => $store->id,
                'wallet_connection_id' => $connection->id,
                'crypto_code' => $cryptoCode,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }

        // Audit log
        AuditLog::log(
            'wallet_connection.configured',
            'wallet_connection',
            $connection->id,
            [
                'store_id' => $store->id,
                'crypto_code' => $cryptoCode,
                'success' => $result['success'] ?? false,
                'requires_manual_config' => $result['requires_manual_config'] ?? false,
                'status' => $result['status'] ?? 'needs_support',
            ],
            $user->id
        );

        return response()->json($result);
    }
}


