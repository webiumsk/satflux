<?php

namespace Tests\Feature;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\CompanySlotPurchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\BtcPay\BtcPayClient;
use App\Services\SubscriptionCheckoutRegistry;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Each settled BTCPay invoice grants paid time exactly once, whichever path
 * (success redirect, webhook, PlanStarted) observes it first.
 */
class SubscriptionPaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const STORE = 'sub-store-1';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.btcpay.base_url' => 'https://btcpay.example.test',
            'services.btcpay.api_key' => 'test-server-key',
            'services.btcpay.subscription_store_id' => self::STORE,
            'services.btcpay.subscription_plans' => ['pro' => 'plan_pro_test'],
        ]);
        $this->app->forgetInstance(BtcPayClient::class);

        SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $invoices  invoiceId => invoice payload
     */
    private function fakeBtcPay(array $invoices, ?array $checkout = null): void
    {
        Http::fake(function ($request) use ($invoices, $checkout) {
            $url = (string) $request->url();
            if ($checkout && str_contains($url, '/api/v1/plan-checkout/'.$checkout['id'])) {
                return Http::response($checkout);
            }
            foreach ($invoices as $id => $invoice) {
                if (str_contains($url, '/invoices/'.$id)) {
                    return Http::response(array_merge(['id' => $id], $invoice));
                }
            }
            if (str_contains($url, '/invoices') && str_contains(urldecode($url), '@')) {
                // Text search by buyer email: an old settled invoice of the same buyer.
                return Http::response([['id' => 'inv_old_by_email', 'status' => 'Settled']]);
            }
            if (str_contains($url, '/invoices')) {
                return Http::response([]);
            }

            return Http::response([], 404);
        });
    }

    private function runWebhook(string $type, string $invoiceId, array $metadata): void
    {
        $event = WebhookEvent::create([
            'event_type' => $type,
            'payload' => [
                'storeId' => self::STORE,
                'invoiceId' => $invoiceId,
                'metadata' => $metadata,
            ],
            'verified' => true,
        ]);

        (new ProcessBtcPayWebhook($event))->handle();
    }

    private function subscriptionMetadata(User $user): array
    {
        return [
            'customerEmail' => $user->email,
            'planId' => 'plan_pro_test',
            'subscriptionId' => 'btcpay-sub-1',
        ];
    }

    private function expiresAt(User $user): int
    {
        return Subscription::where('user_id', $user->id)->firstOrFail()->expires_at->timestamp;
    }

    private function paidCheckout(User $user, string $invoiceId): array
    {
        app(SubscriptionCheckoutRegistry::class)->bind('checkout_1', $user->id, 'pro');

        return [
            'id' => 'checkout_1',
            'invoiceId' => $invoiceId,
            'plan' => ['id' => 'plan_pro_test'],
            'subscriber' => ['customer' => ['id' => 'btcpay-sub-1', 'identities' => ['Email' => $user->email]]],
        ];
    }

    #[Test]
    public function replaying_the_success_redirect_does_not_extend_again(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        $this->fakeBtcPay(
            ['inv_1' => ['status' => 'Settled', 'createdTime' => now()->subMinute()->timestamp]],
            $this->paidCheckout($user, 'inv_1'),
        );

        $this->actingAs($user)->getJson('/api/subscriptions/success?checkoutPlanId=checkout_1')
            ->assertOk()->assertJsonPath('activated', true);
        $first = $this->expiresAt($user);

        $this->travel(5)->minutes();
        $this->actingAs($user)->getJson('/api/subscriptions/success?checkoutPlanId=checkout_1')->assertOk();
        $this->actingAs($user)->getJson('/api/subscriptions/success?checkoutPlanId=checkout_1')->assertOk();

        $this->assertSame($first, $this->expiresAt($user));
    }

    #[Test]
    public function webhooks_after_the_success_redirect_do_not_extend_again(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        $this->fakeBtcPay(
            ['inv_1' => ['status' => 'Settled', 'createdTime' => now()->subMinute()->timestamp]],
            $this->paidCheckout($user, 'inv_1'),
        );

        $this->actingAs($user)->getJson('/api/subscriptions/success?checkoutPlanId=checkout_1')->assertOk();
        $first = $this->expiresAt($user);

        $this->runWebhook('InvoiceReceivedPayment', 'inv_1', $this->subscriptionMetadata($user));
        $this->runWebhook('InvoiceSettled', 'inv_1', $this->subscriptionMetadata($user));

        $this->assertSame($first, $this->expiresAt($user));
    }

    #[Test]
    public function payment_on_an_unsettled_invoice_grants_nothing(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        CompanySlotPurchase::create([
            'user_id' => $user->id,
            'slots' => 5,
            'price_sats' => 120_000,
            'btcpay_invoice_id' => 'inv_pack',
            'status' => CompanySlotPurchase::STATUS_PENDING,
        ]);
        $this->fakeBtcPay([
            'inv_sub' => ['status' => 'New', 'additionalStatus' => 'PaidPartial'],
            'inv_pack' => ['status' => 'New', 'additionalStatus' => 'PaidPartial'],
        ]);

        $this->runWebhook('InvoiceReceivedPayment', 'inv_sub', $this->subscriptionMetadata($user));
        $this->runWebhook('InvoiceReceivedPayment', 'inv_pack', [
            'purpose' => 'company_slot_pack',
            'userId' => (string) $user->id,
        ]);

        $this->assertSame('free', $user->fresh()->role);
        $this->assertSame(0, Subscription::where('user_id', $user->id)->count());
        $this->assertSame(CompanySlotPurchase::STATUS_PENDING, CompanySlotPurchase::first()->status);
    }

    #[Test]
    public function settled_invoice_fulfils_a_pack(): void
    {
        $user = User::factory()->create();
        CompanySlotPurchase::create([
            'user_id' => $user->id,
            'slots' => 5,
            'price_sats' => 120_000,
            'btcpay_invoice_id' => 'inv_pack',
            'status' => CompanySlotPurchase::STATUS_PENDING,
        ]);
        $this->fakeBtcPay(['inv_pack' => ['status' => 'Settled']]);

        $this->runWebhook('InvoiceSettled', 'inv_pack', [
            'purpose' => 'company_slot_pack',
            'userId' => (string) $user->id,
        ]);

        $this->assertSame(CompanySlotPurchase::STATUS_PAID, CompanySlotPurchase::first()->status);
    }

    #[Test]
    public function plan_started_before_the_settled_invoice_grants_one_year(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        $this->fakeBtcPay(['inv_1' => ['status' => 'Settled', 'createdTime' => now()->subMinutes(10)->timestamp]]);

        $event = WebhookEvent::create([
            'event_type' => 'PlanStarted',
            'payload' => [
                'storeId' => self::STORE,
                'subscriber' => [
                    'customer' => ['id' => 'btcpay-sub-1', 'identities' => ['Email' => $user->email]],
                    'plan' => ['id' => 'plan_pro_test'],
                    'phase' => 'Normal',
                    'isActive' => true,
                ],
            ],
            'verified' => true,
        ]);
        (new ProcessBtcPayWebhook($event))->handle();

        $this->assertSame(1, Subscription::where('user_id', $user->id)->count(), 'PlanStarted should activate');
        $first = $this->expiresAt($user);

        $this->runWebhook('InvoiceSettled', 'inv_1', $this->subscriptionMetadata($user));

        $this->assertSame($first, $this->expiresAt($user));
    }

    #[Test]
    public function renewal_invoice_extends_an_older_subscription_once(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => SubscriptionPlan::where('code', 'pro')->value('id'),
            'status' => 'active',
            'starts_at' => now()->subYear(),
            'expires_at' => now()->addDays(3),
        ]);
        $subscription->forceFill(['created_at' => now()->subYear()])->save();
        $before = $this->expiresAt($user);

        $this->fakeBtcPay(['inv_renew' => ['status' => 'Settled', 'createdTime' => now()->subMinute()->timestamp]]);

        $this->runWebhook('InvoiceSettled', 'inv_renew', $this->subscriptionMetadata($user));
        $this->runWebhook('InvoiceSettled', 'inv_renew', $this->subscriptionMetadata($user));

        $this->assertSame(
            $subscription->expires_at->copy()->addYear()->timestamp,
            $this->expiresAt($user),
        );
        $this->assertGreaterThan($before, $this->expiresAt($user));
    }

    #[Test]
    public function success_does_not_accept_an_invoice_found_only_by_email(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        app(SubscriptionCheckoutRegistry::class)->bind('checkout_1', $user->id, 'pro');
        $this->fakeBtcPay(
            ['inv_old_by_email' => ['status' => 'Settled', 'createdTime' => now()->subYear()->timestamp]],
            [
                'id' => 'checkout_1',
                'plan' => ['id' => 'plan_pro_test'],
                'subscriber' => ['customer' => ['identities' => ['Email' => $user->email]]],
            ],
        );

        $this->actingAs($user)->getJson('/api/subscriptions/success?checkoutPlanId=checkout_1')
            ->assertOk()
            ->assertJsonPath('activated', false);

        $this->assertSame('free', $user->fresh()->role);
    }

    #[Test]
    public function an_invoice_applied_to_one_user_cannot_be_applied_to_another(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(SubscriptionEntitlementService::class);

        $service->activateSubscriptionForInvoice($owner, 'pro', 'inv_shared');

        $this->expectException(\RuntimeException::class);
        $service->activateSubscriptionForInvoice($other, 'pro', 'inv_shared');
    }
}
