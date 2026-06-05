<?php

namespace Tests\Feature;

use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseTest extends TestCase
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
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function user_can_create_expense_with_internal_number(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses",
            [
                'title' => 'Office rent',
                'external_number' => 'SF-2026-001',
                'issue_date' => '2026-06-01',
                'total' => 120.50,
                'currency' => 'EUR',
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'recorded');
        $this->assertNotEmpty($response->json('data.internal_number'));
        $this->assertSame('Office rent', $response->json('data.title'));
        $this->assertDatabaseHas('business_expenses', [
            'company_id' => $this->company->id,
            'external_number' => 'SF-2026-001',
        ]);
    }

    #[Test]
    public function duplicate_copies_symbols_and_resets_total(): void
    {
        $source = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses",
            [
                'title' => 'Hosting',
                'variable_symbol' => '12345',
                'issue_date' => '2026-05-01',
                'total' => 99,
            ],
        )->assertCreated()->json('data');

        $dup = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$source['id']}/duplicate",
        );

        $dup->assertCreated();
        $dup->assertJsonPath('data.variable_symbol', '12345');
        $dup->assertJsonPath('data.title', 'Hosting');
        $dup->assertJsonPath('data.total', '0.00');
        $this->assertNotSame($source['internal_number'], $dup->json('data.internal_number'));
    }

    #[Test]
    public function mark_paid_and_cancel(): void
    {
        $expense = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'N20260001',
            'issue_date' => now(),
            'total' => 50,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/mark-paid",
        )->assertOk()->assertJsonPath('data.status', 'paid');

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/unmark-paid",
        )->assertOk()->assertJsonPath('data.status', 'recorded');

        $this->actingAs($this->user)->deleteJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}",
        )->assertOk()->assertJsonPath('data.status', 'cancelled');

        $list = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/expenses",
        );
        $list->assertOk();
        $this->assertEmpty(collect($list->json('data'))->where('id', $expense->id));
    }

    #[Test]
    public function attachment_can_be_uploaded_and_downloaded(): void
    {
        $expense = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'N20260002',
            'issue_date' => now(),
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/attachment",
            ['file' => $file],
        )->assertOk()
            ->assertJsonCount(1, 'data.attachments');

        $expense->refresh();
        $this->assertTrue($expense->hasAttachment());
        $this->assertCount(1, $expense->attachments);

        $this->actingAs($this->user)->get(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/attachment",
        )->assertOk();
    }

    #[Test]
    public function expense_can_have_multiple_attachments(): void
    {
        $expense = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'N20260004',
            'issue_date' => now(),
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $first = UploadedFile::fake()->create('invoice-a.pdf', 100, 'application/pdf');
        $second = UploadedFile::fake()->create('invoice-b.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/attachment",
            ['file' => $first],
        )->assertOk();

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/attachment",
            ['file' => $second],
        )->assertOk()
            ->assertJsonCount(2, 'data.attachments');

        $expense->refresh()->load('attachments');
        $this->assertCount(2, $expense->attachments);
        $this->assertSame('invoice-a.pdf', $expense->original_filename);

        $attachmentId = $expense->attachments->last()->id;

        $this->actingAs($this->user)->get(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/attachments/{$attachmentId}",
        )->assertOk();

        $this->actingAs($this->user)->deleteJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}/attachments/{$attachmentId}",
        )->assertOk()
            ->assertJsonCount(1, 'data.attachments');

        $expense->refresh()->load('attachments');
        $this->assertCount(1, $expense->attachments);
        $this->assertSame('invoice-a.pdf', $expense->original_filename);
    }

    #[Test]
    public function cancel_sets_cancelled_at(): void
    {
        $expense = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'N20260005',
            'issue_date' => now(),
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->user)->deleteJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}",
        )->assertOk()->assertJsonPath('data.status', 'cancelled');

        $expense->refresh();
        $this->assertNotNull($expense->cancelled_at);
    }

    #[Test]
    public function other_users_company_is_forbidden(): void
    {
        $other = User::factory()->create();
        $expense = BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => 'N20260003',
            'issue_date' => now(),
            'total' => 1,
            'currency' => 'EUR',
        ]);

        $this->actingAs($other)->getJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/{$expense->id}",
        )->assertForbidden();
    }
}
