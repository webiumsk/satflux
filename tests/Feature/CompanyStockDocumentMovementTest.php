<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Enums\CompanyStockMovementSource;
use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyStockDocumentMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $proUser;

    private Company $company;

    private CompanyStockItem $stockItem;

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

        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);

        $this->stockItem = CompanyStockItem::create([
            'company_id' => $this->company->id,
            'name' => 'Jablká',
            'sku' => 'Jab-123',
            'unit' => 'kg',
            'quantity_on_hand' => 10,
            'sale_unit_price' => 1.5,
            'track_inventory' => true,
        ]);
    }

    #[Test]
    public function issuing_invoice_deducts_stock_and_cancel_restores_it(): void
    {
        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                'type' => 'invoice',
                'currency' => 'EUR',
                'lines' => [
                    [
                        'name' => 'Jablká',
                        'quantity' => 3,
                        'unit' => 'kg',
                        'unit_price' => 1.5,
                        'company_stock_item_id' => $this->stockItem->id,
                    ],
                ],
            ]);
        $create->assertCreated();
        $documentId = $create->json('data.id');

        $issue = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$documentId}/issue");
        $issue->assertOk();

        $this->stockItem->refresh();
        $this->assertEquals(7.0, (float) $this->stockItem->quantity_on_hand);

        $this->assertDatabaseHas('company_stock_item_movements', [
            'company_stock_item_id' => $this->stockItem->id,
            'business_document_id' => $documentId,
            'source' => CompanyStockMovementSource::DocumentIssue->value,
            'quantity_delta' => -3,
        ]);

        $cancel = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$documentId}/cancel");
        $cancel->assertOk();

        $this->stockItem->refresh();
        $this->assertEquals(10.0, (float) $this->stockItem->quantity_on_hand);

        $this->assertDatabaseHas('company_stock_item_movements', [
            'company_stock_item_id' => $this->stockItem->id,
            'business_document_id' => $documentId,
            'source' => CompanyStockMovementSource::DocumentCancel->value,
            'quantity_delta' => 3,
        ]);
    }

    #[Test]
    public function issuing_credit_note_returns_stock(): void
    {
        $this->stockItem->update(['quantity_on_hand' => 5]);

        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                'type' => 'credit_note',
                'currency' => 'EUR',
                'lines' => [
                    [
                        'name' => 'Jablká',
                        'quantity' => 2,
                        'unit' => 'kg',
                        'unit_price' => 1.5,
                        'company_stock_item_id' => $this->stockItem->id,
                    ],
                ],
            ]);
        $create->assertCreated();
        $documentId = $create->json('data.id');

        $issue = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$documentId}/issue");
        $issue->assertOk();

        $this->stockItem->refresh();
        $this->assertEquals(7.0, (float) $this->stockItem->quantity_on_hand);
    }

    #[Test]
    public function issuing_quote_does_not_change_stock(): void
    {
        $create = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents", [
                'type' => 'quote',
                'currency' => 'EUR',
                'lines' => [
                    [
                        'name' => 'Jablká',
                        'quantity' => 3,
                        'unit' => 'kg',
                        'unit_price' => 1.5,
                        'company_stock_item_id' => $this->stockItem->id,
                    ],
                ],
            ]);
        $create->assertCreated();
        $documentId = $create->json('data.id');

        $issue = $this->actingAs($this->proUser)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$documentId}/issue");
        $issue->assertOk();

        $this->stockItem->refresh();
        $this->assertEquals(10.0, (float) $this->stockItem->quantity_on_hand);
    }
}
