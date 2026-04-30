<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GuestRecoveryService
{
    /**
     * @return array{challenge_id: string, nonce: string}
     */
    public function createChallenge(): array
    {
        $nonce = bin2hex(random_bytes(32));
        $challengeId = (string) Str::uuid();

        Cache::put(
            'guest_recovery_challenge:'.$challengeId,
            ['nonce' => $nonce],
            now()->addMinutes(5),
        );

        return [
            'challenge_id' => $challengeId,
            'nonce' => $nonce,
        ];
    }

    /**
     * @param  array<string, string>  $validated
     * @return array{user: User, store_id: string|null}
     */
    public function restoreFromChallenge(array $validated): array
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('Server crypto unavailable.');
        }

        $cacheKey = 'guest_recovery_challenge:'.$validated['challenge_id'];
        $payload = Cache::pull($cacheKey);
        if (! is_array($payload) || empty($payload['nonce']) || ! is_string($payload['nonce'])) {
            throw new \InvalidArgumentException('Invalid or expired challenge.');
        }

        $pkHex = strtolower($validated['recovery_public_key']);
        $pk = @hex2bin($pkHex);
        $sig = @hex2bin(strtolower($validated['signature']));

        if ($pk === false || $sig === false || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new \InvalidArgumentException('Invalid encoding.');
        }

        $nonce = $payload['nonce'];
        $message = 'satflux:guest-recovery:v1|'.$validated['challenge_id'].'|'.$nonce;
        if (! sodium_crypto_sign_verify_detached($sig, $message, $pk)) {
            throw new \InvalidArgumentException('Invalid signature.');
        }

        $user = User::query()
            ->where('guest_recovery_public_key', $pkHex)
            ->where('is_guest', true)
            ->first();

        if (! $user) {
            throw new \DomainException('No guest account found for this recovery key.');
        }

        Auth::login($user);
        $user->update(['last_login_at' => now()]);
        $storeId = Store::where('user_id', $user->id)->value('id');

        return [
            'user' => $user->fresh(),
            'store_id' => $storeId,
        ];
    }
}

