<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentIsdocService;
use App\Services\Invoicing\BusinessExpenseIsdocImportService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpenseIsdocImportTest extends TestCase
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
            'legal_name' => 'Webium s.r.o.',
            'trade_name' => 'Webium',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'registration_number' => '47615681',
            'street' => 'Bohunice 47',
            'city' => 'Bohunice',
            'postal_code' => '93505',
            'country' => 'SK',
            'iban' => 'SK3112000000198747547509',
            'bank_name' => 'Tatra banka',
            'bank_account' => '8747547509',
            'bank_code' => '1100',
            'vat_payer' => false,
        ]);
    }

    protected function sampleIsdocXml(): string
    {
        $contact = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Dodávateľ s.r.o.',
            'registration_number' => '12345678',
            'street' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '81101',
            'country' => 'SK',
        ]);

        $doc = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $contact->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'FV20261234',
            'variable_symbol' => '20261234',
            'constant_symbol' => '0308',
            'total' => 121.50,
            'subtotal' => 100,
            'tax_total' => 21.50,
            'currency' => 'EUR',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'payment_bank_enabled' => true,
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $doc->id,
            'sort_order' => 0,
            'name' => 'Služba',
            'quantity' => 1,
            'unit' => 'ks.',
            'unit_price' => 100,
            'line_total' => 100,
            'tax_rate' => 21,
        ]);

        return app(BusinessDocumentIsdocService::class)->xml($doc->fresh(['company', 'contact', 'lines']));
    }

    #[Test]
    public function service_extracts_fields_from_isdoc_xml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exp-isdoc-');
        file_put_contents($path, $this->sampleIsdocXml());

        $draft = app(BusinessExpenseIsdocImportService::class)->extractFromPath($path);
        @unlink($path);

        $this->assertSame('isdoc', $draft['source']);
        $this->assertSame('FV20261234', $draft['external_number']);
        $this->assertSame('EUR', $draft['currency']);
        $this->assertSame('2026-06-01', $draft['issue_date']);
        $this->assertGreaterThan(0, $draft['total']);
        $this->assertNotEmpty($draft['title']);
    }

    #[Test]
    public function detect_endpoint_finds_isdoc_without_consuming_quota(): void
    {
        $file = UploadedFile::fake()->createWithContent('invoice.isdoc', $this->sampleIsdocXml());

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/detect-isdoc",
            ['file' => $file],
        );

        $response->assertOk();
        $response->assertJsonPath('data.has_isdoc', true);
        $response->assertJsonPath('data.quota.can_extract', true);

        $this->assertSame(0, AuditLog::query()
            ->where('user_id', $this->user->id)
            ->where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)
            ->count());
    }

    #[Test]
    public function extract_endpoint_returns_draft_json_and_records_quota(): void
    {
        $file = UploadedFile::fake()->createWithContent('invoice.isdoc', $this->sampleIsdocXml());

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/extract",
            ['file' => $file],
        );

        $response->assertOk();
        $response->assertJsonPath('data.source', 'isdoc');
        $response->assertJsonPath('data.external_number', 'FV20261234');
        $response->assertJsonPath('quota.used', 1);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => BusinessExpenseIsdocQuotaService::EXTRACT_ACTION,
        ]);
    }

    #[Test]
    public function extract_is_blocked_after_free_limit_without_pack(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 1]);

        $file = UploadedFile::fake()->createWithContent('invoice.isdoc', $this->sampleIsdocXml());

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/extract",
            ['file' => $file],
        )->assertOk();

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/extract",
            ['file' => $file],
        )->assertStatus(422);
    }

    #[Test]
    public function extract_uses_purchased_credits_after_free_tier(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        ExpenseIsdocCreditBalance::create([
            'user_id' => $this->user->id,
            'balance' => 2,
        ]);

        $file = UploadedFile::fake()->createWithContent('invoice.isdoc', $this->sampleIsdocXml());

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/extract",
            ['file' => $file],
        )->assertOk();

        $this->assertSame(1, ExpenseIsdocCreditBalance::query()
            ->where('user_id', $this->user->id)
            ->value('balance'));
    }

    #[Test]
    public function import_endpoint_creates_expense_with_attachment(): void
    {
        $file = UploadedFile::fake()->createWithContent('invoice.isdoc', $this->sampleIsdocXml());

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/expenses/import",
            ['file' => $file],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.external_number', 'FV20261234');
        $this->assertNotEmpty($response->json('data.attachment_path'));
        $this->assertNotEmpty($response->json('data.internal_number'));
    }
}
