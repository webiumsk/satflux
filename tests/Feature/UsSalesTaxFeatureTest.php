<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\DocumentTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsSalesTaxFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

    protected Company $usCompany;

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

        $this->usCompany = Company::create([
            'user_id' => $this->proUser->id,
            'legal_name' => 'US Seller LLC',
            'jurisdiction' => CompanyJurisdiction::Us,
            'default_currency' => 'USD',
            'street' => '1 Broadway',
            'city' => 'New York',
            'state_region' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'vat_rate_default' => 8.875,
            'app_settings' => ['us_sales_tax_provider' => 'manual'],
        ]);
    }

    #[Test]
    public function creating_us_invoice_via_api_persists_sales_tax_totals(): void
    {
        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->usCompany->id}/documents", [
                'type' => 'invoice',
                'issue_date' => '2026-06-10',
                'due_date' => '2026-06-24',
                'currency' => 'USD',
                'lines' => [
                    [
                        'name' => 'License',
                        'quantity' => 1,
                        'unit_price' => 200,
                        'tax_rate' => 8.875,
                    ],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', '200.00');
        $response->assertJsonPath('data.tax_total', '17.75');
        $response->assertJsonPath('data.total', '217.75');

        $docId = $response->json('data.id');
        $line = BusinessDocumentLine::where('business_document_id', $docId)->first();
        $this->assertNotNull($line);
        $this->assertSame('217.75', $line->line_total);
    }

    #[Test]
    public function document_totals_calculator_matches_us_manual_rates(): void
    {
        $totals = app(DocumentTotalsCalculator::class)->calculate($this->usCompany, [
            ['quantity' => 2, 'unit_price' => 50, 'tax_rate' => 10],
        ]);

        $this->assertSame('100.00', $totals['subtotal']);
        $this->assertSame('10.00', $totals['tax_total']);
        $this->assertSame('110.00', $totals['total']);
    }

    #[Test]
    public function stripe_tax_preview_requires_state_and_postal_code(): void
    {
        config(['services.stripe.tax_secret_key' => 'sk_test_fake']);

        $this->usCompany->update([
            'app_settings' => array_merge($this->usCompany->app_settings ?? [], [
                'us_sales_tax_provider' => 'stripe_tax',
            ]),
            'state_region' => null,
            'postal_code' => null,
        ]);

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->usCompany->id}/us-sales-tax/preview", [
                'currency' => 'USD',
                'lines' => [
                    ['name' => 'Item', 'quantity' => 1, 'unit_price' => 100],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contact']);
    }

    #[Test]
    public function avalara_provider_is_rejected_until_integrated(): void
    {
        $this->usCompany->update([
            'app_settings' => array_merge($this->usCompany->app_settings ?? [], [
                'us_sales_tax_provider' => 'avalara',
            ]),
        ]);

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->usCompany->id}/us-sales-tax/preview", [
                'currency' => 'USD',
                'lines' => [
                    ['name' => 'Item', 'quantity' => 1, 'unit_price' => 100],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['us_sales_tax']);
    }

    #[Test]
    public function stripe_tax_preview_uses_contact_address(): void
    {
        config(['services.stripe.tax_secret_key' => 'sk_test_fake']);

        Http::fake([
            'api.stripe.com/v1/tax/calculations' => Http::response([
                'tax_breakdown' => [
                    [
                        'taxable_amount' => 10000,
                        'tax_amount' => 887,
                        'tax_rate_details' => [
                            'percentage_decimal' => '8.875',
                            'tax_type' => 'sales_tax',
                            'state' => 'NY',
                        ],
                    ],
                ],
                'line_items' => [
                    'data' => [
                        ['reference' => 'line-0', 'amount' => 10000, 'amount_tax' => 887],
                    ],
                ],
            ]),
        ]);

        $this->usCompany->update([
            'app_settings' => array_merge($this->usCompany->app_settings ?? [], [
                'us_sales_tax_provider' => 'stripe_tax',
            ]),
        ]);

        $contact = CompanyContact::create([
            'company_id' => $this->usCompany->id,
            'name' => 'NY Buyer',
            'street' => '5th Ave',
            'city' => 'New York',
            'state_region' => 'NY',
            'postal_code' => '10002',
            'country' => 'US',
        ]);

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->usCompany->id}/us-sales-tax/preview", [
                'company_contact_id' => $contact->id,
                'currency' => 'USD',
                'lines' => [
                    ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.tax_total', '8.87')
            ->assertJsonPath('data.total', '108.87')
            ->assertJsonPath('data.tax_breakdown.0.label', 'NY sales tax');

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            return str_contains($request->url(), 'api.stripe.com/v1/tax/calculations')
                && str_contains($body, 'customer_details')
                && str_contains($body, '10002')
                && str_contains($body, 'NY');
        });
    }

    #[Test]
    public function us_sales_tax_preview_is_forbidden_for_eu_company(): void
    {
        $euCompany = Company::create([
            'user_id' => $this->proUser->id,
            'legal_name' => 'SK s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$euCompany->id}/us-sales-tax/preview", [
                'lines' => [
                    ['name' => 'X', 'quantity' => 1, 'unit_price' => 10],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company']);
    }

    #[Test]
    public function pro_user_can_save_us_sales_tax_app_settings(): void
    {
        $this->actingAs($this->proUser)
            ->patchJson("/api/invoicing/companies/{$this->usCompany->id}/app-settings", [
                'us_sales_tax_provider' => 'stripe_tax',
                'stripe_tax_secret_key' => 'sk_test_company_key',
            ])
            ->assertOk()
            ->assertJsonPath('data.app_settings.us_sales_tax_provider', 'stripe_tax')
            ->assertJsonPath('data.app_settings.stripe_tax_secret_key_set', true)
            ->assertJsonMissingPath('data.app_settings.stripe_tax_secret_key');

        $this->usCompany->refresh();
        $this->assertSame('stripe_tax', $this->usCompany->app_settings['us_sales_tax_provider']);
        $this->assertSame('sk_test_company_key', $this->usCompany->app_settings['stripe_tax_secret_key']);
    }
}
