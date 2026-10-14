<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanySlotPurchase;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\ExpenseIsdocPackPurchase;
use App\Models\User;
use App\Services\BtcPay\SubscriptionService;
use App\Services\Invoicing\BusinessExpenseIsdocPackService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use App\Services\Invoicing\CompanySlotService;
use App\Services\PurchaseAvailabilityPolicy;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FreeProPurchasePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'access.free_pro_enabled' => true,
            'access.pro_purchases_enabled' => false,
            'access.company_slot_purchases_enabled' => false,
            'access.isdoc_pack_purchases_enabled' => false,
            'services.btcpay.base_url' => 'https://btcpay.test',
            'services.btcpay.api_key' => 'synthetic-test-key',
            'services.btcpay.subscription_store_id' => 'subscription-store',
            'services.btcpay.subscription_offering_id' => 'offering-test',
            'services.btcpay.subscription_plans.pro' => 'actual-pro-plan',
            'services.btcpay.subscription_plans.enterprise' => 'actual-enterprise-plan',
            'services.btcpay.allow_guest_subscriptions' => false,
        ]);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    #[Test]
    public function pro_checkout_is_closed_without_billing_or_account_side_effects(): void
    {
        $user = User::factory()->create(['role' => 'free', 'is_guest' => false]);
        $before = $user->fresh()->getAttributes();

        $this->actingAs($user)->postJson('/api/subscriptions/checkout', ['plan' => 'pro'])
            ->assertForbidden()->assertJsonPath('code', 'pro_purchase_temporarily_disabled');

        Http::assertNothingSent();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    #[Test]
    public function enabling_anonymous_checkout_does_not_reopen_pro_sales(): void
    {
        config(['services.btcpay.allow_guest_subscriptions' => true]);

        $this->postJson('/api/subscriptions/checkout', ['plan' => 'pro', 'customerEmail' => 'synthetic@example.test'])
            ->assertForbidden()->assertJsonPath('code', 'pro_purchase_temporarily_disabled');

        Http::assertNothingSent();
    }

    #[Test]
    public function existing_guest_and_provider_input_restrictions_are_preserved(): void
    {
        $this->actingAs(User::factory()->guest()->create())
            ->postJson('/api/subscriptions/checkout', ['plan' => 'enterprise'])
            ->assertUnprocessable()->assertJsonPath('code', 'guest_subscription_blocked');

        $this->actingAs(User::factory()->create())
            ->postJson('/api/subscriptions/checkout', ['plan' => 'enterprise', 'planId' => 'actual-pro-plan'])
            ->assertUnprocessable()->assertJsonValidationErrors('planId');

        Http::assertNothingSent();
    }

    #[Test]
    public function alternate_subscription_service_callers_cannot_create_pro_or_unknown_trial_checkouts(): void
    {
        $service = app(SubscriptionService::class);
        foreach (['actual-pro-plan', 'renamed-unconfigured-plan'] as $planId) {
            foreach ([true, false] as $trial) {
                $this->assertPurchaseDenied(fn () => $service->createPlanCheckout(
                    'subscription-store', 'offering-test', $planId, ['isTrial' => $trial],
                ), 'pro_purchase_temporarily_disabled');
            }
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function configured_enterprise_checkout_remains_available(): void
    {
        Http::fake([
            '*/offerings/offering-test' => Http::response(['id' => 'offering-test']),
            '*/plans/actual-enterprise-plan' => Http::response(['id' => 'actual-enterprise-plan', 'trialDays' => 0]),
            '*/api/v1/plan-checkout' => Http::response(['id' => 'enterprise-checkout', 'url' => 'https://btcpay.test/checkout/enterprise']),
        ]);

        $this->actingAs(User::factory()->create())->postJson('/api/subscriptions/checkout', ['plan' => 'enterprise'])
            ->assertOk()->assertJsonPath('checkoutId', 'enterprise-checkout');
    }

    public static function deniedCreditPlans(): iterable
    {
        yield 'configured PRO' => ['actual-pro-plan'];
        yield 'unknown plan' => ['unconfigured-pro-plan'];
    }

    #[Test]
    #[DataProvider('deniedCreditPlans')]
    public function credit_purchase_uses_actual_provider_plan_even_when_account_role_is_enterprise(string $planId): void
    {
        Http::fake(['*/subscribers/*' => Http::response(['plan' => ['id' => $planId]])]);
        $this->actingAs(User::factory()->create(['role' => 'enterprise']))
            ->postJson('/api/subscriptions/credits', ['amount' => 100])
            ->assertForbidden()->assertJsonPath('code', 'pro_purchase_temporarily_disabled');

        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    #[Test]
    public function public_policy_does_not_reopen_purchases_when_promotion_ends(): void
    {
        $this->getJson('/api/pricing')->assertOk()
            ->assertJsonPath('access_policy.promotion_active', true)
            ->assertJsonPath('access_policy.purchase_availability.pro_subscriptions', false)
            ->assertJsonPath('access_policy.purchase_availability.company_slots', false)
            ->assertJsonPath('access_policy.purchase_availability.isdoc_packs', false)
            ->assertJsonPath('company_slot_packs', []);
        config(['access.free_pro_enabled' => false]);
        $this->getJson('/api/pricing')->assertOk()
            ->assertJsonPath('access_policy.promotion_active', false)
            ->assertJsonPath('access_policy.purchase_availability.pro_credits', false);
        Http::assertNothingSent();
    }

    #[Test]
    public function subscriber_details_use_actual_provider_identity_for_credit_availability(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        Http::fake(fn () => Http::response(['plan' => ['id' => 'actual-enterprise-plan']]));
        $this->actingAs($user)->getJson('/api/subscriptions/details')->assertOk()
            ->assertJsonPath('creditPurchasesAvailable', true);
        $this->assertTrue(app(PurchaseAvailabilityPolicy::class)->creditPurchasesAvailable('actual-enterprise-plan'));
        $this->assertFalse(app(PurchaseAvailabilityPolicy::class)->creditPurchasesAvailable('actual-pro-plan'));
        $this->assertFalse(app(PurchaseAvailabilityPolicy::class)->creditPurchasesAvailable('unconfigured-plan'));
    }

    #[Test]
    public function alternate_credit_service_callers_cannot_create_pro_purchase(): void
    {
        $this->assertPurchaseDenied(fn () => app(SubscriptionService::class)->createCreditPurchaseCheckout(
            'subscription-store', 'offering-test', 'actual-pro-plan', 'synthetic@example.test', 100,
        ), 'pro_purchase_temporarily_disabled');

        Http::assertNothingSent();
    }

    #[Test]
    public function enterprise_credit_purchase_is_allowed_for_a_user_with_a_free_role(): void
    {
        Http::fake([
            '*/subscribers/*' => Http::response(['plan' => ['id' => 'actual-enterprise-plan']]),
            '*/api/v1/plan-checkout' => Http::response([
                'id' => 'enterprise-credit-checkout', 'url' => 'https://btcpay.test/checkout/enterprise', 'invoiceId' => 'enterprise-credit-invoice',
            ]),
            '*/invoices/enterprise-credit-invoice' => Http::response(['checkoutLink' => 'https://btcpay.test/i/enterprise-credit-invoice']),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'free']))
            ->postJson('/api/subscriptions/credits', ['amount' => 100])
            ->assertOk()->assertJsonPath('invoiceId', 'enterprise-credit-invoice');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && ($request->data()['planId'] ?? null) === 'actual-enterprise-plan');
    }

    #[Test]
    public function ambiguous_provider_configuration_fails_closed(): void
    {
        config(['services.btcpay.subscription_plans.enterprise' => 'actual-pro-plan']);
        $this->assertPurchaseDenied(fn () => app(SubscriptionService::class)->createPlanCheckout(
            'subscription-store', 'offering-test', 'actual-pro-plan',
        ), 'pro_purchase_temporarily_disabled');

        Http::assertNothingSent();
    }

    #[Test]
    public function addon_purchase_routes_and_services_are_closed_without_pending_rows(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $company = Company::create([
            'user_id' => $user->id, 'legal_name' => 'Synthetic company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
        ]);

        $this->actingAs($user)->postJson('/api/invoicing/company-slots/purchase', ['slots' => 1])
            ->assertForbidden()->assertJsonPath('code', 'company_slot_purchase_temporarily_disabled');
        $this->postJson("/api/invoicing/companies/{$company->id}/expenses/isdoc-packs/purchase", ['credits' => 25])
            ->assertForbidden()->assertJsonPath('code', 'isdoc_pack_purchase_temporarily_disabled');

        $this->assertPurchaseDenied(fn () => app(CompanySlotService::class)->startPurchase($user, 1), 'company_slot_purchase_temporarily_disabled');
        $this->assertPurchaseDenied(fn () => app(BusinessExpenseIsdocPackService::class)->startPurchase($user, 25), 'isdoc_pack_purchase_temporarily_disabled');
        $this->assertDatabaseCount('company_slot_purchases', 0);
        $this->assertDatabaseCount('expense_isdoc_pack_purchases', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function paused_packs_preserve_purchased_balances_and_legitimate_fulfillment(): void
    {
        $user = User::factory()->create();
        CompanySlotPurchase::create([
            'user_id' => $user->id, 'slots' => 5, 'price_sats' => 120000,
            'btcpay_invoice_id' => 'existing-slot-invoice', 'status' => 'pending',
        ]);
        ExpenseIsdocPackPurchase::create([
            'user_id' => $user->id, 'credits' => 25, 'price_eur' => 10,
            'btcpay_invoice_id' => 'existing-pack-invoice', 'status' => 'pending',
        ]);
        ExpenseIsdocCreditBalance::create(['user_id' => $user->id, 'balance' => 10]);

        $slots = app(CompanySlotService::class);
        $packs = app(BusinessExpenseIsdocPackService::class);
        $this->assertTrue($slots->fulfillPaidInvoice('existing-slot-invoice', (string) $user->id));
        $this->assertFalse($slots->fulfillPaidInvoice('existing-slot-invoice', (string) $user->id));
        $this->assertTrue($packs->fulfillPaidInvoice('existing-pack-invoice', (string) $user->id));
        $this->assertFalse($packs->fulfillPaidInvoice('existing-pack-invoice', (string) $user->id));
        $this->assertSame(5, $slots->paidSlotCount($user));
        $this->assertSame(35, $packs->purchasedBalance($user));
        $this->assertSame([], $slots->availablePacks());
        $snapshot = app(BusinessExpenseIsdocQuotaService::class)->snapshot($user);
        $this->assertSame(35, $snapshot['purchased_credits']);
        $this->assertTrue($snapshot['can_extract']);
        $this->assertFalse($snapshot['purchases_available']);
        $this->assertSame([], $snapshot['packs']);
        Http::assertNothingSent();
    }

    #[Test]
    public function exhausted_quota_does_not_recommend_a_disabled_purchase(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        $user = User::factory()->create(['role' => 'free']);

        try {
            app(BusinessExpenseIsdocQuotaService::class)->assertCanExtract($user);
            $this->fail('Exhausted ISDOC quota must remain enforced.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('messages.isdoc_extract_limit_reached_no_purchase')], $exception->errors()['quota']);
        }
    }

    #[Test]
    public function promotion_rollback_does_not_reopen_purchases(): void
    {
        config(['access.free_pro_enabled' => false]);
        $descriptor = app(PurchaseAvailabilityPolicy::class)->publicDescriptor();
        $this->assertFalse($descriptor['promotion_active']);
        $this->assertNull($descriptor['promotion_ends_at']);
        $this->assertSame([
            'pro_subscriptions' => false, 'pro_credits' => false,
            'company_slots' => false, 'isdoc_packs' => false,
        ], $descriptor['purchase_availability']);
    }

    private function assertPurchaseDenied(callable $action, string $code): void
    {
        try {
            $action();
            $this->fail('Purchase must fail before any provider mutation.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(403, $exception->getResponse()->getStatusCode());
            $this->assertSame($code, json_decode($exception->getResponse()->getContent(), true)['code']);
        }
    }
}
