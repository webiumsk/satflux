<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\StoreApiKeyService;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        // Everything that can fail on input is checked before the token is
        // claimed, so a bad request (e.g. unsafe callback) leaves it usable.
        $callbackUrl = $validated['callback_url'] ?? null;
        if ($callbackUrl !== null && app(OutboundUrlGuard::class)->pinnedOptions($callbackUrl) === null) {
            throw ValidationException::withMessages([
                'callback_url' => ['The callback URL must be a public HTTPS endpoint.'],
            ]);
        }

        $pending = Cache::get("eshop_token:{$validated['token']}");
        if (! is_array($pending)) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 400);
        }

        if ($pending['store_id'] !== $validated['store_id']) {
            Log::warning('E-shop token store_id mismatch', [
                'token_store_id' => $pending['store_id'],
                'request_store_id' => $validated['store_id'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Invalid token for this store',
            ], 400);
        }

        $store = Store::find($validated['store_id']);
        if (! $store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        // Atomic claim right before the key is minted: one request wins.
        $tokenData = $this->claimToken($validated['token']);
        if (! is_array($tokenData)) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 400);
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
        } catch (ValidationException $e) {
            throw $e;
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
        $tokenData = $this->claimToken($token);

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
     * Claim a one-time token: Cache::add is an atomic "set if absent" (SET NX
     * on Redis), so exactly one request wins even when several read the
     * token concurrently; Cache::pull alone is a separate get and forget.
     *
     * @return array<string, mixed>|null
     */
    protected function claimToken(string $token): ?array
    {
        $tokenData = Cache::get("eshop_token:{$token}");
        if (! is_array($tokenData)) {
            return null;
        }

        if (! Cache::add("eshop_token_claimed:{$token}", true, now()->addDay())) {
            return null;
        }

        Cache::forget("eshop_token:{$token}");

        return $tokenData;
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
