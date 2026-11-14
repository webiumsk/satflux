<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NegativeQuantityInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_quantity_invoice_issues_with_matching_vat_and_no_stock_movements(): void
    {
        [$user, $company] = $this->company();
        $base = "/api/invoicing/companies/{$company->id}/documents";
        $this->actingAs($user);
        $id = $this->postJson($base, $this->payload())->assertCreated()->json('data.id');
        $this->postJson("{$base}/{$id}/issue")->assertOk()
            ->assertJsonPath('data.subtotal', '85.00')
            ->assertJsonPath('data.tax_total', '19.55')
            ->assertJsonPath('data.total', '104.55');
        $this->assertDatabaseHas('business_document_lines', ['business_document_id' => $id, 'quantity' => '-1.0000', 'unit_price' => '15.0000']);
        $this->assertDatabaseCount('company_stock_item_movements', 0);
        $isdoc = $this->get("{$base}/{$id}/isdoc")->assertOk();
        $this->assertStringContainsString('<InvoicedQuantity unitCode="ks">-1</InvoicedQuantity>', $isdoc->getContent());
        $this->assertStringContainsString('<UnitPrice>15.00</UnitPrice>', $isdoc->getContent());
        $this->assertStringContainsString('<UnitPriceTaxInclusive>18.45</UnitPriceTaxInclusive>', $isdoc->getContent());
        $this->get("{$base}/{$id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $invalid = $this->payload();
        $invalid['lines'][1]['unit_price'] = 150;
        $this->patchJson("{$base}/{$id}", $invalid)->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->assertSame('104.55', BusinessDocument::findOrFail($id)->total);
        $this->assertDatabaseCount('company_stock_item_movements', 0);
        Http::assertNothingSent();
    }

    public function test_manual_deductions_reject_zero_double_negatives_stock_links_and_unsupported_types(): void
    {
        [$user, $company] = $this->company();
        $this->actingAs($user);
        $base = "/api/invoicing/companies/{$company->id}/documents";
        foreach ([
            ['quantity' => 0], ['quantity' => -1e-20], ['unit_price' => -15], ['unit_price' => 0], ['unit_price' => 1e-20],
            ['company_stock_item_id' => '11111111-1111-4111-8111-111111111111'],
            ['company_warehouse_id' => '22222222-2222-4222-8222-222222222222'],
        ] as $override) {
            $payload = $this->payload();
            $payload['lines'][1] = array_merge($payload['lines'][1], $override);
            $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.1.quantity');
        }
        $payload = $this->payload();
        $payload['type'] = 'proforma';
        $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.1.quantity');
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('company_stock_item_movements', 0);
    }

    public function test_negative_quantity_cannot_create_a_negative_tax_bucket_or_nonpositive_invoice(): void
    {
        [$user, $company] = $this->company();
        $this->actingAs($user);
        $base = "/api/invoicing/companies/{$company->id}/documents";
        foreach ([['positive_price' => 10, 'positive_tax' => 23], ['positive_price' => 100, 'positive_tax' => 0]] as $case) {
            $payload = $this->payload();
            $payload['lines'][0]['unit_price'] = $case['positive_price'];
            $payload['lines'][0]['tax_rate'] = $case['positive_tax'];
            $id = $this->postJson($base, $payload)->assertCreated()->json('data.id');
            $this->postJson("{$base}/{$id}/issue")->assertUnprocessable()->assertJsonValidationErrors('lines');
            $this->assertNull(BusinessDocument::findOrFail($id)->number);
        }
    }

    public function test_additional_percentage_discounts_keep_visual_pdf_but_reject_unrepresented_isdoc(): void
    {
        [$user, $company] = $this->company();
        $this->actingAs($user);
        $base = "/api/invoicing/companies/{$company->id}/documents";
        $payload = $this->payload();
        $payload['discount_percent'] = 10;
        $id = $this->postJson($base, $payload)->assertCreated()->json('data.id');
        $this->postJson("{$base}/{$id}/issue")->assertOk()->assertJsonPath('data.total', '94.10');
        $this->getJson("{$base}/{$id}/isdoc")->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->get("{$base}/{$id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_deduction_invoice_remains_correctable_by_an_issued_credit_note(): void
    {
        [$user, $company] = $this->company();
        $this->actingAs($user);
        $base = "/api/invoicing/companies/{$company->id}/documents";
        $invoiceId = $this->postJson($base, $this->payload())->assertCreated()->json('data.id');
        $this->postJson("{$base}/{$invoiceId}/issue")->assertOk();
        $noteId = $this->postJson("{$base}/credit-note-from-invoice", ['invoice_id' => $invoiceId])
            ->assertCreated()->assertJsonPath('data.source_document_id', $invoiceId)->json('data.id');
        $this->postJson("{$base}/{$noteId}/issue")->assertOk()
            ->assertJsonPath('data.total', '104.55')->assertJsonPath('data.type', 'credit_note');
        $this->get("{$base}/{$noteId}/pdf")->assertOk();
        $this->get("{$base}/{$noteId}/isdoc")->assertOk();
        $xml = $this->get("{$base}/{$noteId}/ubl")->assertOk()->getContent();
        $this->assertStringContainsString('<cbc:CreditedQuantity unitCode="C62">-1</cbc:CreditedQuantity>', $xml);
        $this->assertStringContainsString('<cbc:PriceAmount currencyID="EUR">15.00</cbc:PriceAmount>', $xml);
        $this->assertSame('104.55', BusinessDocument::findOrFail($invoiceId)->total);
        $this->assertSame('issued', BusinessDocument::findOrFail($invoiceId)->getAttribute('status')->value);
        $this->assertDatabaseHas('business_document_lines', ['business_document_id' => $invoiceId, 'quantity' => '-1.0000', 'unit_price' => '15.0000']);
    }

    private function payload(): array
    {
        return ['type' => 'invoice', 'issue_date' => '2026-11-13', 'payment_btc_enabled' => false, 'lines' => [
            ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 23, 'unit' => 'ks'],
            ['name' => 'Manual deduction', 'quantity' => -1, 'unit_price' => 15, 'tax_rate' => 23, 'unit' => 'ks'],
        ]];
    }

    private function company(): array
    {
        $plan = SubscriptionPlan::create(['code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro', 'price_eur' => 99,
            'billing_period' => 'year', 'features' => ['business_invoicing'], 'is_active' => true]);
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear()]);
        $company = Company::create(['user_id' => $user->id, 'legal_name' => 'Synthetic Supplier',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'vat_payer' => true, 'vat_status' => 'payer', 'default_currency' => 'EUR']);

        return [$user, $company];
    }
}
