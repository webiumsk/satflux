<?php

namespace Tests\Feature;

use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Invoicing\BusinessExpenseImportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class BusinessExpenseImportTest extends TestCase
{
    use RefreshDatabase;

    private User $proUser;

    private Company $company;

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
    }

    #[Test]
    public function user_can_preview_and_import_expenses_from_superfaktura_xlsx(): void
    {
        $file = $this->makeSpreadsheetUpload([
            BusinessExpenseImportFields::EXAMPLE_HEADERS,
            [
                'Monacor káble',
                'Hifiaudio',
                'MONACOR',
                '250351',
                '2026001',
                '2026-01-15',
                '2026-01-29',
                '2026-01-15',
                '45.76',
                'EUR',
                '2026-01-20',
                '250351',
                '',
                '',
                'import',
                '',
                '12345678',
                '2123456789',
                'info@monacor.sk',
                'Jegorovova 5',
                'Banská Bystrica',
                'SK',
            ],
        ]);

        $preview = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel/preview", [
                'file' => $file,
            ]);

        $preview->assertOk();
        $preview->assertJsonPath('data.row_count', 1);
        $preview->assertJsonPath('data.suggested_mapping.internal_number', 4);
        $mapping = $preview->json('data.suggested_mapping');

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel", [
                'file' => $file,
                'mapping' => json_encode($mapping),
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.imported', 1);

        $expense = BusinessExpense::query()
            ->where('company_id', $this->company->id)
            ->where('internal_number', '2026001')
            ->first();

        $this->assertNotNull($expense);
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->status);
        $this->assertSame('250351', $expense->external_number);
        $this->assertSame('Monacor káble', $expense->title);
        $this->assertSame('45.76', $expense->total);
        $this->assertStringContainsString('import', $expense->internal_note ?? '');
    }

    #[Test]
    public function import_skips_duplicate_internal_numbers(): void
    {
        BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => '2026001',
            'issue_date' => '2026-01-01',
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $mapping = [
            'title' => 0,
            'internal_number' => 1,
            'issue_date' => 2,
            'total' => 3,
        ];

        $file = $this->makeSpreadsheetUpload([
            ['Názov', 'Interné číslo', 'Dátum vystavenia', 'Spolu'],
            ['Duplicate test', '2026001', '2026-02-01', '99'],
        ]);

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel", [
                'file' => $file,
                'mapping' => json_encode($mapping),
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.imported', 0);
        $import->assertJsonPath('data.skipped', 1);
        $import->assertJsonPath('data.errors.0.message', 'Internal number already exists: 2026001');
    }

    #[Test]
    public function user_can_attach_pdfs_from_zip_by_internal_number(): void
    {
        BusinessExpense::create([
            'company_id' => $this->company->id,
            'status' => BusinessExpenseStatus::Recorded,
            'internal_number' => '2026001',
            'external_number' => '250351',
            'issue_date' => '2026-01-15',
            'total' => 45.76,
            'currency' => 'EUR',
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'expense-pdf-zip-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('naklad_2026001.pdf', '%PDF-1.4 fake');
        $zip->addFromString('unknown.pdf', '%PDF-1.4 fake');
        $zip->close();

        $file = new UploadedFile($zipPath, 'expenses.zip', 'application/zip', null, true);

        $preview = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/expenses/import/attachments/preview", [
                'file' => $file,
            ]);

        $preview->assertOk();
        $preview->assertJsonPath('data.matched', 1);
        $preview->assertJsonPath('data.unmatched', 1);

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/expenses/import/attachments", [
                'file' => new UploadedFile($zipPath, 'expenses.zip', 'application/zip', null, true),
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.attached', 1);

        $expense = BusinessExpense::query()->where('internal_number', '2026001')->first();
        $this->assertTrue($expense->hasAttachment());
        $this->assertSame('naklad_2026001.pdf', $expense->original_filename);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeSpreadsheetUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'expense-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'expenses.xlsx', null, null, true);
    }
}
