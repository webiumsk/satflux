<?php

namespace App\Support\Legal;

use App\Models\User;

class LegalConsent
{
    /**
     * @return array<string, list<string>>
     */
    public static function registrationRules(): array
    {
        return [
            'privacy_consent' => ['required', 'accepted'],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function privacyOnlyRules(): array
    {
        return [
            'privacy_consent' => ['required', 'accepted'],
        ];
    }

    public static function recordRegistration(User $user): void
    {
        $now = now();
        $user->forceFill([
            'privacy_consent_at' => $now,
            'terms_accepted_at' => $now,
        ])->save();
    }

    public static function recordPrivacy(User $user): void
    {
        $user->forceFill([
            'privacy_consent_at' => now(),
        ])->save();
    }
}
