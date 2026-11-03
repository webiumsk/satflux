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
use App\Services\Invoicing\BusinessDocumentPdfService;
use App\Services\Invoicing\BusinessExpenseIsdocImportService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use App\Services\Invoicing\BusinessExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
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
    public function service_extracts_isdoc_when_temp_path_has_no_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exp-isdoc-');
        file_put_contents($path, $this->sampleIsdocXml());

        $service = app(BusinessExpenseIsdocImportService::class);
        $this->assertTrue($service->hasIsdocAtPath($path, 'isdoc'));

        $draft = $service->extractFromPath($path, 'isdoc');
        @unlink($path);

        $this->assertSame('FV20261234', $draft['external_number']);
    }

    #[Test]
    public function service_extracts_fields_from_isdoc_xml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exp-isdoc-');
        file_put_contents($path, $this->sampleIsdocXml());

        $draft = app(BusinessExpenseIsdocImportService::class)->extractFromPath($path, 'isdoc');
        @unlink($path);

        $this->assertSame('isdoc', $draft['source']);
        $this->assertSame('FV20261234', $draft['external_number']);
        $this->assertSame('EUR', $draft['currency']);
        $this->assertSame('2026-06-01', $draft['issue_date']);
        $this->assertGreaterThan(0, $draft['total']);
        $this->assertNotEmpty($draft['title']);
    }

    #[Test]
    public function service_detects_isdoc_in_satflux_issued_pdf(): void
    {
        $doc = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'FV20261234',
            'variable_symbol' => '20261234',
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

        $doc = $doc->fresh(['company', 'contact', 'lines']);
        $xml = app(BusinessDocumentIsdocService::class)->xml($doc);
        $this->assertStringContainsString('<Invoice', $xml);

        $binary = app(BusinessDocumentPdfService::class)->renderBinary($doc);
        $this->assertStringContainsString('invoice.isdoc', $binary);

        $path = tempnam(sys_get_temp_dir(), 'exp-satflux-pdf-');
        file_put_contents($path, $binary);

        $service = app(BusinessExpenseIsdocImportService::class);
        $this->assertTrue(
            $service->hasIsdocAtPath($path, 'pdf'),
            'Satflux PDF with embedded ISDOC should be detected. XML prefix: '.substr($xml, 0, 30),
        );

        $draft = $service->extractFromPath($path, 'pdf');
        @unlink($path);

        $this->assertSame('FV20261234', $draft['external_number']);
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

    #[Test]
    public function legacy_import_rechecks_quota_before_persisting_an_expense_or_file(): void
    {
        Storage::fake('local');
        config(['invoicing.expense_isdoc_extract_free_limit' => 1]);
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractFromUpload')->once()->andReturnUsing(function () {
                AuditLog::log(BusinessExpenseIsdocQuotaService::EXTRACT_ACTION, 'business_expense', null, ['billing' => 'free'], $this->user->id);

                return ['issue_date' => '2026-06-01', 'total' => 12.5, 'currency' => 'EUR'];
            });
        });
        $this->actingAs($this->user)->postJson("/api/invoicing/companies/{$this->company->id}/expenses/import", [
            'file' => UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('quota');
        $this->assertDatabaseCount('business_expenses', 0);
        $this->assertDatabaseCount('business_expense_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function legacy_import_failure_removes_only_its_new_directory_and_rolls_back_rows_and_usage(): void
    {
        Storage::fake('local');
        $sentinel = "companies/{$this->company->id}/expenses/".Str::uuid().'/sentinel.txt';
        Storage::disk('local')->put($sentinel, 'preserve');
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractFromUpload')->once()->andReturn(['issue_date' => '2026-06-01', 'total' => 12.5, 'currency' => 'EUR']);
        });
        AuditLog::creating(function (AuditLog $audit) {
            if ($audit->action === BusinessExpenseIsdocQuotaService::EXTRACT_ACTION) {
                throw new \RuntimeException('Synthetic audit failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->user)->postJson("/api/invoicing/companies/{$this->company->id}/expenses/import", [
                'file' => UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF\n"),
            ]);
            $this->fail('The synthetic persistence failure must propagate.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic audit failure.', $error->getMessage());
        }
        $this->assertDatabaseCount('business_expenses', 0);
        $this->assertDatabaseCount('business_expense_attachments', 0);
        $this->assertSame(0, AuditLog::where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->count());
        $this->assertSame([$sentinel], Storage::disk('local')->allFiles());
        $this->assertSame('preserve', Storage::disk('local')->get($sentinel));
    }

    #[Test]
    public function legacy_import_never_claims_or_deletes_an_existing_candidate_directory(): void
    {
        Storage::fake('local');
        $candidate = null;
        $service = app(BusinessExpenseService::class);
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractFromUpload')->once()->andReturn(['issue_date' => '2026-06-01', 'total' => 12.5, 'currency' => 'EUR']);
        });
        $this->mock(BusinessExpenseService::class, function (MockInterface $mock) use ($service, &$candidate) {
            $mock->shouldReceive('create')->once()->andReturnUsing(function (...$arguments) use ($service, &$candidate) {
                $expense = $service->create(...$arguments);
                $candidate = "companies/{$this->company->id}/expenses/{$expense->id}/preexisting.txt";
                Storage::disk('local')->put($candidate, 'preserve');

                return $expense;
            });
            $mock->shouldNotReceive('storeAttachment');
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->user)->postJson("/api/invoicing/companies/{$this->company->id}/expenses/import", [
                'file' => UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF\n"),
            ]);
            $this->fail('An existing candidate directory must be rejected.');
        } catch (\LogicException $error) {
            $this->assertSame('The new expense attachment directory already exists.', $error->getMessage());
        }
        $this->assertDatabaseCount('business_expenses', 0);
        $this->assertDatabaseCount('business_expense_attachments', 0);
        $this->assertSame('preserve', Storage::disk('local')->get($candidate));
        $this->assertSame(0, AuditLog::where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->count());
    }
}
