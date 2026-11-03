<?php

namespace Tests\Feature;

use App\Enums\BusinessExpenseStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessExpenseAttachmentBulkService;
use App\Services\Invoicing\BusinessExpenseExcelImportService;
use App\Services\Invoicing\BusinessExpenseService;
use App\Support\Invoicing\BusinessExpenseImportFields;
use App\Support\Spreadsheet\NoFormulaValueBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class BusinessExpenseImportTest extends TestCase
{
    use RefreshDatabase;

    private const SUPERFAKTURA_HEADERS = [
        'Názov', 'Kategória', 'Dodávateľ', 'Číslo dokladu', 'Interné číslo dokladu',
        'Dátum vystavenia', 'Dátum splatnosti', 'Dátum dodania', 'Spolu', 'Mena',
        'Dátum úhrady', 'Druh úhrady', 'Suma úhrady', 'Mena úhrady', 'Kurz úhrady',
        'VS', 'Tagy', 'Bankový účet dodávateľa', 'Dodávateľ IČO', 'Dodávateľ DIČ',
        'Dodávateľ IČ DPH', 'Dodávateľ telefón', 'Dodávateľ email', 'Dodávateľ adresa',
        'Dodávateľ PSČ', 'Dodávateľ mesto', 'Dodávateľ krajina', 'Interná poznámka',
        'Konštantný symbol', 'Špecifický symbol', 'Počet položiek', 'Jednotky položiek', 'Položky',
    ];

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
    public function complete_superfaktura_layout_preserves_all_fields_and_original_payment_date(): void
    {
        $row = $this->superfakturaRow();
        $file = $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $row]);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $preview = $this->actingAs($this->proUser)->post($url.'/preview', ['file' => $file]);
        $preview->assertOk()->assertJsonPath('data.row_count', 1);
        $mapping = $preview->json('data.suggested_mapping');
        $this->assertCount(33, $mapping);
        $this->assertNotContains(null, array_values($mapping));
        $this->assertSame(4, $mapping['internal_number']);
        $this->assertSame(20, $mapping['supplier_vat_id']);
        $this->assertSame(26, $mapping['supplier_country']);

        $this->post($url, ['file' => $file, 'mapping' => json_encode($mapping)])
            ->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped', 0);
        $expense = BusinessExpense::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame('000001', $expense->internal_number);
        $this->assertSame('000042', $expense->external_number);
        $this->assertSame('001234', $expense->variable_symbol);
        $this->assertSame('0308', $expense->constant_symbol);
        $this->assertSame('0005', $expense->specific_symbol);
        $this->assertSame('45000.00', $expense->total);
        $this->assertSame('2025-01-01', $expense->issue_date->toDateString());
        $this->assertSame('2025-01-15', $expense->due_date->toDateString());
        $this->assertSame('2025-01-02', $expense->paid_at->toDateString());
        $this->assertSame(BusinessExpenseStatus::Paid, $expense->status);
        foreach ([
            'Private synthetic note', 'Tags: synthetic', 'Supplier: Example Supplier',
            'Category: Office', 'Supplier registration number: 00112233', 'Supplier tax ID: 0011223344',
            'Supplier VAT ID: SK0011223344', 'Supplier phone: +421900000000',
            'Supplier email: supplier@example.test', 'Supplier street: Example Street 1',
            'Supplier postal code: 01234', 'Supplier city: Example City', 'Supplier country: SK',
            'Supplier bank account: SYNTHETIC-ACCOUNT', 'Payment method: Bank transfer',
            'Payment amount: 45000', 'Payment currency: EUR', 'Payment exchange rate: 1',
            'Item quantity: 2', 'Item units: pcs', 'Items: Synthetic supplies',
        ] as $value) {
            $this->assertStringContainsString($value, $expense->internal_note ?? '');
        }
        $this->post($url, ['file' => $file, 'mapping' => json_encode($mapping)])
            ->assertOk()->assertJsonPath('data.imported', 0)->assertJsonPath('data.skipped', 1);
        $this->assertSame(1, BusinessExpense::where('company_id', $this->company->id)->count());
    }

    #[Test]
    public function partial_and_unresolved_foreign_currency_payments_remain_recorded_with_evidence(): void
    {
        $partial = $this->superfakturaRow();
        $partial[12] = '100';
        $foreign = $this->superfakturaRow();
        $foreign[4] = '000002';
        $foreign[13] = 'USD';
        $file = $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $partial, $foreign]);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $mapping = app(BusinessExpenseExcelImportService::class)->suggestMapping(self::SUPERFAKTURA_HEADERS);

        $this->actingAs($this->proUser)->post($url, ['file' => $file, 'mapping' => json_encode($mapping)])
            ->assertOk()->assertJsonPath('data.imported', 2);
        foreach (BusinessExpense::where('company_id', $this->company->id)->get() as $expense) {
            $this->assertSame(BusinessExpenseStatus::Recorded, $expense->status);
            $this->assertNull($expense->paid_at);
            $this->assertStringContainsString('Payment date: 2025-01-02', $expense->internal_note);
            $this->assertStringContainsString('Payment amount:', $expense->internal_note);
        }
    }

    #[Test]
    public function invalid_core_values_and_optional_dates_are_skipped_without_partial_row_writes(): void
    {
        $rows = [];
        foreach ([5 => '2025-02-30', 6 => '2025-02-30', 7 => 'tomorrow', 10 => '2025-02-30', 8 => 'EUR 5', 9 => 'EURO', 27 => str_repeat('x', 4001)] as $column => $value) {
            $row = $this->superfakturaRow();
            $row[$column] = $value;
            $rows[] = $row;
        }
        $mapping = app(BusinessExpenseExcelImportService::class)->suggestMapping(self::SUPERFAKTURA_HEADERS);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $this->actingAs($this->proUser)->post($url, [
            'file' => $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, ...$rows]),
            'mapping' => json_encode($mapping),
        ])->assertOk()->assertJsonPath('data.imported', 0)->assertJsonPath('data.skipped', count($rows));
        $this->assertDatabaseCount('business_expenses', 0);
    }

    #[Test]
    public function import_does_not_calculate_formulas_and_rejects_formula_amounts(): void
    {
        $literal = $this->superfakturaRow();
        $literal[0] = '=1+1';
        $formula = $this->superfakturaRow();
        $formula[4] = '000002';
        $formula[8] = '=45000';
        $mapping = app(BusinessExpenseExcelImportService::class)->suggestMapping(self::SUPERFAKTURA_HEADERS);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $this->actingAs($this->proUser)->post($url, [
            'file' => $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $literal, $formula]),
            'mapping' => json_encode($mapping),
        ])->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped', 1);
        $this->assertSame('=1+1', BusinessExpense::firstOrFail()->title);
    }

    #[Test]
    public function duplicate_checks_are_company_scoped_and_cover_duplicates_within_one_file(): void
    {
        $otherCompany = Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Other Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
        ]);
        BusinessExpense::create([
            'company_id' => $otherCompany->id,
            'internal_number' => '000001',
            'issue_date' => '2025-01-01',
            'total' => 10,
            'currency' => 'EUR',
        ]);
        $mapping = app(BusinessExpenseExcelImportService::class)->suggestMapping(self::SUPERFAKTURA_HEADERS);
        $this->actingAs($this->proUser)->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel", [
            'file' => $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $this->superfakturaRow(), $this->superfakturaRow()]),
            'mapping' => json_encode($mapping),
        ])->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped', 1);
        $this->assertSame(1, BusinessExpense::where('company_id', $otherCompany->id)->count());
        $this->actingAs($this->proUser)->post("/api/invoicing/companies/{$otherCompany->id}/expenses/import/excel", [
            'file' => $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $this->superfakturaRow()]),
            'mapping' => json_encode($mapping),
        ])->assertForbidden();
    }

    #[Test]
    public function malformed_mapping_indexes_are_rejected_before_import(): void
    {
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        foreach ([-1, 33, 'not-a-column', [], 1.5] as $invalid) {
            $this->actingAs($this->proUser)->post($url, [
                'file' => $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $this->superfakturaRow()]),
                'mapping' => json_encode(['issue_date' => 5, 'total' => $invalid]),
            ])->assertStatus(422);
        }
        $this->assertDatabaseCount('business_expenses', 0);
    }

    #[Test]
    public function import_rejects_excess_rows_before_persisting_any_expense(): void
    {
        $this->actingAs($this->proUser)->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel", [
            'file' => $this->makeSpreadsheetUpload([['Issue date', 'Total'], ...array_fill(0, 2001, ['2025-01-01', '10'])]),
            'mapping' => json_encode(['issue_date' => 0, 'total' => 1]),
        ])->assertStatus(422)->assertJsonPath('message', 'Row limit exceeded.');
        $this->assertDatabaseCount('business_expenses', 0);
    }

    #[Test]
    public function numeric_cells_preserve_identifier_zeros_without_applying_amount_display_formats(): void
    {
        $row = $this->superfakturaRow();
        $row[4] = 1;
        $row[5] = 45658;
        $row[8] = 45000;
        $mapping = app(BusinessExpenseExcelImportService::class)->suggestMapping(self::SUPERFAKTURA_HEADERS);
        $this->actingAs($this->proUser)->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel", [
            'file' => $this->makeSpreadsheetUpload([self::SUPERFAKTURA_HEADERS, $row], [
                'E2' => '000000', 'F2' => 'dd.mm.yyyy', 'I2' => '#,##0.00 "EUR"',
            ]),
            'mapping' => json_encode($mapping),
        ])->assertOk()->assertJsonPath('data.imported', 1);
        $expense = BusinessExpense::firstOrFail();
        $this->assertSame('000001', $expense->internal_number);
        $this->assertSame('45000.00', $expense->total);
        $this->assertSame('2025-01-01', $expense->issue_date->toDateString());
    }

    #[Test]
    public function csv_import_preserves_text_identifiers_without_calculating_formulas(): void
    {
        $file = $this->makeCsvUpload([
            ['Title', 'Internal number', 'Invoice number', 'Issue date', 'Total', 'Supplier phone', 'Supplier bank account'],
            ['=1+1', 'CSV-TEXT', '+12345', '2025-01-01', '12.50', '+421900000000', '123456789012345678901234567890'],
            ['Formula amount', 'CSV-FORMULA', '+54321', '2025-01-01', '=2+2', '+421900000001', '001234567890123456789012345678'],
        ]);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $preview = $this->actingAs($this->proUser)->post($url.'/preview', ['file' => $file]);
        $preview->assertOk()
            ->assertJsonPath('data.preview.0.external_number', '+12345')
            ->assertJsonPath('data.preview.0.title', '=1+1')
            ->assertJsonPath('data.preview.0.total', '12.50')
            ->assertJsonPath('data.preview.1.error', 'Total amount is invalid or missing.');
        $mapping = $preview->json('data.suggested_mapping');

        $this->post($url, ['file' => $file, 'mapping' => json_encode($mapping)])
            ->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped', 1);

        $expense = BusinessExpense::firstOrFail();
        $this->assertSame('+12345', $expense->external_number);
        $this->assertSame('=1+1', $expense->title);
        $this->assertSame('12.50', $expense->total);
        $this->assertStringContainsString('Supplier phone: +421900000000', $expense->internal_note);
        $this->assertStringContainsString('Supplier bank account: 123456789012345678901234567890', $expense->internal_note);
        $this->assertInstanceOf(NoFormulaValueBinder::class, Cell::getValueBinder());
    }

    #[Test]
    public function fractional_totals_are_not_rounded_by_plain_or_padded_integer_formats(): void
    {
        $file = $this->makeSpreadsheetUpload([
            ['Internal number', 'Issue date', 'Total'],
            [1, '2025-01-01', 1234.56],
            [2, '2025-01-02', 12.5],
        ], ['A2' => '000000', 'A3' => '000000', 'C2' => '0', 'C3' => '00']);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $preview = $this->actingAs($this->proUser)->post($url.'/preview', ['file' => $file]);
        $preview->assertOk()
            ->assertJsonPath('data.preview.0.internal_number', '000001')
            ->assertJsonPath('data.preview.0.total', '1234.56')
            ->assertJsonPath('data.preview.1.internal_number', '000002')
            ->assertJsonPath('data.preview.1.total', '12.50');

        $this->post($url, ['file' => $file, 'mapping' => json_encode($preview->json('data.suggested_mapping'))])
            ->assertOk()->assertJsonPath('data.imported', 2);
        $this->assertSame('1234.56', BusinessExpense::where('internal_number', '000001')->firstOrFail()->total);
        $this->assertSame('12.50', BusinessExpense::where('internal_number', '000002')->firstOrFail()->total);
    }

    #[Test]
    public function dashed_supplier_vat_phone_and_postal_headers_map_in_preview_and_import(): void
    {
        $headers = self::SUPERFAKTURA_HEADERS;
        $headers[20] = 'Dodávateľ - IČ DPH';
        $headers[21] = 'Dodávateľ - telefón';
        $headers[24] = 'Dodávateľ - PSČ';
        $file = $this->makeSpreadsheetUpload([$headers, $this->superfakturaRow()]);
        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/excel";
        $preview = $this->actingAs($this->proUser)->post($url.'/preview', ['file' => $file]);
        $preview->assertOk()
            ->assertJsonPath('data.suggested_mapping.supplier_vat_id', 20)
            ->assertJsonPath('data.suggested_mapping.supplier_phone', 21)
            ->assertJsonPath('data.suggested_mapping.supplier_postal_code', 24);

        $this->post($url, ['file' => $file, 'mapping' => json_encode($preview->json('data.suggested_mapping'))])
            ->assertOk()->assertJsonPath('data.imported', 1);
        $note = BusinessExpense::firstOrFail()->internal_note;
        $this->assertStringContainsString('Supplier VAT ID: SK0011223344', $note);
        $this->assertStringContainsString('Supplier phone: +421900000000', $note);
        $this->assertStringContainsString('Supplier postal code: 01234', $note);
    }

    #[Test]
    public function generated_numbers_do_not_take_explicit_numbers_from_later_rows(): void
    {
        $prefix = 'EXP'.now()->year;
        $this->actingAs($this->proUser)->post("/api/invoicing/companies/{$this->company->id}/expenses/import/excel", [
            'file' => $this->makeSpreadsheetUpload([
                ['Title', 'Internal number', 'Issue date', 'Total'],
                ['Generated first', '', '2025-01-01', '10'],
                ['Explicit later', $prefix.'0001', '2025-01-01', '20'],
            ]),
            'mapping' => json_encode(['title' => 0, 'internal_number' => 1, 'issue_date' => 2, 'total' => 3]),
        ])->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', 0);
        $this->assertDatabaseHas('business_expenses', ['title' => 'Generated first', 'internal_number' => $prefix.'0002']);
        $this->assertDatabaseHas('business_expenses', ['title' => 'Explicit later', 'internal_number' => $prefix.'0001']);
    }

    /** @return list<string> */
    private function superfakturaRow(): array
    {
        return [
            'Synthetic expense', 'Office', 'Example Supplier', '000042', '000001',
            '45658', '45672', '45658', '45000', 'EUR', '45659', 'Bank transfer', '45000', 'EUR', '1',
            '001234', 'synthetic', 'SYNTHETIC-ACCOUNT', '00112233', '0011223344', 'SK0011223344',
            '+421900000000', 'supplier@example.test', 'Example Street 1', '01234', 'Example City', 'SK',
            'Private synthetic note', '0308', '0005', '2', 'pcs', 'Synthetic supplies',
        ];
    }

    #[Test]
    public function zip_entries_that_inflate_past_the_limit_are_rejected(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'expense-pdf-bomb-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        // ~11 MB of zeros compresses to a few KB but inflates past the 10 MB entry cap.
        $zip->addFromString('bomb.pdf', '%PDF-1.4 '.str_repeat("\0", 11 * 1024 * 1024));
        $zip->close();

        $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/expenses/import/attachments/preview", [
                'file' => new UploadedFile($zipPath, 'expenses.zip', 'application/zip', null, true),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains((string) $message, 'too large'));
    }

    /**
     * @return list<string>
     */
    private function extractionTempDirs(): array
    {
        return glob(sys_get_temp_dir().'/sf-expense-pdf-*') ?: [];
    }

    #[Test]
    public function zip_extraction_leaves_no_temp_files_behind(): void
    {
        $before = $this->extractionTempDirs();

        $okZip = tempnam(sys_get_temp_dir(), 'expense-pdf-ok-').'.zip';
        $zip = new ZipArchive;
        $zip->open($okZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('naklad_1.pdf', '%PDF-1.4 fake');
        $zip->close();

        $badZip = tempnam(sys_get_temp_dir(), 'expense-pdf-bad-').'.zip';
        $zip = new ZipArchive;
        $zip->open($badZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('a_small.pdf', '%PDF-1.4 fine');
        $zip->addFromString('b_bomb.pdf', '%PDF-1.4 '.str_repeat("\0", 11 * 1024 * 1024));
        $zip->close();

        $url = "/api/invoicing/companies/{$this->company->id}/expenses/import/attachments/preview";
        $this->actingAs($this->proUser)
            ->post($url, ['file' => new UploadedFile($okZip, 'ok.zip', 'application/zip', null, true)])
            ->assertOk();
        $this->actingAs($this->proUser)
            ->post($url, ['file' => new UploadedFile($badZip, 'bad.zip', 'application/zip', null, true)])
            ->assertStatus(422);

        $this->assertSame($before, $this->extractionTempDirs());
    }

    #[Test]
    public function failed_entry_setup_leaves_no_files_or_open_handles(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'expense-pdf-setup-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('real.pdf', '%PDF-1.4 fake');
        $zip->close();
        $zip->open($zipPath);

        $service = new class(app(BusinessExpenseService::class)) extends BusinessExpenseAttachmentBulkService
        {
            public function extract(ZipArchive $zip, string $name, string $target): int
            {
                return $this->extractEntryWithLimit($zip, $name, $target, PHP_INT_MAX);
            }
        };

        // The entry stream cannot be opened: no target file may be created.
        $target = sys_get_temp_dir().'/sf-setup-'.uniqid().'.pdf';
        try {
            $service->extract($zip, 'missing.pdf', $target);
            $this->fail('A missing entry must be rejected');
        } catch (\InvalidArgumentException) {
            $this->assertFileDoesNotExist($target);
        }

        // The target cannot be opened: the entry stream is closed, nothing written.
        $unwritable = sys_get_temp_dir().'/sf-no-such-dir-'.uniqid().'/x.pdf';
        try {
            $service->extract($zip, 'real.pdf', $unwritable);
            $this->fail('An unwritable target must be rejected');
        } catch (\InvalidArgumentException) {
            $this->assertFileDoesNotExist($unwritable);
        }

        $zip->close();
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
    private function makeSpreadsheetUpload(array $rows, array $formats = []): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $cell = $sheet->getCell([$columnIndex + 1, $rowIndex + 1]);
                if (is_string($value) && ! str_starts_with($value, '=')) {
                    // Match SuperFaktura inline-string exports without the default
                    // value binder removing identifier zeros or a phone's plus.
                    $cell->setValueExplicit($value, DataType::TYPE_STRING);
                } else {
                    $cell->setValue($value);
                }
            }
        }
        foreach ($formats as $address => $format) {
            $sheet->getStyle($address)->getNumberFormat()->setFormatCode($format);
        }

        $path = tempnam(sys_get_temp_dir(), 'expense-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'expenses.xlsx', null, null, true);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeCsvUpload(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'expense-import-').'.csv';
        $stream = fopen($path, 'w');
        if ($stream === false) {
            throw new \RuntimeException('Could not create synthetic CSV.');
        }
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        fclose($stream);

        return new UploadedFile($path, 'expenses.csv', 'text/csv', null, true);
    }
}
