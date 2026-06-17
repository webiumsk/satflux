<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyContactIndexTest extends TestCase
{
    use RefreshDatabase;

    private function proUserWithCompany(): array
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
            'vat_payer' => false,
        ]);

        return [$user, $company];
    }

    #[Test]
    public function contact_index_returns_stats_and_letter_meta(): void
    {
        [$user, $company] = $this->proUserWithCompany();

        $alpha = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Alpha Client',
        ]);
        CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Beta Client',
        ]);

        BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $alpha->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'total' => 150,
            'currency' => 'EUR',
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDay(),
            'lines' => [],
        ]);

        $response = $this->actingAs($user)->getJson("/api/invoicing/companies/{$company->id}/contacts");

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.last_page', 1);
        $response->assertJsonFragment(['name' => 'Alpha Client']);
        $alphaRow = collect($response->json('data'))->firstWhere('id', $alpha->id);
        $this->assertSame(150.0, (float) $alphaRow['stats']['invoiced_total']);
        $this->assertSame(1, $alphaRow['stats']['invoiced_count']);
        $this->assertSame(150.0, (float) $alphaRow['stats']['overdue_total']);
        $this->assertContains('A', $response->json('meta.letters'));
        $this->assertContains('B', $response->json('meta.letters'));
    }

    #[Test]
    public function contact_index_filters_by_search_and_letter(): void
    {
        [$user, $company] = $this->proUserWithCompany();

        CompanyContact::create(['company_id' => $company->id, 'name' => 'Zebra Corp']);
        CompanyContact::create(['company_id' => $company->id, 'name' => 'Acme Ltd', 'registration_number' => '12345']);

        $bySearch = $this->actingAs($user)->getJson("/api/invoicing/companies/{$company->id}/contacts?q=12345");
        $bySearch->assertOk();
        $this->assertCount(1, $bySearch->json('data'));
        $this->assertSame('Acme Ltd', $bySearch->json('data.0.name'));

        $byLetter = $this->actingAs($user)->getJson("/api/invoicing/companies/{$company->id}/contacts?letter=Z");
        $byLetter->assertOk();
        $this->assertCount(1, $byLetter->json('data'));
        $this->assertSame('Zebra Corp', $byLetter->json('data.0.name'));
    }

    #[Test]
    public function contact_store_defaults_payment_terms_when_omitted(): void
    {
        [$user, $company] = $this->proUserWithCompany();

        $response = $this->actingAs($user)->postJson("/api/invoicing/companies/{$company->id}/contacts", [
            'name' => 'Registry Client s.r.o.',
            'registration_number' => '31329217',
            'default_payment_terms_days' => null,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.default_payment_terms_days', 14);
        $this->assertDatabaseHas('company_contacts', [
            'company_id' => $company->id,
            'name' => 'Registry Client s.r.o.',
            'default_payment_terms_days' => 14,
        ]);
    }
}
