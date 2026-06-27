<?php

namespace App\Services;

use App\Jobs\SendVerificationEmailJob;
use App\Jobs\SyncBtcpayEmailJob;
use App\Models\User;
use App\Services\BtcPay\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GuestUpgradeService
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function upgrade(User $user, array $validated): User
    {
        $newEmail = strtolower(trim((string) $validated['email']));
        $plainPassword = isset($validated['password']) ? trim((string) $validated['password']) : '';
        $passwordHash = $plainPassword !== ''
            ? Hash::make($plainPassword)
            : Hash::make(Str::random(64));

        $user->forceFill([
            'email' => $newEmail,
            'password' => $passwordHash,
            'email_verified_at' => null,
            'is_guest' => false,
            'allows_satflux_email_changes' => true,
        ])->save();

        if (! empty($user->btcpay_api_key)) {
            try {
                $this->userService->updateCurrentUserProfile($user->getBtcPayApiKeyOrFail(), [
                    'email' => $newEmail,
                ]);
            } catch (\Throwable $e) {
                Log::warning('BTCPay email update failed during guest upgrade (users/me)', [
                    'user_id' => $user->id,
                    'btcpay_user_id' => $user->btcpay_user_id,
                    'error' => $e->getMessage(),
                ]);
                $this->dispatchBtcpayEmailSyncSafely($user->id);
            }
        } elseif (! empty($user->btcpay_user_id)) {
            try {
                $this->userService->updateUser((string) $user->btcpay_user_id, [
                    'email' => $newEmail,
                ]);
            } catch (\Throwable $e) {
                Log::warning('BTCPay email update failed during guest upgrade (legacy users/{id})', [
                    'user_id' => $user->id,
                    'btcpay_user_id' => $user->btcpay_user_id,
                    'error' => $e->getMessage(),
                ]);
                $this->dispatchBtcpayEmailSyncSafely($user->id);
            }
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('Failed to send verification email after guest upgrade', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            SendVerificationEmailJob::dispatch($user->id);
        }

        return $user->fresh();
    }

    /**
     * Queue a BTCPay email sync; swallow sync-driver failures so the Satflux upgrade still returns success.
     */
    private function dispatchBtcpayEmailSyncSafely(int $userId): void
    {
        try {
            SyncBtcpayEmailJob::dispatch($userId);
        } catch (\Throwable $e) {
            Log::warning('SyncBtcpayEmailJob dispatch/run failed after guest upgrade', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
