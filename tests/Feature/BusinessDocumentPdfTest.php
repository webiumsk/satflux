<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\CompanyBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_download_pdf_via_web_route(): void
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
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        $response = $this->actingAs($user)->get(
            "/invoicing/companies/{$company->id}/documents/{$doc->id}/pdf"
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function pdf_download_succeeds_when_company_has_logo(): void
    {
        Storage::fake('local');
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
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        app(CompanyBrandingService::class)->storeLogo(
            $company,
            UploadedFile::fake()->image('logo.png', 80, 40)
        );

        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260003',
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'pdf_show_signature' => true,
        ]);

        $this->actingAs($user)
            ->get("/invoicing/companies/{$company->id}/documents/{$doc->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function guest_cannot_download_pdf_without_session(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260002',
            'total' => 50,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        $this->get("/invoicing/companies/{$company->id}/documents/{$doc->id}/pdf")
            ->assertRedirect('/login');
    }

    #[Test]
    public function eu_pdf_view_uses_slovak_labels_when_pdf_locale_is_sk(): void
    {
        app()->setLocale('sk');

        $this->assertSame('Dátum vystavenia', __('Issue date'));
        $this->assertSame('Faktúra', __('Invoice'));
        $this->assertSame('Variabilný symbol', __('Variable symbol'));
    }
}
