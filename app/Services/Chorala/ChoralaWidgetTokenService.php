<?php

namespace App\Services\Chorala;

use App\Models\User;
use Firebase\JWT\JWT;

class ChoralaWidgetTokenService
{
    public function isConfigured(): bool
    {
        $projectKey = config('services.chorala.project_key');
        $secret = config('services.chorala.end_user_jwt_secret');

        return is_string($projectKey) && $projectKey !== ''
            && is_string($secret) && $secret !== '';
    }

    public function createTokenForUser(User $user): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $secret = (string) config('services.chorala.end_user_jwt_secret');
        $subscription = $user->currentSubscription();
        $plan = $user->currentSubscriptionPlan();

        $segment = array_filter([
            'plan' => $plan?->code,
            'status' => $subscription?->status,
            'is_guest' => (bool) ($user->is_guest ?? false),
        ], static fn ($value) => $value !== null && $value !== '');

        $payload = [
            'id' => (string) $user->id,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        if (is_string($user->email) && $user->email !== '') {
            $payload['email'] = $user->email;
        }

        if (is_string($user->name) && $user->name !== '') {
            $payload['name'] = $user->name;
        }

        if ($segment !== []) {
            $payload['segment'] = $segment;
        }

        return JWT::encode($payload, $secret, 'HS256');
    }
}
