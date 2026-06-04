<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\ExpenseIsdocPackPurchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessExpenseIsdocPackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseIsdocPackTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.btcpay.subscription_store_id' => 'sub-store-1',
            'services.btcpay.base_url' => 'https://btcpay.test',
            'services.btcpay.api_key' => 'test-key',
        ]);

        $proPlan = SubscriptionPlan::create([
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
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function quota_includes_purchased_credits(): void
    {
        ExpenseIsdocCreditBalance::create([
            'user_id' => $this->user->id,
            'balance' => 10,
        ]);

        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/isdoc-extract-quota",
        );

        $response->assertOk();
        $response->assertJsonPath('data.purchased_credits', 10);
        $response->assertJsonPath('data.can_extract', true);
    }

    #[Test]
    public function pack_purchase_creates_btcpay_invoice(): void
    {
        Http::fake([
            'https://btcpay.test/api/v1/stores/sub-store-1/invoices' => Http::response([
                'id' => 'inv-pack-1',
                'checkoutLink' => 'https://btcpay.test/i/pack1',
            ], 201),
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/isdoc-packs/purchase",
            ['credits' => 25],
        );

        $response->assertOk();
        $response->assertJsonPath('data.checkoutLink', 'https://btcpay.test/i/pack1');
        $response->assertJsonPath('data.credits', 25);

        $this->assertDatabaseHas('expense_isdoc_pack_purchases', [
            'user_id' => $this->user->id,
            'credits' => 25,
            'btcpay_invoice_id' => 'inv-pack-1',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function webhook_fulfillment_adds_credits(): void
    {
        ExpenseIsdocPackPurchase::create([
            'user_id' => $this->user->id,
            'credits' => 50,
            'price_eur' => 18.45,
            'btcpay_invoice_id' => 'inv-pack-2',
            'status' => ExpenseIsdocPackPurchase::STATUS_PENDING,
        ]);

        $service = app(BusinessExpenseIsdocPackService::class);
        $this->assertTrue($service->fulfillPaidInvoice('inv-pack-2', (string) $this->user->id));

        $this->assertSame(50, ExpenseIsdocCreditBalance::query()
            ->where('user_id', $this->user->id)
            ->value('balance'));
    }
}
