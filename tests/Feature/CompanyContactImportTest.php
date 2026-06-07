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
use App\Support\Invoicing\CompanyContactImportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyContactImportTest extends TestCase
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
    public function user_can_download_import_example(): void
    {
        $response = $this->actingAs($this->proUser)
            ->get("/api/invoicing/companies/{$this->company->id}/contacts/import/example");

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString('client_import_example.xlsx', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function user_can_import_contacts_from_superfaktura_xlsx(): void
    {
        $file = $this->makeSpreadsheetUpload([
            CompanyContactImportFields::EXAMPLE_HEADERS,
            [
                'Vzorový klient s.r.o.',
                'Kvetná 1',
                '123 45',
                'Bratislava',
                'SK',
                '12345678',
                '2123456789',
                'SK2123456789',
                'vzory@example.sk',
                '0903123456',
                '',
                '',
                'Dodacia 9',
                '999 99',
                'Košice',
                'SK',
                'https://example.sk',
                'Poznámka klienta',
                '21',
                '',
                'EUR',
                'SK3112000000198747547501',
                'GIBASKBX',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'invalid-email',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ]);

        $response = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/contacts/import", [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.imported', 1);
        $response->assertJsonPath('data.skipped', 1);
        $this->assertCount(1, $response->json('data.errors'));

        $contact = CompanyContact::query()->where('company_id', $this->company->id)->first();
        $this->assertNotNull($contact);
        $this->assertSame('Vzorový klient s.r.o.', $contact->name);
        $this->assertSame('Kvetná 1', $contact->street);
        $this->assertSame('Bratislava', $contact->city);
        $this->assertSame('Slovensko', $contact->country);
        $this->assertSame('vzory@example.sk', $contact->email);
        $this->assertSame(21, $contact->default_payment_terms_days);
        $this->assertSame('Dodacia 9', $contact->delivery_street);
        $this->assertSame('Košice', $contact->delivery_city);
        $this->assertStringContainsString('Poznámka klienta', $contact->notes ?? '');
        $this->assertStringContainsString('https://example.sk', $contact->notes ?? '');
    }

    #[Test]
    public function contact_import_preview_returns_suggested_mapping(): void
    {
        $file = $this->makeSpreadsheetUpload([
            CompanyContactImportFields::EXAMPLE_HEADERS,
            ['Test Client', 'Street 1', '81101', 'Bratislava', 'SK', '', '', '', 'test@example.sk', '', '', '', '', '', '', '', '', '', '14', '', '', '', ''],
        ]);

        $response = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/contacts/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.row_count', 1);
        $response->assertJsonPath('data.suggested_mapping.name', 0);
        $response->assertJsonPath('data.suggested_mapping.email', 8);
    }

    #[Test]
    public function user_can_import_contacts_with_custom_column_mapping(): void
    {
        $file = $this->makeSpreadsheetUpload([
            ['Company', 'Mail', 'Town'],
            ['Custom Mapped s.r.o.', 'mapped@example.sk', 'Košice'],
        ]);

        $mapping = [
            'name' => 0,
            'email' => 1,
            'city' => 2,
        ];

        $response = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/contacts/import", [
                'file' => $file,
                'mapping' => json_encode($mapping),
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.imported', 1);

        $contact = CompanyContact::query()->where('company_id', $this->company->id)->first();
        $this->assertSame('Custom Mapped s.r.o.', $contact->name);
        $this->assertSame('mapped@example.sk', $contact->email);
        $this->assertSame('Košice', $contact->city);
    }

    #[Test]
    public function user_can_export_selected_contacts_as_xlsx(): void
    {
        CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Export Test s.r.o.',
            'street' => 'Hlavná 5',
            'city' => 'Bratislava',
            'country' => 'Slovensko',
            'registration_number' => '99887766',
            'email' => 'export@example.sk',
            'default_payment_terms_days' => 14,
        ]);

        $contact = CompanyContact::query()->where('company_id', $this->company->id)->first();

        $response = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/contacts/bulk", [
                'action' => 'export_xlsx',
                'contact_ids' => [$contact->id],
            ]);

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString('contacts.xlsx', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function user_can_bulk_delete_contacts(): void
    {
        $deletable = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'No invoices s.r.o.',
        ]);

        $withInvoice = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'Has invoice s.r.o.',
            'email' => 'keep-history@example.sk',
        ]);

        BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $withInvoice->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'lines' => [],
        ]);

        $response = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/contacts/bulk", [
                'action' => 'delete',
                'contact_ids' => [$deletable->id, $withInvoice->id],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted', 1);
        $response->assertJsonPath('data.anonymized', 1);

        $this->assertDatabaseMissing('company_contacts', ['id' => $deletable->id]);
        $this->assertDatabaseHas('company_contacts', ['id' => $withInvoice->id]);
        $withInvoice->refresh();
        $this->assertNull($withInvoice->email);
        $this->assertStringContainsString('Removed contact', $withInvoice->name);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeSpreadsheetUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'contacts-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'clients.xlsx', null, null, true);
    }
}
