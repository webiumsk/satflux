<?php

namespace Tests\Unit\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $this->service = app(SubscriptionService::class);
    }

    /** @test */
    public function activate_subscription_creates_new_subscription_and_updates_user_role(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        $subscription = $this->service->activateSubscription($user, 'pro', 'btcpay-sub-001');

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('btcpay-sub-001', $subscription->btcpay_subscription_id);
        $this->assertTrue($subscription->expires_at->isFuture());

        // User role should be updated within the same transaction
        $user->refresh();
        $this->assertSame('pro', $user->role);
        $this->assertSame('btcpay-sub-001', $user->btcpay_subscription_id);
    }

    /** @test */
    public function activate_subscription_extends_existing_subscription_of_same_plan(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $proPlan = SubscriptionPlan::where('code', 'pro')->first();

        // Create an existing active subscription
        $existing = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonths(6),
            'grace_ends_at' => now()->addMonths(6)->addDays(14),
        ]);

        $originalExpiry = $existing->expires_at->copy();

        $subscription = $this->service->activateSubscription($user, 'pro', 'btcpay-sub-002');

        // Should return the same subscription, extended
        $this->assertSame($existing->id, $subscription->id);
        $this->assertTrue($subscription->expires_at->greaterThan($originalExpiry));
        $this->assertSame('btcpay-sub-002', $subscription->btcpay_subscription_id);

        // Should NOT create a second subscription
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    /** @test */
    public function activate_subscription_is_idempotent_with_same_btcpay_subscription_id(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        // First call creates subscription
        $first = $this->service->activateSubscription($user, 'pro', 'btcpay-sub-duplicate');
        $this->assertSame('active', $first->status);

        // Second call with same btcpay_subscription_id returns existing (idempotent)
        $second = $this->service->activateSubscription($user, 'pro', 'btcpay-sub-duplicate');
        $this->assertSame($first->id, $second->id);

        // Only 1 subscription should exist
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    /** @test */
    public function activate_subscription_without_btcpay_id_still_works(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        $subscription = $this->service->activateSubscription($user, 'pro');

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertNull($subscription->btcpay_subscription_id);
        $this->assertSame('active', $subscription->status);

        $user->refresh();
        $this->assertSame('pro', $user->role);
    }

    /** @test */
    public function activate_subscription_creates_different_plan_when_existing_is_different(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $proPlan = SubscriptionPlan::where('code', 'pro')->first();

        // Existing pro subscription
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'grace_ends_at' => now()->addYear()->addDays(14),
        ]);

        // Upgrade to enterprise creates a new subscription
        $subscription = $this->service->activateSubscription($user, 'enterprise', 'btcpay-sub-ent');

        $this->assertSame('active', $subscription->status);
        $this->assertSame(2, Subscription::where('user_id', $user->id)->count());

        $user->refresh();
        $this->assertSame('enterprise', $user->role);
    }

    /** @test */
    public function activate_subscription_throws_for_invalid_plan(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Subscription plan 'nonexistent' not found.");

        $this->service->activateSubscription($user, 'nonexistent');
    }

    /** @test */
    public function activate_subscription_sets_grace_period_14_days_after_expiry(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        $subscription = $this->service->activateSubscription($user, 'pro', 'btcpay-sub-grace');

        $expectedGrace = $subscription->expires_at->copy()->addDays(14);
        $this->assertTrue($subscription->grace_ends_at->equalTo($expectedGrace));
    }

    /** @test */
    public function ensure_free_subscription_creates_one_if_none_exists(): void
    {
        $user = User::factory()->create(['role' => 'free']);

        $subscription = $this->service->ensureFreeSubscription($user);

        $this->assertSame('active', $subscription->status);
        $this->assertSame('free', $subscription->plan->code);
    }

    /** @test */
    public function ensure_free_subscription_returns_existing_active_subscription(): void
    {
        $user = User::factory()->create(['role' => 'pro']);

        // Create existing active pro subscription
        $proPlan = SubscriptionPlan::where('code', 'pro')->first();
        $existing = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        // ensureFreeSubscription should return the existing active (pro) subscription
        $subscription = $this->service->ensureFreeSubscription($user);

        $this->assertSame($existing->id, $subscription->id);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }
}
