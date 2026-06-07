<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\CompanyPdfFilenameBuilder;
use App\Services\Invoicing\PayBySquareGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

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
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);

        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->company = Company::create([
            'user_id' => $this->proUser->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'iban' => 'SK3112000000198747547509',
            'vat_payer' => false,
        ]);
    }

    #[Test]
    public function company_show_includes_resolved_app_settings_defaults(): void
    {
        $response = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}");

        $response->assertOk();
        $response->assertJsonPath('data.app_settings.default_constant_symbol', '0308');
        $response->assertJsonPath('data.app_settings.show_pay_by_square', true);
        $response->assertJsonPath('data.app_settings.default_invoice_payment_terms_days', 14);
    }

    #[Test]
    public function pro_user_can_update_app_settings(): void
    {
        $response = $this->actingAs($this->proUser)
            ->patchJson("/api/invoicing/companies/{$this->company->id}/app-settings", [
                'default_constant_symbol' => '0558',
                'show_pay_by_square' => false,
                'default_invoice_payment_terms_days' => 21,
                'pdf_filename_pattern' => '#TYPE#_#NUMBER#',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.app_settings.default_constant_symbol', '0558');
        $response->assertJsonPath('data.app_settings.show_pay_by_square', false);

        $this->company->refresh();
        $this->assertSame('0558', $this->company->app_settings['default_constant_symbol']);
    }

    #[Test]
    public function pay_by_square_respects_company_setting(): void
    {
        $this->company->update([
            'app_settings' => ['show_pay_by_square' => false],
        ]);

        $document = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'payment_bank_enabled' => true,
            'lines' => [],
        ]);
        $document->setRelation('company', $this->company->fresh());

        $generator = app(PayBySquareGenerator::class);
        $this->assertFalse($generator->canGenerate($this->company->fresh(), $document));
    }

    #[Test]
    public function pdf_filename_builder_replaces_tokens(): void
    {
        $this->company->update([
            'trade_name' => 'Acme',
            'app_settings' => ['pdf_filename_pattern' => '#TYPE#_#COMPANY#_#NUMBER#'],
        ]);

        $document = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 120.5,
            'currency' => 'EUR',
            'issue_date' => now()->parse('2026-06-01'),
            'lines' => [],
        ]);

        $filename = app(CompanyPdfFilenameBuilder::class)->build($document->fresh(['company']));

        $this->assertStringContainsString('fa_Acme_20260001', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }
}
