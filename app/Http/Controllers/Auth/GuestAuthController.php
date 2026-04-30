<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\BtcPay\WebhookService;
use App\Services\StoreChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestAuthController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected StoreService $storeService,
        protected WebhookService $webhookService
    ) {}

    /**
     * Create a fully provisioned guest account (local + BTCPay) and login immediately.
     */
    public function create(Request $request)
    {
        $validatedGuest = $request->validate([
            'recovery_public_key' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ]);
        $recoveryPkHex = isset($validatedGuest['recovery_public_key'])
            ? strtolower($validatedGuest['recovery_public_key'])
            : null;

        if ($request->user()) {
            $existingUser = $request->user();
            $existingStoreId = Store::where('user_id', $existingUser->id)->value('id');

            if (($existingUser->is_guest ?? false)
                && $recoveryPkHex
                && Schema::hasColumn('users', 'guest_recovery_public_key')
                && empty($existingUser->guest_recovery_public_key)) {
                if (User::where('guest_recovery_public_key', $recoveryPkHex)->where('id', '!=', $existingUser->id)->exists()) {
                    throw ValidationException::withMessages([
                        'recovery_public_key' => ['This recovery key is already in use.'],
                    ]);
                }
                $existingUser->update([
                    'guest_recovery_public_key' => $recoveryPkHex,
                    'guest_recovery_enrolled_at' => now(),
                ]);
                $existingUser = $existingUser->fresh();
            }

            return response()->json([
                'message' => 'Already authenticated.',
                'user' => $existingUser->makeVisible('role'),
                'store_id' => $existingStoreId,
            ]);
        }

        $guestToken = strtolower((string) Str::ulid());
        $guestEmailDomain = (string) config('services.auth.guest_email_domain', 'guest.satflux.local');
        $guestEmail = "guest+{$guestToken}@{$guestEmailDomain}";
        $guestPassword = Str::random(48);
        $defaultStoreName = 'My Store';

        try {
            [$user, $store] = DB::transaction(function () use ($guestEmail, $guestPassword, $defaultStoreName, $recoveryPkHex) {
                $userData = [
                    'name' => 'Guest',
                    'email' => $guestEmail,
                    'password' => Hash::make($guestPassword),
                    'role' => 'free',
                    'email_verified_at' => now(),
                ];

                // Backward compatibility: allow guest provisioning before the is_guest migration is applied.
                if (Schema::hasColumn('users', 'is_guest')) {
                    $userData['is_guest'] = true;
                }

                if ($recoveryPkHex && Schema::hasColumn('users', 'guest_recovery_public_key')) {
                    if (User::where('guest_recovery_public_key', $recoveryPkHex)->exists()) {
                        throw ValidationException::withMessages([
                            'recovery_public_key' => ['This recovery key is already in use.'],
                        ]);
                    }
                    $userData['guest_recovery_public_key'] = $recoveryPkHex;
                    $userData['guest_recovery_enrolled_at'] = now();
                }

                $user = User::create($userData);

                $btcpayUser = $this->userService->createUser([
                    'email' => $guestEmail,
                    'password' => Str::random(32),
                    'isAdministrator' => false,
                    'sendInvitationEmail' => false,
                ]);

                $btcpayUserId = $btcpayUser['id'] ?? $btcpayUser['userId'] ?? null;
                if (! $btcpayUserId) {
                    throw new \RuntimeException('BTCPay user ID missing after guest user creation.');
                }

                $apiKey = null;
                try {
                    $apiKeyData = $this->userService->createApiKey(
                        $btcpayUserId,
                        [],
                        [],
                        'satflux.io Guest API Key - '.$guestEmail
                    );
                    $apiKey = $apiKeyData['apiKey'] ?? null;
                } catch (\Throwable $apiKeyError) {
                    Log::warning('Guest BTCPay user API key creation failed, trying fallback', [
                        'btcpay_user_id' => $btcpayUserId,
                        'guest_email' => $guestEmail,
                        'error' => $apiKeyError->getMessage(),
                    ]);
                }

                if (! $apiKey) {
                    $serverApiKey = config('services.btcpay.api_key', env('BTCPAY_API_KEY'));
                    if (! $serverApiKey) {
                        throw new \RuntimeException('BTCPay API key missing after guest provisioning.');
                    }
                    $apiKey = $serverApiKey;
                    Log::warning('Using server-level BTCPay API key fallback for guest user', [
                        'btcpay_user_id' => $btcpayUserId,
                        'guest_email' => $guestEmail,
                    ]);
                }

                try {
                    $btcpayStore = $this->storeService->createStore([
                        'name' => $defaultStoreName,
                        'defaultCurrency' => 'EUR',
                        'timeZone' => 'Europe/Vienna',
                        'anyoneCanCreateInvoice' => false,
                        'showRecommendedFee' => true,
                        'recommendedFeeBlockTarget' => 1,
                        'preferredExchange' => 'kraken',
                    ], $apiKey);
                } catch (\Throwable $storeCreateError) {
                    $serverApiKey = config('services.btcpay.api_key', env('BTCPAY_API_KEY'));
                    if (! $serverApiKey) {
                        throw $storeCreateError;
                    }
                    Log::warning('Retrying guest BTCPay store creation with server-level API key', [
                        'btcpay_user_id' => $btcpayUserId,
                        'guest_email' => $guestEmail,
                        'error' => $storeCreateError->getMessage(),
                    ]);
                    $apiKey = $serverApiKey;
                    $btcpayStore = $this->storeService->createStore([
                        'name' => $defaultStoreName,
                        'defaultCurrency' => 'EUR',
                        'timeZone' => 'Europe/Vienna',
                        'anyoneCanCreateInvoice' => false,
                        'showRecommendedFee' => true,
                        'recommendedFeeBlockTarget' => 1,
                        'preferredExchange' => 'kraken',
                    ], null);
                }

                $btcpayStoreId = $btcpayStore['id'] ?? $btcpayStore['storeId'] ?? null;
                if (! $btcpayStoreId) {
                    throw new \RuntimeException('BTCPay store ID missing after guest store creation.');
                }

                Cache::forget("btcpay:store:{$btcpayStoreId}:server");
                Cache::forget("btcpay:store:{$btcpayStoreId}:".md5($apiKey));

                try {
                    $this->storeService->addUserToStore($btcpayStoreId, $btcpayUserId, 'Owner');
                } catch (\Throwable $e) {
                    Log::warning('Failed to add guest BTCPay user to guest store', [
                        'btcpay_store_id' => $btcpayStoreId,
                        'btcpay_user_id' => $btcpayUserId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $store = Store::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'btcpay_store_id' => $btcpayStoreId,
                    'name' => $defaultStoreName,
                    'default_currency' => 'EUR',
                    'timezone' => 'Europe/Vienna',
                    'preferred_exchange' => 'kraken',
                    'wallet_type' => null,
                ]);
                StoreChecklistService::ensureChecklistInitialized($store);

                try {
                    $data = $this->webhookService->replacePanelWebhookForStore($btcpayStoreId, null);
                    $store->update([
                        'btcpay_webhook_id' => $data['id'] ?? null,
                        'webhook_secret' => $data['secret'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Guest store webhook provisioning failed', [
                        'store_id' => $store->id,
                        'btcpay_store_id' => $btcpayStoreId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $user->update([
                    'btcpay_user_id' => $btcpayUserId,
                    'btcpay_api_key' => $apiKey,
                ]);

                return [$user->fresh(), $store->fresh()];
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Guest account provisioning failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Unable to start guest session right now. Please try again.',
            ], 503);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'message' => 'Guest session started.',
            'user' => $user->makeVisible('role'),
            'store_id' => $store->id,
        ], 201);
    }

    /**
     * Start a guest recovery challenge (sign the returned message with the same key as enrollment).
     */
    public function recoveryChallenge(Request $request): \Illuminate\Http\JsonResponse
    {
        $nonce = bin2hex(random_bytes(32));
        $challengeId = (string) Str::uuid();
        Cache::put(
            'guest_recovery_challenge:'.$challengeId,
            ['nonce' => $nonce],
            now()->addMinutes(5),
        );

        return response()->json([
            'data' => [
                'challenge_id' => $challengeId,
                'nonce' => $nonce,
            ],
        ]);
    }

    /**
     * Complete guest recovery: verify Ed25519 signature and start a session for the matching guest user.
     */
    public function recoveryRestore(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! extension_loaded('sodium')) {
            return response()->json(['message' => 'Server crypto unavailable.'], 503);
        }

        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'recovery_public_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'signature' => ['required', 'string', 'regex:/^[a-f0-9]{128}$/i'],
        ]);

        $cacheKey = 'guest_recovery_challenge:'.$validated['challenge_id'];
        $payload = Cache::pull($cacheKey);
        if (! is_array($payload) || empty($payload['nonce']) || ! is_string($payload['nonce'])) {
            return response()->json(['message' => 'Invalid or expired challenge.'], 422);
        }

        $pkHex = strtolower($validated['recovery_public_key']);
        $pk = @hex2bin($pkHex);
        $sig = @hex2bin(strtolower($validated['signature']));
        if ($pk === false || $sig === false || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return response()->json(['message' => 'Invalid encoding.'], 422);
        }

        $nonce = $payload['nonce'];
        $message = 'satflux:guest-recovery:v1|'.$validated['challenge_id'].'|'.$nonce;

        if (! sodium_crypto_sign_verify_detached($sig, $message, $pk)) {
            return response()->json(['message' => 'Invalid signature.'], 422);
        }

        $user = User::query()
            ->where('guest_recovery_public_key', $pkHex)
            ->where('is_guest', true)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'No guest account found for this recovery key.'], 404);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->update(['last_login_at' => now()]);
        $storeId = Store::where('user_id', $user->id)->value('id');

        return response()->json([
            'message' => 'Guest session restored.',
            'user' => $user->fresh()->makeVisible('role'),
            'store_id' => $storeId,
        ]);
    }
}
