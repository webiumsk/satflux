<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegistrationService
{
    /**
     * Create or refresh an unverified user and dispatch Registered (verification email via listener).
     *
     * @throws ValidationException
     */
    public function register(string $email, string $plainPassword): User
    {
        return DB::transaction(function () use ($email, $plainPassword) {
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                if ($existingUser->hasVerifiedEmail()) {
                    throw ValidationException::withMessages([
                        'email' => ['The email has already been taken.'],
                    ]);
                }

                $user = $existingUser;
                $user->update([
                    'password' => Hash::make($plainPassword),
                ]);
            } else {
                $user = User::create([
                    'email' => $email,
                    'password' => Hash::make($plainPassword),
                ]);
            }

            try {
                event(new Registered($user));
            } catch (TransportExceptionInterface $e) {
                Log::warning('Failed to send verification email during registration', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
                throw ValidationException::withMessages([
                    'email' => [__('messages.verification_email_failed')],
                ]);
            }

            return $user->fresh();
        });
    }
}
