<?php

namespace Tests\Feature;

use App\Mail\BusinessDocumentEmail;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EphemeralBusinessDocumentCompanyLessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_without_server_company_can_render_ephemeral_pdf(): void
    {
        $user = $this->createProUser();

        $response = $this->actingAs($user)->postJson(
            '/api/invoicing/ephemeral/pdf',
            $this->ephemeralPayload()
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    #[Test]
    public function authenticated_user_without_server_company_can_preview_ephemeral_email(): void
    {
        $user = $this->createProUser();

        $response = $this->actingAs($user)->postJson(
            '/api/invoicing/ephemeral/email-preview',
            $this->ephemeralPayload()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['subject', 'body', 'to', 'attachment_filename'],
            ]);
    }

    #[Test]
    public function authenticated_user_without_server_company_can_send_ephemeral_email(): void
    {
        Mail::fake();
        $user = $this->createProUser();
        $payload = $this->ephemeralPayload();
        $payload['to'] = ['billing@client.test'];
        $payload['subject'] = 'Local-first invoice';
        $payload['body'] = 'Please find the invoice attached.';

        $response = $this->actingAs($user)->postJson(
            '/api/invoicing/ephemeral/send-email',
            $payload
        );

        $response->assertOk()->assertJsonPath('data.sent_to.0', 'billing@client.test');
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('companies', 0);

        Mail::assertSent(BusinessDocumentEmail::class, function (BusinessDocumentEmail $mail) {
            return $mail->subjectLine === 'Local-first invoice'
                && in_array('billing@client.test', $mail->toAddresses, true)
                && str_starts_with($mail->pdfBinary, '%PDF');
        });
    }

    #[Test]
    public function authenticated_user_without_server_company_can_render_ephemeral_isdoc(): void
    {
        $user = $this->createProUser();

        $response = $this->actingAs($user)->postJson(
            '/api/invoicing/ephemeral/isdoc',
            $this->ephemeralPayload()
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('Invoice', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    #[Test]
    public function authenticated_user_without_server_company_can_render_ephemeral_ubl(): void
    {
        $user = $this->createProUser();

        $response = $this->actingAs($user)->postJson(
            '/api/invoicing/ephemeral/ubl',
            $this->ephemeralPayload()
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('Invoice', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    protected function createProUser(): User
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

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ephemeralPayload(): array
    {
        return [
            'company' => [
                'legal_name' => 'Local Studio s.r.o.',
                'street' => 'Main 1',
                'city' => 'Bratislava',
                'postal_code' => '81101',
                'country' => 'SK',
                'default_currency' => 'EUR',
                'jurisdiction' => 'eu_sk',
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
                'number' => 'LOCAL-2026-001',
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
