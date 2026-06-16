<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserRoleSubscriptionSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_assigned_pro_role_grants_active_pro_subscription(): void
    {
        $freePlan = $this->createPlan('free', []);
        $proPlan = $this->createPlan('pro', ['business_invoicing']);

        $user = User::factory()->create(['role' => 'free']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYears(10),
        ]);

        $user->role = 'pro';
        $user->save();

        app(SubscriptionService::class)->syncSubscriptionForAdminRole($user, 'pro');

        $user->refresh();

        $this->assertTrue(app(SubscriptionService::class)->canUseBusinessInvoicing($user));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function admin_assigned_free_role_expires_paid_subscription(): void
    {
        $freePlan = $this->createPlan('free', []);
        $proPlan = $this->createPlan('pro', ['business_invoicing']);

        $user = User::factory()->create(['role' => 'pro']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'billing_phase' => Subscription::BILLING_PAID,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'grace_ends_at' => now()->addYear()->addDays(30),
        ]);

        $user->role = 'free';
        $user->save();

        app(SubscriptionService::class)->syncSubscriptionForAdminRole($user, 'free');

        $user->refresh();

        $this->assertFalse(app(SubscriptionService::class)->canUseBusinessInvoicing($user));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  list<string>  $features
     */
    protected function createPlan(string $code, array $features): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'code' => $code,
            'name' => $code,
            'display_name' => ucfirst($code),
            'price_eur' => $code === 'free' ? 0 : 99,
            'billing_period' => 'year',
            'max_stores' => $code === 'free' ? 1 : 3,
            'max_api_keys' => $code === 'free' ? 1 : 3,
            'max_ln_addresses' => $code === 'free' ? 1 : null,
            'features' => $features,
            'is_active' => true,
        ]);
    }
}
