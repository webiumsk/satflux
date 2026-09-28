<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\StoreApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EshopIntegrationController extends Controller
{
    protected StoreApiKeyService $storeApiKeyService;

    public function __construct(StoreApiKeyService $storeApiKeyService)
    {
        $this->storeApiKeyService = $storeApiKeyService;
    }

    /**
     * Connect e-shop and generate API key via token.
     * POST /api/public/eshop/connect
     */
    public function connect(Request $request)
    {
        $validated = $request->validate([
            'store_id' => ['required', 'string'], // Local store UUID
            'token' => ['required', 'string'],
            'callback_url' => ['nullable', 'url:https', 'max:500'],
        ]);

        // Consume the token atomically: a get-then-forget would let two
        // concurrent requests mint two keys from one token.
        $tokenKey = "eshop_token:{$validated['token']}";
        $tokenData = Cache::pull($tokenKey);

        if (! is_array($tokenData)) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 400);
        }

        // Validate store ID matches token
        if ($tokenData['store_id'] !== $validated['store_id']) {
            Log::warning('E-shop token store_id mismatch', [
                'token_store_id' => $tokenData['store_id'],
                'request_store_id' => $validated['store_id'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Invalid token for this store',
            ], 400);
        }

        // Load store
        $store = Store::find($validated['store_id']);
        if (! $store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        try {
            // Generate API key
            $apiKey = $this->storeApiKeyService->generateApiKey(
                $store->id,
                $tokenData['permissions'] ?? [],
                $tokenData['label'] ?? 'E-shop Integration',
                $validated['callback_url'] ?? null
            );

            Log::info('E-shop API key created via public endpoint', [
                'store_id' => $store->id,
                'api_key_id' => $apiKey->id,
                'has_callback_url' => ! empty($validated['callback_url']),
                'ip' => $request->ip(),
            ]);

            // If callback URL was provided, API key was already sent there
            // Otherwise return it directly
            if (empty($validated['callback_url'])) {
                return response()->json([
                    'data' => [
                        'api_key' => $apiKey->btcpay_api_key,
                        'store_id' => $store->btcpay_store_id,
                        'permissions' => $apiKey->permissions,
                        'label' => $apiKey->label,
                    ],
                    'message' => 'API key created successfully',
                ]);
            }

            return response()->json([
                'message' => 'API key created and sent to callback URL',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create e-shop API key via public endpoint', [
                'store_id' => $validated['store_id'],
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Could not create the API key. Please generate a new token and try again.',
            ], 500);
        }
    }

    /**
     * Get API key via one-time token.
     * GET /api/public/eshop/token/{token}
     */
    public function getToken(Request $request, string $token)
    {
        // One-shot exchange: the token mints its own key. It never hands out
        // an existing key (matching by label returned whatever key happened
        // to carry the default label).
        $tokenData = Cache::pull("eshop_token:{$token}");

        if (! is_array($tokenData)) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 400);
        }

        $store = Store::find($tokenData['store_id']);
        if (! $store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        try {
            $apiKey = $this->storeApiKeyService->generateApiKey(
                $store->id,
                $tokenData['permissions'] ?? [],
                $tokenData['label'] ?? 'E-shop Integration',
            );
        } catch (\Exception $e) {
            Log::error('Failed to create e-shop API key via token exchange', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Could not create the API key. Please generate a new token and try again.',
            ], 500);
        }

        Log::info('E-shop API key created via token exchange', [
            'store_id' => $store->id,
            'api_key_id' => $apiKey->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'api_key' => $apiKey->btcpay_api_key,
                'store_id' => $store->btcpay_store_id,
                'permissions' => $apiKey->permissions,
                'label' => $apiKey->label,
            ],
        ]);
    }

    /**
     * Generate a one-time token for e-shop integration.
     * This is called from the authenticated StoreApiKeyController or can be a helper method.
     */
    public static function generateToken(string $storeId, array $permissions = [], string $label = 'E-shop Integration', int $expirationMinutes = 60): string
    {
        $token = Str::random(64);
        $tokenKey = "eshop_token:{$token}";

        Cache::put($tokenKey, [
            'store_id' => $storeId,
            'permissions' => $permissions,
            'label' => $label,
            'created_at' => now()->toISOString(),
        ], now()->addMinutes($expirationMinutes));

        return $token;
    }
}
