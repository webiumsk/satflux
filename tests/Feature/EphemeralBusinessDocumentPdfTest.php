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
    public function migrated_evolu_snapshot_shape_renders_ephemeral_pdf(): void
    {
        [$user, $company] = $this->createProUserWithCompany();

        $payload = $this->ephemeralPayload();
        $payload['company']['jurisdiction'] = 'eu_sk';
        $payload['company']['vat_payer'] = true;
        $payload['company']['vat_status'] = 'payer';
        $payload['company']['app_settings'] = [
            'show_pay_by_square' => true,
            'rounding_method' => 'per_line',
        ];
        $payload['document']['status'] = 'issued';
        $payload['document']['number'] = '20260042';
        $payload['lines'] = [
            [
                'name' => 'Service',
                'quantity' => '1',
                'unit' => 'ks',
                'unit_price' => '100.00',
                'tax_rate' => '21',
                'line_discount_percent' => '0',
            ],
        ];

        $response = $this->actingAs($user)->postJson(
            '/api/invoicing/ephemeral/pdf',
            $payload,
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function historical_invoice_with_deduction_renders_without_persisting_or_creating_a_checkout(): void
    {
        [$user, $company] = $this->createProUserWithCompany();

        foreach (['/api/invoicing/ephemeral/pdf', "/api/invoicing/companies/{$company->id}/documents/ephemeral/pdf"] as $url) {
            $response = $this->actingAs($user)->postJson($url, $this->deductionPayload());

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }

        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('business_document_lines', 0);
        $this->assertDatabaseCount('ephemeral_btcpay_checkouts', 0);
    }

    #[Test]
    public function pdf_rejects_unsafe_or_inconsistent_signed_snapshots(): void
    {
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        $url = '/api/invoicing/ephemeral/pdf';

        foreach ([
            ['document' => ['status' => 'draft']],
            ['company' => ['jurisdiction' => 'us', 'app_settings' => ['us_sales_tax_provider' => 'stripe_tax']]],
            ['company' => ['jurisdiction' => 'eu_de']],
            [
                'lines' => [1 => ['unit_price' => -100]],
                'source_totals' => [
                    'subtotal' => '0.00',
                    'tax_total' => '0.00',
                    'total' => '0.00',
                    'line_totals' => ['123.00', '-123.00'],
                ],
                'document' => ['amount_paid' => 0],
            ],
            ['lines' => [1 => ['unit_price' => -1, 'quantity' => '1e20']]],
            [
                'lines' => [1 => ['unit_price' => -20, 'tax_rate' => 10]],
                'source_totals' => ['tax_total' => '21.00', 'total' => '101.00', 'line_totals' => ['123.00', '-22.00']],
            ],
            [
                'lines' => [1 => ['unit_price' => '-20.0049', 'quantity' => 2]],
                'source_totals' => [
                    'subtotal' => '59.99',
                    'tax_total' => '13.80',
                    'total' => '73.79',
                    'line_totals' => ['123.00', '-49.21'],
                ],
                'document' => ['amount_paid' => 0],
            ],
        ] as $override) {
            $response = $this->postJson($url, array_replace_recursive($this->deductionPayload(), $override));
            $this->assertSame(422, $response->status(), json_encode($override));
            $response->assertJsonValidationErrors('lines');
        }

        $this->assertDatabaseCount('ephemeral_btcpay_checkouts', 0);
    }

    #[Test]
    public function signed_pdf_requires_original_totals_and_rejects_a_changed_vat_profile(): void
    {
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        $url = '/api/invoicing/ephemeral/pdf';
        $payload = $this->deductionPayload();
        $payload['document']['status'] = 'issued';
        $payload['document']['amount_paid'] = '0.00';
        $payload['company']['vat_payer'] = false;

        $this->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');

        $payload['company']['vat_payer'] = true;
        unset($payload['source_totals']);
        $this->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');
    }

    #[Test]
    public function signed_pdf_checks_line_totals_even_when_vat_changes_offset_in_the_aggregate(): void
    {
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        $url = '/api/invoicing/ephemeral/pdf';
        $payload = $this->deductionPayload();
        $payload['document']['amount_paid'] = '100.00';
        $payload['lines'][1]['unit_price'] = -100;
        $payload['lines'][] = ['name' => 'Non-taxable item', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0];
        $payload['source_totals'] = [
            'subtotal' => '100.00',
            'tax_total' => '0.00',
            'total' => '100.00',
            'line_totals' => ['123.00', '-123.00', '100.00'],
        ];

        $this->postJson($url, $payload)->assertOk();

        $payload['company']['vat_payer'] = false;
        $this->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');
    }

    #[Test]
    public function signed_unit_prices_are_still_rejected_by_non_pdf_snapshot_routes(): void
    {
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);

        foreach (['isdoc', 'ubl', 'efaktura/send'] as $action) {
            $this->postJson("/api/invoicing/ephemeral/{$action}", $this->deductionPayload())
                ->assertUnprocessable()
                ->assertJsonValidationErrors('lines.1.unit_price');
        }
    }

    #[Test]
    public function ordinary_invoice_with_deductions_keeps_payment_options_and_can_be_emailed(): void
    {
        Mail::fake();
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        $payload = $this->deductionPayload();
        $payload['document'] = array_merge($payload['document'], [
            'status' => 'issued', 'amount_paid' => '0.00',
            'payment_bank_enabled' => true, 'payment_btc_enabled' => true,
        ]);

        $this->postJson('/api/invoicing/ephemeral/pdf', $payload)->assertOk();
        $this->postJson('/api/invoicing/ephemeral/email-preview', $payload)->assertOk();
        $payload['to'] = ['client@example.com'];
        $this->postJson('/api/invoicing/ephemeral/send-email', $payload)->assertOk();
        Mail::assertSent(BusinessDocumentEmail::class);
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('ephemeral_btcpay_checkouts', 0);
    }

    #[Test]
    public function negative_quantity_deductions_render_email_and_export_with_positive_unit_price(): void
    {
        Mail::fake();
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        foreach (['invoice', 'credit_note'] as $type) {
            $payload = $this->deductionPayload();
            $payload['document']['type'] = $type;
            $payload['lines'][1]['quantity'] = -1;
            $payload['lines'][1]['unit_price'] = 20;
            $this->postJson('/api/invoicing/ephemeral/pdf', $payload)->assertOk();
            $this->postJson('/api/invoicing/ephemeral/email-preview', $payload)->assertOk();
            $payload['to'] = ['buyer@example.test'];
            $this->postJson('/api/invoicing/ephemeral/send-email', $payload)->assertOk();
            Mail::assertSent(BusinessDocumentEmail::class);
            $isdoc = $this->postJson('/api/invoicing/ephemeral/isdoc', $payload)->assertOk();
            $this->assertStringContainsString('<UnitPrice>20.00</UnitPrice>', $isdoc->getContent());
            $this->assertStringContainsString('>-1</InvoicedQuantity>', $isdoc->getContent());
        }
        $payload['source_totals']['total'] = '123.00';
        $this->postJson('/api/invoicing/ephemeral/pdf', $payload)->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->assertDatabaseCount('business_documents', 0);
    }

    #[Test]
    public function negative_quantity_request_rejects_tiny_values_and_malformed_company_shapes(): void
    {
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        $base = $this->deductionPayload();
        $base['lines'][1]['quantity'] = -1;
        $base['lines'][1]['unit_price'] = 20;
        foreach ([
            ['lines' => [1 => ['quantity' => -1e-20]]],
            ['lines' => [1 => ['unit_price' => 1e-20]]],
            ['company' => ['app_settings' => 'invalid']],
            ['company' => ['jurisdiction' => ['eu_sk']]],
        ] as $override) {
            $this->postJson('/api/invoicing/ephemeral/pdf', array_replace_recursive($base, $override))->assertUnprocessable();
        }
        $this->assertDatabaseCount('business_documents', 0);
    }

    #[Test]
    public function signed_snapshot_rejects_zero_or_negative_quantity_on_visual_and_email_routes(): void
    {
        [$user] = $this->createProUserWithCompany();
        $this->actingAs($user);
        foreach ([0, -1] as $quantity) {
            foreach (['pdf', 'email-preview', 'send-email'] as $action) {
                $payload = $this->deductionPayload();
                $payload['lines'][1]['quantity'] = $quantity;
                $payload['to'] = ['client@example.com'];
                $this->postJson("/api/invoicing/ephemeral/{$action}", $payload)
                    ->assertUnprocessable()->assertJsonValidationErrors('lines.1.quantity');
            }
        }
    }

    #[Test]
    public function ephemeral_pdf_accepts_contact_country_name_and_normalizes_to_iso_code(): void
    {
        [$user, $company] = $this->createProUserWithCompany();
        $payload = $this->ephemeralPayload();
        $payload['contact']['country'] = 'Slovensko';

        $response = $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/pdf",
            $payload,
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
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

    #[Test]
    public function ephemeral_email_uses_snapshot_email_settings_over_bridge_company(): void
    {
        Mail::fake();
        [$user, $company] = $this->createProUserWithCompany();
        $company->update([
            'email_settings' => [
                'delivery_method' => 'smtp',
                'smtp' => [
                    'username' => 'broken@example.com',
                    'host' => 'invalid.example.com',
                    'port' => 587,
                    'encryption' => 'tls',
                ],
            ],
        ]);

        $payload = $this->ephemeralPayload();
        $payload['company']['email_settings'] = [
            'delivery_method' => 'system',
        ];
        $payload['to'] = ['billing@client.test'];
        $payload['subject'] = 'System delivery';
        $payload['body'] = 'Invoice attached.';

        $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/send-email",
            $payload,
        )->assertOk()->assertJsonPath('data.sent_to.0', 'billing@client.test');

        Mail::assertSent(BusinessDocumentEmail::class, function (BusinessDocumentEmail $mail) {
            return $mail->subjectLine === 'System delivery'
                && in_array('billing@client.test', $mail->toAddresses, true);
        });
    }

    #[Test]
    public function ephemeral_email_send_uses_snapshot_smtp_credentials_over_bridge_company(): void
    {
        Mail::fake();
        [$user, $company] = $this->createProUserWithCompany();
        $company->update([
            'email_settings' => [
                'delivery_method' => 'smtp',
                'smtp' => [
                    'username' => 'broken@example.com',
                    'host' => 'invalid.example.com',
                    'port' => 587,
                    'encryption' => 'tls',
                ],
            ],
        ]);

        $payload = $this->ephemeralPayload();
        $payload['company']['email_settings'] = [
            'delivery_method' => 'smtp',
            'smtp' => [
                'username' => 'billing@acme.sk',
                'password' => 'secret-pass',
                'host' => 'smtp.acme.sk',
                'port' => 587,
                'encryption' => 'tls',
                'from_name' => 'Acme Billing',
            ],
        ];
        $payload['to'] = ['billing@client.test'];
        $payload['subject'] = 'Invoice via SMTP';
        $payload['body'] = 'Please find attached invoice.';

        $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/documents/ephemeral/send-email",
            $payload,
        )->assertOk()->assertJsonPath('data.sent_to.0', 'billing@client.test');

        Mail::assertSent(BusinessDocumentEmail::class, function (BusinessDocumentEmail $mail) {
            return $mail->subjectLine === 'Invoice via SMTP'
                && in_array('billing@client.test', $mail->toAddresses, true);
        });
    }

    #[Test]
    public function ephemeral_smtp_test_uses_snapshot_settings_without_persisting(): void
    {
        Mail::fake();
        [$user, $company] = $this->createProUserWithCompany();

        $this->actingAs($user)->postJson(
            "/api/invoicing/companies/{$company->id}/email-settings/ephemeral/test-smtp",
            [
                'to' => 'smtp-test@example.com',
                'company' => [
                    'legal_name' => $company->legal_name,
                    'email_settings' => [
                        'delivery_method' => 'smtp',
                        'smtp' => [
                            'username' => 'billing@acme.sk',
                            'password' => 'secret-pass',
                            'host' => 'smtp.acme.sk',
                            'port' => 587,
                            'encryption' => 'tls',
                            'from_name' => 'Acme Billing',
                        ],
                    ],
                ],
            ],
        )->assertOk()->assertJsonPath('message', 'Test email sent.');

        $company->refresh();
        $this->assertNull($company->email_settings);
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
            'iban' => 'SK3112000000198742637541',
            'issuer_email' => 'issuer@satflux.test',
            'app_settings' => ['show_pay_by_square' => true],
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
                'iban' => 'SK3112000000198742637541',
                'app_settings' => [
                    'show_pay_by_square' => true,
                ],
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
                'payment_bank_enabled' => true,
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

    /** @return array<string, mixed> */
    private function deductionPayload(): array
    {
        return array_replace_recursive($this->ephemeralPayload(), [
            'company' => ['jurisdiction' => 'eu_sk', 'vat_payer' => true],
            'contact' => ['country' => 'SK'],
            'document' => [
                'status' => 'paid',
                'amount_paid' => '98.40',
                'payment_bank_enabled' => false,
                'payment_btc_enabled' => false,
            ],
            'source_totals' => [
                'subtotal' => '80.00',
                'tax_total' => '18.40',
                'total' => '98.40',
                'line_totals' => ['123.00', '-24.60'],
            ],
            'lines' => [
                ['tax_rate' => 23],
                ['name' => 'Service deduction', 'quantity' => 1, 'unit_price' => -20, 'tax_rate' => 23],
            ],
        ]);
    }
}
