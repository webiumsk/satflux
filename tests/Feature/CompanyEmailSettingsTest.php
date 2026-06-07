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
use App\Services\Invoicing\CompanyEmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyEmailSettingsTest extends TestCase
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
            'bank_account' => '1234567890',
            'vat_payer' => false,
        ]);
    }

    #[Test]
    public function company_show_includes_email_settings_defaults(): void
    {
        $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}")
            ->assertOk()
            ->assertJsonPath('data.email_settings.delivery_method', 'system')
            ->assertJsonPath('data.email_settings.templates.invoice.subject', '#MY_COMPANY# - Faktúra #NUMBER#');
    }

    #[Test]
    public function pro_user_can_update_email_templates_and_smtp(): void
    {
        $response = $this->actingAs($this->proUser)
            ->patchJson("/api/invoicing/companies/{$this->company->id}/email-settings", [
                'delivery_method' => 'smtp',
                'smtp' => [
                    'username' => 'billing@acme.sk',
                    'password' => 'secret-pass',
                    'host' => 'smtp.acme.sk',
                    'port' => 587,
                    'encryption' => 'tls',
                    'from_name' => 'Acme Billing',
                ],
                'templates' => [
                    'invoice' => [
                        'subject' => 'FA #NUMBER#',
                        'body' => 'Hello #CLIENT_NAME#',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.email_settings.delivery_method', 'smtp');
        $response->assertJsonPath('data.email_settings.smtp.password_set', true);
        $response->assertJsonPath('data.email_settings.templates.invoice.subject', 'FA #NUMBER#');

        $this->company->refresh();
        $this->assertSame('billing@acme.sk', $this->company->email_settings['smtp']['username']);
        $this->assertSame('secret-pass', Crypt::decryptString($this->company->email_settings['smtp']['password_encrypted']));
    }

    #[Test]
    public function gmail_delivery_is_rejected_until_oauth_exists(): void
    {
        $this->actingAs($this->proUser)
            ->patchJson("/api/invoicing/companies/{$this->company->id}/email-settings", [
                'delivery_method' => 'gmail',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function template_renderer_replaces_tokens(): void
    {
        $this->company->update([
            'trade_name' => 'Acme',
            'email_settings' => [
                'templates' => [
                    'invoice' => [
                        'subject' => 'Faktúra #NUMBER# pre #CLIENT_NAME#',
                        'body' => 'Suma #AMOUNT#',
                    ],
                ],
            ],
        ]);

        $document = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260099',
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'lines' => [],
        ]);

        $rendered = app(CompanyEmailTemplateRenderer::class)->render(
            $this->company->fresh(),
            'invoice',
            $document,
            $this->proUser,
        );

        $this->assertStringContainsString('20260099', $rendered['subject']);
        $this->assertStringContainsString('100,00 EUR', $rendered['body']);
    }
}
