<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentPdfService;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentUsPdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Company}
     */
    private function proUserWithUsCompany(): array
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
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'US Widgets LLC',
            'jurisdiction' => CompanyJurisdiction::Us,
            'default_currency' => 'USD',
            'street' => '100 Main St',
            'city' => 'Los Angeles',
            'state_region' => 'CA',
            'postal_code' => '90001',
            'country' => 'US',
            'vat_rate_default' => 8.25,
            'issuer_name' => 'Jane Issuer',
            'issuer_phone' => '+1 555 0100',
            'issuer_email' => 'billing@example.com',
            'website' => 'https://example.com',
            'app_settings' => [
                'us_sales_tax_provider' => 'manual',
                'embed_isdoc_in_pdf' => true,
            ],
        ]);

        return [$user, $company];
    }

    #[Test]
    public function us_invoice_pdf_view_includes_sales_tax_breakdown(): void
    {
        [, $company] = $this->proUserWithUsCompany();

        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Buyer Inc',
            'street' => '200 Oak Ave',
            'city' => 'Los Angeles',
            'state_region' => 'CA',
            'postal_code' => '90002',
            'country' => 'US',
        ]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'US-1001',
            'subtotal' => 200,
            'tax_total' => 16.5,
            'total' => 216.5,
            'currency' => 'USD',
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Consulting',
            'quantity' => 2,
            'unit' => 'hr',
            'unit_price' => 100,
            'tax_rate' => 8.25,
            'line_total' => 216.5,
        ]);

        $doc = $doc->fresh(['company', 'contact', 'lines']);
        $canonical = app(CanonicalInvoiceBuilder::class)->fromDocument($doc);

        $html = View::make('pdf.business-invoice-us', [
            'document' => $doc,
            'company' => $company,
            'contact' => $contact,
            'lines' => $doc->lines,
            'taxBreakdown' => $canonical->taxBreakdown,
            'showSalesTaxColumn' => true,
            'showVatColumn' => false,
            'showVatBreakdown' => false,
            'reverseChargeNote' => null,
            'bankQr' => null,
            'btcPayQr' => null,
            'btcPayUrl' => null,
            'logoDataUri' => null,
            'signatureStampDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Subtotal', $html);
        $this->assertStringContainsString('Sales tax 8.25%', $html);
        $this->assertStringContainsString('Tax %', $html);
        $this->assertStringContainsString('216.50', $html);
        $this->assertStringContainsString('Buyer Inc', $html);
        $this->assertStringContainsString('Jane Issuer', $html);
        $this->assertStringContainsString('SATFLUX.io', $html);
        $this->assertStringContainsString('billing@example.com', $html);
    }

    #[Test]
    public function us_pdf_binary_download_succeeds_and_skips_isdoc_embed(): void
    {
        [$user, $company] = $this->proUserWithUsCompany();

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'US-1002',
            'subtotal' => 50,
            'tax_total' => 4.13,
            'total' => 54.13,
            'currency' => 'USD',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Item',
            'quantity' => 1,
            'unit_price' => 50,
            'tax_rate' => 8.25,
            'line_total' => 54.13,
        ]);

        $binary = app(BusinessDocumentPdfService::class)->renderBinary($doc->fresh(['company', 'contact', 'lines']));

        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertStringNotContainsString('invoice.isdoc', $binary);

        $this->actingAs($user)
            ->get("/invoicing/companies/{$company->id}/documents/{$doc->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function us_pdf_uses_us_template_not_eu_isdoc_badge(): void
    {
        [, $company] = $this->proUserWithUsCompany();

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'US-1003',
            'total' => 10,
            'currency' => 'USD',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Line',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $html = View::make('pdf.business-invoice-us', [
            'document' => $doc->fresh(['lines']),
            'company' => $company,
            'contact' => null,
            'lines' => $doc->lines,
            'taxBreakdown' => [],
            'showSalesTaxColumn' => false,
            'showVatColumn' => false,
            'showVatBreakdown' => false,
            'reverseChargeNote' => null,
            'bankQr' => null,
            'btcPayQr' => null,
            'btcPayUrl' => null,
            'logoDataUri' => null,
            'signatureStampDataUri' => null,
        ])->render();

        $this->assertStringNotContainsString('ISDOC', $html);
        $this->assertStringContainsString('Invoice US-1003', $html);
    }
}
