<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pro_user_can_create_and_issue_invoice(): void
    {
        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['btcpay_api_key' => 'test-key']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'iban' => 'SK3112000000198747547509',
            'default_currency' => 'EUR',
            'vat_payer' => false,
        ]);

        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client Ltd',
            'email' => 'client@example.com',
        ]);

        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-1',
        ]);

        $create = $this->actingAs($user)->postJson("/api/invoicing/companies/{$company->id}/documents", [
            'type' => 'invoice',
            'company_contact_id' => $contact->id,
            'store_id' => $store->id,
            'issue_date' => now()->toDateString(),
            'payment_btc_enabled' => true,
            'payment_bank_enabled' => true,
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 'btcpay-inv-issue',
                'checkoutLink' => 'https://btcpay.example/i/issue-checkout',
            ], 200),
        ]);

        $create->assertCreated();
        $docId = $create->json('data.id');

        $issue = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/{$docId}/issue"
        );

        $issue->assertOk();
        $issue->assertJsonPath('data.status', BusinessDocumentStatus::Issued->value);
        $issue->assertJsonPath('data.number', fn ($n) => ! empty($n));
        $issue->assertJsonPath('data.btcpay_invoice_id', 'btcpay-inv-issue');
        $issue->assertJsonPath('data.btcpay_checkout_link', 'https://btcpay.example/i/issue-checkout');
        $issue->assertJsonPath('data.payment_token', fn ($t) => is_string($t) && strlen($t) === 64);
    }

    #[Test]
    public function negative_price_invoice_can_be_saved_and_issued_with_the_reduced_balance(): void
    {
        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['btcpay_api_key' => 'test-key']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'iban' => 'SK3112000000198747547509',
            'default_currency' => 'EUR',
            'vat_payer' => true,
        ]);

        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client Ltd',
            'email' => 'client@example.com',
        ]);

        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-1',
        ]);

        $create = $this->actingAs($user)->postJson("/api/invoicing/companies/{$company->id}/documents", [
            'type' => 'invoice',
            'company_contact_id' => $contact->id,
            'store_id' => $store->id,
            'issue_date' => now()->toDateString(),
            'payment_btc_enabled' => true,
            'payment_bank_enabled' => true,
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 23],
                ['name' => 'Service deduction', 'quantity' => 1, 'unit_price' => -20, 'tax_rate' => 23],
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 'btcpay-inv-issue',
                'checkoutLink' => 'https://btcpay.example/i/issue-checkout',
            ], 200),
        ]);

        $create->assertCreated();
        $docId = $create->json('data.id');

        $issue = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/{$docId}/issue"
        );

        $issue->assertOk();
        $issue->assertJsonPath('data.subtotal', '80.00');
        $issue->assertJsonPath('data.tax_total', '18.40');
        $issue->assertJsonPath('data.total', '98.40');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === '98.40');

        $this->patchJson("/api/invoicing/companies/{$company->id}/documents/{$docId}", [
            'type' => 'invoice',
            'lines' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_price' => 20],
                ['name' => 'Deduction', 'quantity' => 1, 'unit_price' => -21],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->assertSame('98.40', BusinessDocument::findOrFail($docId)->total);
        $this->assertDatabaseHas('business_document_lines', ['business_document_id' => $docId, 'unit_price' => '-20.0000']);
        $this->postJson("/api/invoicing/companies/{$company->id}/documents", [
            'type' => 'proforma',
            'lines' => [['name' => 'Unsupported deduction', 'quantity' => 1, 'unit_price' => -20]],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.unit_price');

        foreach (['ubl', 'isdoc'] as $format) {
            $this->getJson("/api/invoicing/companies/{$company->id}/documents/{$docId}/{$format}")
                ->assertUnprocessable()->assertJsonValidationErrors('lines');
        }
        $pdf = $this->get("/api/invoicing/companies/{$company->id}/documents/{$docId}/pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringNotContainsString('invoice.isdoc', $pdf->getContent());
        $this->postJson("/api/invoicing/companies/{$company->id}/documents", [
            'type' => 'invoice',
            'lines' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_price' => 100],
                ['name' => 'Deduction', 'quantity' => 2, 'unit_price' => -20.0049],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines.1.unit_price');

        foreach ([0, -1] as $quantity) {
            $this->postJson("/api/invoicing/companies/{$company->id}/documents", [
                'type' => 'invoice',
                'lines' => [['name' => 'Invalid deduction', 'quantity' => $quantity, 'unit_price' => -20]],
            ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');
        }
        $invalidTax = $this->postJson("/api/invoicing/companies/{$company->id}/documents", [
            'type' => 'invoice',
            'lines' => [
                ['name' => 'Untaxed work', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
                ['name' => 'Unmatched taxable deduction', 'quantity' => 1, 'unit_price' => -20, 'tax_rate' => 23],
            ],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/invoicing/companies/{$company->id}/documents/{$invalidTax}/issue")
            ->assertUnprocessable()->assertJsonValidationErrors('lines');

        $zero = $this->postJson("/api/invoicing/companies/{$company->id}/documents", [
            'type' => 'invoice',
            'lines' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_price' => 20],
                ['name' => 'Deduction', 'quantity' => 1, 'unit_price' => -20],
            ],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/invoicing/companies/{$company->id}/documents/{$zero}/issue")
            ->assertUnprocessable()->assertJsonValidationErrors('lines');

        $issue->assertJsonPath('data.status', BusinessDocumentStatus::Issued->value);
        $issue->assertJsonPath('data.number', fn ($n) => ! empty($n));
        $issue->assertJsonPath('data.btcpay_invoice_id', 'btcpay-inv-issue');
        $issue->assertJsonPath('data.btcpay_checkout_link', 'https://btcpay.example/i/issue-checkout');
        $issue->assertJsonPath('data.payment_token', fn ($t) => is_string($t) && strlen($t) === 64);
    }

    #[Test]
    public function pro_user_can_update_issued_invoice_and_refresh_btcpay_on_total_change(): void
    {
        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['btcpay_api_key' => 'test-key']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-1',
        ]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260099',
            'total' => 100,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'payment_bank_enabled' => true,
            'btcpay_invoice_id' => 'old-btcpay',
            'btcpay_checkout_link' => 'https://btcpay.example/i/old',
            'issue_date' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 'btcpay-inv-refreshed',
                'checkoutLink' => 'https://btcpay.example/i/refreshed',
            ], 200),
        ]);

        $this->actingAs($user)->patchJson(
            "/api/invoicing/companies/{$company->id}/documents/{$doc->id}",
            [
                'type' => 'invoice',
                'store_id' => $store->id,
                'payment_btc_enabled' => true,
                'payment_bank_enabled' => true,
                'lines' => [
                    ['name' => 'Service', 'quantity' => 1, 'unit_price' => 150],
                ],
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonPath('data.btcpay_invoice_id', 'btcpay-inv-refreshed')
            ->assertJsonPath('data.btcpay_checkout_link', 'https://btcpay.example/i/refreshed');
    }
}
