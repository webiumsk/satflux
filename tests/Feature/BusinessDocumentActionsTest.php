<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Test Co',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function can_mark_issued_invoice_as_paid(): void
    {
        $doc = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$doc->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    #[Test]
    public function can_duplicate_invoice_as_draft(): void
    {
        $doc = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260002',
            'total' => 50,
            'currency' => 'EUR',
        ]);
        $doc->lines()->create([
            'sort_order' => 0,
            'name' => 'Item',
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$doc->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null);

        $this->assertDatabaseCount('business_documents', 2);
    }

    #[Test]
    public function can_delete_draft_only(): void
    {
        $draft = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Draft,
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$draft->id}")
            ->assertOk();

        $this->assertDatabaseMissing('business_documents', ['id' => $draft->id]);
    }

    #[Test]
    public function cannot_delete_issued_invoice(): void
    {
        $issued = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260003',
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$issued->id}")
            ->assertStatus(422);
    }
}
