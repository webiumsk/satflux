<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\BusinessRecurringProfile;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessRecurringProfileTest extends TestCase
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
            'vat_payer' => false,
        ]);

        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);
    }

    #[Test]
    public function user_can_create_and_list_recurring_profile(): void
    {
        $contact = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Client s.r.o.',
        ]);

        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/recurring-profiles", [
                'document_type' => 'proforma',
                'company_contact_id' => $contact->id,
                'recurrence_interval' => 'yearly',
                'first_issue_date' => '2026-07-15',
                'next_issue_date' => '2026-07-15',
                'title' => 'Zálohová faktúra #INVOICE_NUMBER#',
                'variable_symbol' => '#INVOICE_NUMBER#',
                'lines' => [
                    ['name' => 'Hosting', 'description' => 'Do #NEXT_YEAR#', 'quantity' => 1, 'unit_price' => 50],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.document_type', 'proforma');
        $create->assertJsonPath('data.title', 'Zálohová faktúra #INVOICE_NUMBER#');

        $list = $this->actingAs($this->proUser)
            ->getJson("/api/invoicing/companies/{$this->company->id}/recurring-profiles");

        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    #[Test]
    public function generate_now_issues_document_with_resolved_placeholders(): void
    {
        $profile = BusinessRecurringProfile::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'is_active' => true,
            'recurrence_interval' => 'yearly',
            'first_issue_date' => now()->toDateString(),
            'next_issue_date' => now()->toDateString(),
            'repeat_indefinitely' => true,
            'title' => 'Faktúra #INVOICE_NUMBER#',
            'variable_symbol' => '#VARIABLE_SYMBOL#',
            'currency' => 'EUR',
            'total' => 100,
            'payment_terms_days' => 14,
        ]);

        $profile->lines()->create([
            'sort_order' => 0,
            'name' => 'Služba #YEAR#',
            'description' => null,
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/recurring-profiles/{$profile->id}/generate");

        $response->assertOk();
        $year = now()->format('Y');
        $response->assertJsonPath('data.document.status', 'issued');
        $this->assertStringStartsWith("INV{$year}", $response->json('data.document.number'));
        $this->assertStringContainsString($year, $response->json('data.document.title'));
        $this->assertStringContainsString('Služba', $response->json('data.document.lines.0.name'));
    }
}
