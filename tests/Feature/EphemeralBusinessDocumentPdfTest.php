<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Mail\BusinessDocumentEmail;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EphemeralBusinessDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_render_ephemeral_pdf_without_persisting_document(): void
    {
        [$user, $company] = $this->createProUserWithCompany();

        $payload = $this->ephemeralPayload();

        $response = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/pdf",
            $payload
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('business_document_lines', 0);
    }

    #[Test]
    public function authenticated_user_can_send_ephemeral_email_with_pdf_attachment(): void
    {
        Mail::fake();
        [$user, $company] = $this->createProUserWithCompany();
        $payload = $this->ephemeralPayload();
        $payload['to'] = ['billing@client.test'];
        $payload['subject'] = 'Ephemeral invoice';
        $payload['body'] = 'Please find the invoice attached.';

        $response = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/send-email",
            $payload
        );

        $response->assertOk()->assertJsonPath('data.sent_to.0', 'billing@client.test');
        $this->assertDatabaseCount('business_documents', 0);

        Mail::assertSent(BusinessDocumentEmail::class, function (BusinessDocumentEmail $mail) {
            return $mail->subjectLine === 'Ephemeral invoice'
                && in_array('billing@client.test', $mail->toAddresses, true)
                && str_starts_with($mail->pdfBinary, '%PDF');
        });
    }

    #[Test]
    public function authenticated_user_can_render_ephemeral_isdoc_without_persisting_document(): void
    {
        [$user, $company] = $this->createProUserWithCompany();
        $payload = $this->ephemeralPayload();

        $response = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/isdoc",
            $payload
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('Invoice', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
    }

    #[Test]
    public function authenticated_user_can_render_ephemeral_ubl_without_persisting_document(): void
    {
        [$user, $company] = $this->createProUserWithCompany();
        $payload = $this->ephemeralPayload();

        $response = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/ubl",
            $payload
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('Invoice', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    protected function createProUserWithCompany(): array
    {
        $plan = SubscriptionPlan::create([
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
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
            'issuer_email' => 'issuer@satflux.test',
        ]);

        return [$user, $company];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ephemeralPayload(): array
    {
        return [
            'company' => [
                'legal_name' => 'Acme s.r.o.',
                'street' => 'Main 1',
                'city' => 'Bratislava',
                'postal_code' => '81101',
                'country' => 'SK',
                'default_currency' => 'EUR',
            ],
            'contact' => [
                'name' => 'Client Ltd',
                'email' => 'client@example.com',
                'street' => 'Buyer Street 5',
                'city' => 'Prague',
                'postal_code' => '11000',
                'country' => 'CZ',
            ],
            'document' => [
                'type' => 'invoice',
                'status' => 'issued',
                'number' => 'TEMP-2026-001',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'EUR',
                'variable_symbol' => '20260001',
                'discount_percent' => 0,
                'pdf_locale' => 'sk',
            ],
            'lines' => [
                [
                    'name' => 'Consulting',
                    'quantity' => 1,
                    'unit' => 'h',
                    'unit_price' => 100,
                    'tax_rate' => 0,
                ],
            ],
        ];
    }
}
