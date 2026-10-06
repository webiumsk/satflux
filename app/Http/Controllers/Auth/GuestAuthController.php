<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Compliance\ComplianceGate;
use App\Services\GuestProvisioningService;
use App\Services\GuestRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GuestAuthController extends Controller
{
    public function __construct(
        protected GuestProvisioningService $guestProvisioningService,
        protected GuestRecoveryService $guestRecoveryService,
        protected ComplianceGate $complianceGate,
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
            $existingStoreId = $this->guestProvisioningService->resolvePrimaryStoreId($existingUser);

            // Guest signup never mutates an existing session's account: a stale
            // session (signed in in another tab) would otherwise receive the new
            // phrase and lose password login. Only a retry with the account's own
            // key is answered with the existing account; enrollment has its own
            // endpoint (POST /account/recovery-key).
            if ($recoveryPkHex && ! hash_equals(strtolower((string) $existingUser->guest_recovery_public_key), $recoveryPkHex)) {
                return response()->json([
                    'message' => 'You are already signed in to another account. Sign out before creating a new account.',
                    'code' => 'already_authenticated',
                ], 409);
            }

            return response()->json([
                'message' => 'Already authenticated.',
                'user' => $existingUser->makeVisible('role'),
                'store_id' => $existingStoreId,
            ]);
        }

        try {
            $guestEmail = $this->guestProvisioningService->generateGuestEmail();
            $this->complianceGate->assertRegistrationAllowed($request, $guestEmail, 'Guest');

            [$user, $store] = $this->guestProvisioningService->provisionGuest($recoveryPkHex, $guestEmail);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Guest account provisioning failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $payload = [
                'message' => 'Unable to start guest session right now. Please try again.',
                'code' => 'guest_provisioning_failed',
            ];
            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            return response()->json($payload, 503);
        }

        Auth::login($user);
        $this->complianceGate->linkLatestRegistrationScreening($user->email, $user);

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
     * Enroll a recovery public key on the signed-in account (guest backup or
     * legacy email account migration). Enrolling disables password login, so
     * it is a deliberate, authenticated action - never a side effect of signup.
     */
    public function enrollRecoveryKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recovery_public_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ]);

        $user = $this->guestProvisioningService->attachRecoveryKey(
            $request->user(),
            strtolower($validated['recovery_public_key']),
        );

        if ($user->wasChanged('guest_recovery_public_key')) {
            // target_id is a uuid column - the user id goes into user_id only.
            AuditLog::log('account.recovery_key_enrolled', 'user', null, [
                'is_guest' => (bool) $user->is_guest,
            ], $user->id);
        }

        return response()->json([
            'message' => 'Recovery phrase enrolled.',
            'user' => $user->makeVisible('role'),
        ]);
    }

    /**
     * Start a guest recovery challenge (sign the returned message with the same key as enrollment).
     */
    public function recoveryChallenge(): JsonResponse
    {
        $challenge = $this->guestRecoveryService->createChallenge();

        return response()->json([
            'data' => [
                'challenge_id' => $challenge['challenge_id'],
                'nonce' => $challenge['nonce'],
            ],
        ]);
    }

    /**
     * Complete guest recovery: verify Ed25519 signature and start a session for the matching guest user.
     */
    public function recoveryRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'recovery_public_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'signature' => ['required', 'string', 'regex:/^[a-f0-9]{128}$/i'],
        ]);

        try {
            $result = $this->guestRecoveryService->restoreFromChallenge($validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Guest session restored.',
            'user' => $result['user']->makeVisible('role'),
            'store_id' => $result['store_id'],
        ]);
    }
}
