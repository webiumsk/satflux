<?php

namespace Tests\Feature;

use App\Enums\BankTransactionDirection;
use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\Wise\WiseStatementMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WiseBankSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

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
            'legal_name' => 'Webium LLC',
            'jurisdiction' => CompanyJurisdiction::Us,
            'default_currency' => 'USD',
        ]);
    }

    #[Test]
    public function statement_mapper_extracts_credit_and_reference(): void
    {
        $mapper = new WiseStatementMapper;
        $rows = $mapper->map([
            'transactions' => [
                [
                    'type' => 'CREDIT',
                    'date' => '2026-06-02',
                    'amount' => ['value' => 250.0, 'currency' => 'USD'],
                    'details' => [
                        'paymentReference' => 'Invoice INV-0042',
                        'senderName' => 'Acme Client',
                    ],
                    'referenceNumber' => 'wise-tx-1',
                ],
            ],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(BankTransactionDirection::Credit, $rows[0]->direction);
        $this->assertNull($rows[0]->variableSymbol);
        $this->assertSame('Invoice INV-0042', $rows[0]->reference);
        $this->assertSame(250.0, $rows[0]->amount);
    }

    #[Test]
    public function connect_stores_encrypted_token_and_profile_ids(): void
    {
        Http::fake([
            'api.wise.com/v1/profiles' => Http::response([
                ['id' => 10, 'type' => 'BUSINESS', 'details' => ['name' => 'Webium LLC']],
            ]),
            'api.wise.com/v4/profiles/10/balances*' => Http::response([
                ['id' => 99, 'currency' => 'USD', 'amount' => ['currency' => 'USD']],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/wise/connect",
            ['wise_api_token' => 'test-token-abcdefghij'],
        );

        $response->assertOk()->assertJsonPath('data.wise_token_set', true);
        $response->assertJsonPath('data.profile_id', 10);
        $response->assertJsonPath('data.balance_id', 99);

        $settings = $this->company->fresh()->app_settings;
        $this->assertArrayHasKey('wise_api_token_encrypted', $settings);
        $this->assertArrayNotHasKey('wise_api_token', $settings);
    }

    #[Test]
    public function sync_local_first_returns_rows_without_persisting(): void
    {
        Http::fake([
            'api.wise.com/v1/profiles' => Http::response([
                ['id' => 10, 'type' => 'BUSINESS'],
            ]),
            'api.wise.com/v4/profiles/10/balances*' => Http::response([
                ['id' => 99, 'currency' => 'USD'],
            ]),
            'api.wise.com/v1/profiles/10/balance-statements/99/statement.json*' => Http::response([
                'transactions' => [
                    [
                        'type' => 'CREDIT',
                        'date' => '2026-06-02',
                        'amount' => ['value' => 100.0, 'currency' => 'USD'],
                        'details' => ['paymentReference' => 'INV-0007'],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/wise/connect",
            ['wise_api_token' => 'test-token-abcdefghij'],
        )->assertOk();

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/wise/sync",
            ['local_first' => true],
        );

        $response->assertOk();
        $response->assertJsonPath('data.imported', 1);
        $response->assertJsonCount(1, 'data.rows');
        $this->assertDatabaseCount('bank_transactions', 0);
    }
}
