<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Enums\CompanyStockMovementSource;
use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Models\CompanyStockItemMovement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Invoicing\CompanyStockItemImportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesCompanyStock;
use Tests\TestCase;

class CompanyStockItemImportTest extends TestCase
{
    use CreatesCompanyStock;
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
    public function user_can_download_stock_import_example(): void
    {
        $response = $this->actingAs($this->proUser)
            ->get("/api/invoicing/companies/{$this->company->id}/stock-items/import/example");

        $response->assertOk();
        $this->assertStringContainsString('stock_import_example.xlsx', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function user_can_import_stock_items_from_superfaktura_xlsx(): void
    {
        $file = $this->makeSpreadsheetUpload([
            CompanyStockItemImportFields::EXAMPLE_HEADERS,
            [
                'Jablká',
                'Jab-123',
                '1.5',
                '1',
                'EUR',
                'kg',
                '13',
                '',
                'Odroda Red Delicious',
                'FA001',
                '',
            ],
        ]);

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/stock-items/import", [
                'file' => $file,
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.imported', 1);

        $item = CompanyStockItem::query()->where('sku', 'Jab-123')->first();
        $this->assertNotNull($item);
        $this->assertSame('Jablká', $item->name);
        $this->assertEquals(13.0, (float) $item->quantity_on_hand);

        $this->assertDatabaseHas('company_stock_item_movements', [
            'company_stock_item_id' => $item->id,
            'source' => CompanyStockMovementSource::Import->value,
        ]);
    }

    #[Test]
    public function import_upserts_existing_item_by_sku(): void
    {
        $this->createStockItem($this->company, [
            'name' => 'Old name',
            'sku' => 'Jab-123',
            'sale_unit_price' => 1,
        ], quantity: 5);

        $file = $this->makeSpreadsheetUpload([
            CompanyStockItemImportFields::EXAMPLE_HEADERS,
            [
                'Jablká updated',
                'Jab-123',
                '2',
                '1.2',
                'EUR',
                'kg',
                '20',
                '',
                'New description',
                'FA002',
                '',
            ],
        ]);

        $import = $this->actingAs($this->proUser)
            ->post("/api/invoicing/companies/{$this->company->id}/stock-items/import", [
                'file' => $file,
            ]);

        $import->assertOk();
        $import->assertJsonPath('data.updated', 1);
        $import->assertJsonPath('data.imported', 0);

        $item = CompanyStockItem::query()->where('sku', 'Jab-123')->first();
        $this->assertSame('Jablká updated', $item->name);
        $this->assertEquals(20.0, (float) $item->quantity_on_hand);
        $this->assertEquals(2.0, (float) $item->sale_unit_price);

        $movementCount = CompanyStockItemMovement::query()
            ->where('company_stock_item_id', $item->id)
            ->where('source', CompanyStockMovementSource::Import)
            ->count();
        $this->assertSame(1, $movementCount);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeSpreadsheetUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'stock-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'stock.xlsx', null, null, true);
    }
}
