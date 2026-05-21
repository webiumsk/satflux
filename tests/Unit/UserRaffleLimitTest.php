<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRaffleLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_has_zero_raffle_limit(): void
    {
        $user = User::factory()->create(['is_guest' => true]);

        $this->assertSame(0, $user->getMaxRafflesPerStore());
    }

    public function test_free_user_has_one_raffle_limit(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        $this->assertSame(1, $user->getMaxRafflesPerStore());
    }

    public function test_active_pro_subscription_has_unlimited_raffles_even_with_free_role(): void
    {
        $plan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => [],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => 'free']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->assertTrue($user->hasActivePaidSubscription());
        $this->assertNull($user->getMaxRafflesPerStore());
    }

    public function test_pro_role_without_subscription_is_limited_to_one(): void
    {
        $user = User::factory()->create(['role' => 'pro']);

        $this->assertFalse($user->hasActivePaidSubscription());
        $this->assertSame(1, $user->getMaxRafflesPerStore());
    }
}
