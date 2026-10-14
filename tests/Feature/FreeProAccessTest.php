<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\IntegrationDocumentInboxStatus;
use App\Http\Middleware\EnsureApiKeyLimit;
use App\Http\Middleware\EnsureStoreLimit;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanySlotPurchase;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Models\StoreApiKey;
use App\Models\StoreIntegration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\EffectiveAccessPolicy;
use App\Services\Integrations\WooCommerceDocumentService;
use App\Services\SubscriptionEntitlementService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FreeProAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.free_pro_enabled' => true, 'invoicing.beta_pro_max_companies' => null]);
        $this->seed(SubscriptionPlanSeeder::class);
        Http::fake();
    }

    public function test_promotion_grants_pro_without_mutating_billing_or_identity(): void
    {
        $user = User::factory()->create(['role' => 'free', 'is_guest' => false]);
        $before = $user->fresh()->getAttributes();
        $policy = app(EffectiveAccessPolicy::class);
        $access = $policy->snapshot($user);

        $this->assertSame('pro', $access['plan_code']);
        $this->assertSame('promotion', $access['source']);
        $this->assertTrue($access['promotion']['active']);
        $this->assertNull($access['promotion']['ends_at']);
        $this->assertTrue($policy->allows($user, 'business_invoicing'));
        $this->assertTrue($policy->allows($user, 'xlsx_exports'));
        $this->assertFalse($policy->allows($user, 'per_store_user_management'));
        $this->assertFalse($policy->allows($user, 'expense_isdoc_extract_unlimited'));
        $this->assertFalse($user->hasUnlimitedAccess());
        $this->assertFalse($user->hasActiveProEntitlement());
        $this->assertSame('free', $user->currentSubscriptionPlan()->code);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('subscriptions', 0);
        Http::assertNothingSent();
    }

    public static function ineligibleAccounts(): iterable
    {
        yield 'verified guest' => [true, true];
        yield 'unverified registered' => [false, false];
        yield 'unverified guest' => [true, false];
    }

    #[DataProvider('ineligibleAccounts')]
    public function test_verification_does_not_override_guest_restrictions(bool $guest, bool $verified): void
    {
        $user = User::factory()->create([
            'role' => 'free', 'is_guest' => $guest,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $access = app(EffectiveAccessPolicy::class)->snapshot($user);
        $this->assertSame('free', $access['plan_code']);
        $this->assertFalse($access['promotion']['active']);
        $this->assertFalse($user->planFeature('business_invoicing'));
        $this->assertSame($guest ? 0 : 1, $user->getMaxRafflesPerStore());
    }

    public function test_disabling_promotion_preserves_manual_pro_and_privileged_roles(): void
    {
        config(['access.free_pro_enabled' => false]);
        $policy = app(EffectiveAccessPolicy::class);
        $free = User::factory()->create(['role' => 'free']);
        $manual = User::factory()->create(['role' => 'pro', 'email_verified_at' => null]);
        $this->assertSame('free', $policy->snapshot($free)['plan_code']);
        $this->assertSame('legacy', $policy->snapshot($manual)['source']);
        $this->assertTrue($policy->allows($manual, 'business_invoicing'));
        foreach (['admin', 'support', 'enterprise'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertSame('enterprise', $policy->snapshot($user)['plan_code']);
            $this->assertTrue($policy->allows($user, 'per_store_user_management'));
            $this->assertNull($policy->limit($user, 'max_stores'));
        }
    }

    public function test_pro_plan_cannot_promote_enterprise_features_even_if_misconfigured(): void
    {
        $pro = SubscriptionPlan::where('code', 'pro')->first();
        $pro->update(['features' => array_merge($pro->features, [
            'per_store_user_management', 'expense_isdoc_extract_unlimited',
        ])]);
        $user = User::factory()->create(['role' => 'free']);
        $this->assertFalse($user->planFeature('per_store_user_management'));
        $this->assertFalse($user->planFeature('expense_isdoc_extract_unlimited'));
    }

    public function test_active_paid_pro_remains_factual_and_is_not_relabelled_promotion(): void
    {
        $user = User::factory()->create(['role' => 'pro']);
        $subscription = $this->subscription($user, now()->addYear());
        $before = $subscription->fresh()->getAttributes();
        $access = app(EffectiveAccessPolicy::class)->snapshot($user);
        $this->assertSame('subscription', $access['source']);
        $this->assertFalse($access['promotion']['active']);
        $this->assertTrue($user->hasActiveProEntitlement());
        $this->assertSame($before, $subscription->fresh()->getAttributes());
    }

    public function test_expired_trial_and_stale_grace_do_not_keep_paid_features_after_promotion_ends(): void
    {
        config(['access.free_pro_enabled' => false]);
        foreach (['trial', 'paid'] as $phase) {
            $user = User::factory()->create(['role' => 'pro']);
            $this->subscription($user, now()->subDays(50), $phase, 'grace');
            $this->assertSame('free', app(EffectiveAccessPolicy::class)->snapshot($user)['plan_code']);
            $this->assertFalse(app(SubscriptionEntitlementService::class)->canUseBusinessInvoicing($user));
        }
    }

    public function test_grace_access_ends_at_exact_boundary_without_billing_mutation(): void
    {
        config(['access.free_pro_enabled' => false]);
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create(['role' => 'pro']);
        $subscription = $this->subscription($user, now()->subDays(30), 'paid', 'grace');
        $this->assertSame('free', app(EffectiveAccessPolicy::class)->snapshot($user)['plan_code']);
        $this->assertSame('grace', $subscription->fresh()->status);
        $this->travelBack();
    }

    public function test_effective_limits_use_current_pro_and_preserve_paid_company_slots(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        CompanySlotPurchase::create([
            'user_id' => $user->id, 'slots' => 2, 'price_sats' => 100,
            'status' => CompanySlotPurchase::STATUS_PAID, 'paid_at' => now(),
        ]);
        $policy = app(EffectiveAccessPolicy::class);
        $limits = $policy->snapshot($user)['limits'];
        $this->assertSame(3, $limits['max_stores']);
        $this->assertSame(3, $limits['max_api_keys']);
        $this->assertSame(3, $limits['max_user_api_keys']);
        $this->assertSame(3, $limits['max_events']);
        $this->assertNull($limits['max_ln_addresses']);
        $this->assertNull($limits['max_raffles']);
        $this->assertSame(2, $limits['included_companies']);
        $this->assertSame(4, $limits['max_companies']);
        config(['invoicing.beta_pro_max_companies' => 7]);
        $this->assertSame(9, $policy->limit($user, 'max_companies'));
        $this->assertDatabaseCount('company_slot_purchases', 1);
    }

    public function test_missing_plans_fail_conservatively_without_unlimited_access(): void
    {
        SubscriptionPlan::query()->delete();
        $user = User::factory()->create(['role' => 'free']);
        $policy = app(EffectiveAccessPolicy::class);
        $this->assertSame('free', $policy->snapshot($user)['plan_code']);
        $this->assertSame(1, $policy->limit($user, 'max_stores'));
        $this->assertSame(1, $policy->limit($user, 'max_api_keys'));
        $this->assertSame(0, $policy->limit($user, 'max_companies'));
        $this->assertFalse($policy->allows($user, 'business_invoicing'));
        Store::factory()->create(['user_id' => $user->id]);
        $response = app(EnsureStoreLimit::class)->handle($this->requestFor($user), fn () => response('allowed'));
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_overlimit_existing_stores_remain_and_new_creation_is_blocked(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        Store::factory()->count(4)->create(['user_id' => $user->id]);
        $response = app(EnsureStoreLimit::class)->handle($this->requestFor($user), fn () => response('allowed'));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(4, $user->stores()->count());
    }

    public function test_store_key_limit_uses_owner_and_only_counts_active_keys(): void
    {
        $owner = User::factory()->create(['role' => 'free']);
        $viewer = User::factory()->create(['role' => 'free', 'email_verified_at' => null]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        foreach ([true, true, false] as $active) {
            StoreApiKey::create([
                'store_id' => $store->id, 'label' => 'Synthetic key',
                'btcpay_api_key' => 'synthetic-test-key', 'is_active' => $active,
            ]);
        }
        $request = $this->requestFor($viewer);
        $request->setRouteResolver(fn () => new class($store)
        {
            public function __construct(private Store $store) {}

            public function parameter(string $key): Store
            {
                return $this->store;
            }
        });
        $middleware = app(EnsureApiKeyLimit::class);
        $this->assertSame(200, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());
        config(['access.free_pro_enabled' => false]);
        $this->assertSame(403, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());
    }

    public function test_account_snapshot_separates_billing_and_reloads_limits_after_policy_change(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        Sanctum::actingAs($user);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('plan.code', 'free')->assertJsonPath('plan.max_stores', 1)
            ->assertJsonPath('subscription', null)->assertJsonPath('effective_access.plan_code', 'pro')
            ->assertJsonPath('plan_features.business_invoicing', true)
            ->assertJsonPath('plan_features.expense_isdoc_extract_unlimited', false);
        $this->getJson('/api/user/limits')->assertOk()->assertJsonPath('stores.max', 3);
        config(['access.free_pro_enabled' => false]);
        $this->getJson('/api/user/limits')->assertOk()->assertJsonPath('stores.max', 1)
            ->assertJsonPath('companies.max', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_new_woocommerce_issuance_requires_current_access_but_issued_retries_complete(): void
    {
        $user = User::factory()->create(['role' => 'free']);
        $company = Company::create([
            'user_id' => $user->id, 'legal_name' => 'Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
        ]);
        $store = Store::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);
        $integration = StoreIntegration::createForStore($store)['integration'];
        $document = BusinessDocument::create([
            'user_id' => $user->id, 'store_id' => $store->id, 'company_id' => $company->id,
            'type' => BusinessDocumentType::Invoice, 'status' => BusinessDocumentStatus::Draft,
            'currency' => 'EUR', 'total' => 0,
        ]);
        $service = app(WooCommerceDocumentService::class);
        config(['access.free_pro_enabled' => false]);
        try {
            $service->issueDocument($integration, $document);
            $this->fail('New issuance must check current owner access.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('plan', $exception->errors());
        }
        $this->assertNull($document->fresh()->number);
        $document->update(['status' => BusinessDocumentStatus::Issued, 'number' => 'SYNTHETIC-001']);
        $this->assertSame('SYNTHETIC-001', $service->issueDocument($integration, $document)->number);
        $inbox = IntegrationDocumentInbox::create([
            'user_id' => $user->id, 'store_id' => $store->id,
            'store_integration_id' => $integration->id, 'source' => 'woocommerce',
            'evolu_document_id' => (string) Str::uuid(),
            'status' => IntegrationDocumentInboxStatus::Pending,
            'payload_json' => ['type' => 'invoice', 'number' => 'SYNTHETIC-002'],
        ]);
        $this->assertSame('SYNTHETIC-002', $service->issueInboxEntry($integration, $inbox)->payload_json['number']);
    }

    private function requestFor(User $user): Request
    {
        $request = Request::create('/synthetic', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function subscription(User $user, $expiry, string $phase = 'paid', string $status = 'active'): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id, 'plan_id' => SubscriptionPlan::where('code', 'pro')->first()->id,
            'status' => $status, 'billing_phase' => $phase, 'starts_at' => now()->subYear(),
            'expires_at' => $expiry, 'grace_ends_at' => $phase === 'trial' ? null : $expiry->copy()->addDays(30),
        ]);
    }
}
