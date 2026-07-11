<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Re-auth for sensitive actions (wallet secret reveal, Cashu edit unlock).
 *
 * Legacy email/password accounts must confirm with their password.
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
