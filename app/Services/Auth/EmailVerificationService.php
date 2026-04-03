<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    /**
     * Full signed URL for the SPA email-verification route (query params match the API route for signature checks).
     */
    public function signedVerificationUrlForUser(User $user): string
    {
        $baseUrl = rtrim((string) config('app.url', env('APP_URL', 'http://localhost:8080')), '/');

        $relative = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1((string) $user->email),
            ],
            false
        );

        $spaRelative = str_replace('/api/auth/verify-email/', '/auth/verify-email/', $relative);

        return $baseUrl.$spaRelative;
    }
}
