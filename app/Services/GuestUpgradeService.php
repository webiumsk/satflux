<?php

namespace App\Services;

use App\Models\User;
use App\Services\BtcPay\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        $method = (string) $validated['method'];

        if ($method === 'lightning' && empty($user->lightning_public_key)) {
            throw ValidationException::withMessages([
                'method' => ['Link Lightning login first.'],
            ]);
        }

        if ($method === 'nostr' && empty($user->nostr_public_key)) {
            throw ValidationException::withMessages([
                'method' => ['Link Nostr login first.'],
            ]);
        }

        if ($method === 'email') {
            $newEmail = (string) $validated['email'];
            $newPassword = (string) $validated['password'];

            $user->forceFill([
                'email' => $newEmail,
                'password' => Hash::make($newPassword),
                'email_verified_at' => null,
                'is_guest' => false,
            ])->save();

            if (! empty($user->btcpay_user_id)) {
                try {
                    $this->userService->updateUser((string) $user->btcpay_user_id, [
                        'email' => $newEmail,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('BTCPay email update failed during guest upgrade', [
                        'user_id' => $user->id,
                        'btcpay_user_id' => $user->btcpay_user_id,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException('BTCPay email update failed.', 0, $e);
                }
            }

            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('Failed to send verification email after guest upgrade', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Unable to send verification email.', 0, $e);
            }
        } else {
            $user->forceFill([
                'is_guest' => false,
            ])->save();
        }

        return $user->fresh();
    }
}

