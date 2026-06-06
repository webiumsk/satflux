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
    public function cannot_delete_older_issued_invoice(): void
    {
        $older = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260003',
            'total' => 10,
            'currency' => 'EUR',
            'created_at' => now()->subDay(),
        ]);

        BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260004',
            'total' => 20,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$older->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function can_delete_latest_paid_invoice(): void
    {
        $paid = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Paid,
            'number' => '20260005',
            'total' => 99,
            'currency' => 'EUR',
            'paid_at' => now(),
            'amount_paid' => 99,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$paid->id}")
            ->assertOk();

        $this->assertDatabaseMissing('business_documents', ['id' => $paid->id]);
    }

    #[Test]
    public function can_unmark_paid_invoice_and_then_update(): void
    {
        $paid = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Paid,
            'number' => '20260006',
            'total' => 50,
            'currency' => 'EUR',
            'paid_at' => now(),
            'amount_paid' => 50,
            'issue_date' => now(),
        ]);
        $paid->lines()->create([
            'sort_order' => 0,
            'name' => 'Line',
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$paid->id}/unmark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'issued');

        $this->actingAs($this->user)
            ->patchJson("/api/invoicing/companies/{$this->company->id}/documents/{$paid->id}", [
                'title' => 'Updated title',
                'lines' => [
                    ['name' => 'Line', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    #[Test]
    public function can_cancel_paid_invoice(): void
    {
        $paid = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Paid,
            'number' => '20260007',
            'total' => 30,
            'currency' => 'EUR',
            'paid_at' => now(),
            'amount_paid' => 30,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$paid->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.amount_paid', null);
    }

    #[Test]
    public function can_delete_cancelled_invoice(): void
    {
        $cancelled = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Cancelled,
            'number' => '20260008',
            'total' => 15,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$cancelled->id}")
            ->assertOk();
    }
}
