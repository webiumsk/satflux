<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SubscriptionCheckoutRegistry
{
    public function bind(string $checkoutId, int $userId, string $plan): void
    {
        Cache::put($this->cacheKey($checkoutId), [
            'user_id' => $userId,
            'plan' => $plan,
        ], now()->addDays(7));
    }

    /**
     * @return array{user_id: int, plan: string}|null
     */
    public function resolve(string $checkoutId): ?array
    {
        $payload = Cache::get($this->cacheKey($checkoutId));

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['plan'])) {
            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'plan' => (string) $payload['plan'],
        ];
    }

    protected function cacheKey(string $checkoutId): string
    {
        return 'subscription_checkout:'.hash('sha256', $checkoutId);
    }
}
