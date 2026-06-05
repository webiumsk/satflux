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
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentImportTest extends TestCase
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
    public function user_can_preview_and_import_invoices_from_excel(): void
    {
        $file = $this->makeSpreadsheetUpload([
            ['Č. faktúry', 'Vytvorené', 'Dátum splatnosti', 'Názov / Meno', 'Suma', 'Fakturačná mena (kód ISO)', 'Dátum úhrady'],
            ['20230101', '2023-01-10', '2023-01-24', 'Import Client s.r.o.', '100', 'EUR', '2023-01-20'],
        ]);

        $preview = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/documents/import/preview", [
                'file' => $file,
            ]);

        $preview->assertOk();
        $preview->assertJsonPath('data.row_count', 1);
        $mapping = $preview->json('data.suggested_mapping');

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/documents/import", [
                'file' => $file,
                'mapping' => json_encode($mapping),
                'line_name' => 'Imported service',
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.imported', 1);

        $document = BusinessDocument::query()
            ->where('company_id', $this->company->id)
            ->where('number', '20230101')
            ->first();

        $this->assertNotNull($document);
        $this->assertSame(BusinessDocumentType::Invoice, $document->type);
        $this->assertSame(BusinessDocumentStatus::Paid, $document->status);
        $this->assertSame('100.00', $document->total);

        $import->assertJsonPath('data.contacts_created', 1);
        $import->assertJsonPath('data.contacts_linked', 0);

        $contact = CompanyContact::query()->where('company_id', $this->company->id)->first();
        $this->assertSame('Import Client s.r.o.', $contact->name);
    }

    #[Test]
    public function invoice_import_links_existing_contact_instead_of_duplicating(): void
    {
        CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Import Client s.r.o.',
            'registration_number' => '12345678',
        ]);

        $file = $this->makeSpreadsheetUpload([
            ['Č. faktúry', 'Vytvorené', 'Dátum splatnosti', 'Názov / Meno', 'IČO klienta', 'E-mail', 'Suma'],
            ['20230201', '2023-02-10', '2023-02-24', 'Import Client s.r.o.', '12345678', 'new@example.sk', '50'],
        ]);

        $preview = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/documents/import/preview", [
                'file' => $file,
            ]);

        $mapping = $preview->json('data.suggested_mapping');

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/documents/import", [
                'file' => $file,
                'mapping' => json_encode($mapping),
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.contacts_created', 0);
        $import->assertJsonPath('data.contacts_linked', 1);
        $this->assertSame(1, CompanyContact::query()->where('company_id', $this->company->id)->count());

        $contact = CompanyContact::query()->where('company_id', $this->company->id)->first();
        $this->assertSame('new@example.sk', $contact->email);
    }

    #[Test]
    public function import_returns_skipped_invoices_with_reasons(): void
    {
        $headers = ['Č. faktúry', 'Vytvorené', 'Dátum splatnosti', 'Názov / Meno', 'Suma'];
        $mapping = [
            'invoice_number' => 0,
            'issue_date' => 1,
            'due_date' => 2,
            'client_name' => 3,
            'amount' => 4,
        ];

        $firstFile = $this->makeSpreadsheetUpload([
            $headers,
            ['20230101', '2023-01-10', '2023-01-24', 'Import Client s.r.o.', '100'],
        ]);

        $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/documents/import", [
                'file' => $firstFile,
                'mapping' => json_encode($mapping),
            ])
            ->assertOk();

        $duplicateFile = $this->makeSpreadsheetUpload([
            $headers,
            ['20230101', '2023-01-10', '2023-01-24', 'Import Client s.r.o.', '100'],
            ['', '2023-02-01', '2023-02-15', 'Other Client', '50'],
        ]);

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/documents/import", [
                'file' => $duplicateFile,
                'mapping' => json_encode($mapping),
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.imported', 0);
        $import->assertJsonPath('data.skipped', 2);
        $import->assertJsonPath('data.errors.0.row', 2);
        $import->assertJsonPath('data.errors.0.invoice_number', '20230101');
        $import->assertJsonPath('data.errors.0.message', 'Invoice number already exists: 20230101');
        $import->assertJsonPath('data.errors.1.row', 3);
        $import->assertJsonPath('data.errors.1.message', 'Invoice number is required.');
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeSpreadsheetUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'invoice-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'invoices.xlsx', null, null, true);
    }
}
