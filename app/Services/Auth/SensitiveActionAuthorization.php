<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Re-auth for sensitive actions (wallet secret reveal, Cashu edit unlock).
 *
 * Legacy email/password accounts must confirm with password or LNURL/Nostr.
 * Recovery-phrase accounts have no password; an authenticated Sanctum session is sufficient.
 */
class SensitiveActionAuthorization
{
    public static function allowed(User $user, Request $request): bool
    {
        if ($request->filled('password')) {
            return Hash::check($request->password, $user->password);
        }

        if (! $user->canUsePasswordLogin()) {
            return true;
        }

        $cacheKey = 'reveal_confirmed:'.$user->id;
        if (! Cache::get($cacheKey)) {
            return false;
        }

        if ($request->boolean('confirm_via_lnurl') && $user->lightning_public_key) {
            Cache::forget($cacheKey);

            return true;
        }

        if ($request->boolean('confirm_via_nostr') && $user->nostr_public_key) {
            Cache::forget($cacheKey);

            return true;
        }

        return false;
    }

    public static function assertAllowed(User $user, Request $request): void
    {
        if (self::allowed($user, $request)) {
            return;
        }

        throw ValidationException::withMessages([
            'password' => [__('auth.invalid_password_or_confirm_lnurl')],
        ]);
    }
}
