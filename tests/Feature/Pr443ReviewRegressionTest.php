<?php

namespace Tests\Feature;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Jobs\SubmitBusinessDocumentCompliance;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Models\CompanyWarehouse;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentMarkPaidService;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class Pr443ReviewRegressionTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    private Company $company;

    public function runDatabaseMigrations(): void
    {
        // Real commits are required here. Each test gets a fresh schema;
        // the disposable database is discarded instead of rolling back all historical migrations.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro', 'price_eur' => 99,
            'billing_period' => 'year', 'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id, 'legal_name' => 'Synthetic Supplier',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'vat_payer' => true,
            'vat_status' => 'payer', 'default_currency' => 'EUR',
        ]);
        $this->actingAs($this->user);
    }

    public function test_credit_note_checkout_is_rejected_before_creation_or_existing_provider_lookup(): void
    {
        Http::fake(['*' => Http::response(['id' => 'synthetic-checkout', 'checkoutLink' => 'https://btcpay.test/i/synthetic'])]);
        $this->user->update(['btcpay_api_key' => 'synthetic-key']);
        $store = Store::factory()->create(['user_id' => $this->user->id]);
        $payload = [
            'store_id' => $store->id,
            'company' => ['legal_name' => 'Synthetic Supplier', 'jurisdiction' => 'eu_sk'],
            'document' => ['type' => 'credit_note', 'status' => 'issued', 'number' => 'SYNTHETIC-CN', 'currency' => 'EUR'],
            'lines' => [['name' => 'Credit', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
        ];
        foreach ([100, 0, -100] as $price) {
            $payload['lines'][0]['unit_price'] = $price;
            foreach ([
                '/api/invoicing/ephemeral/btcpay-checkout',
                '/api/invoicing/ephemeral/btcpay-checkout/existing',
                $this->base().'/ephemeral/btcpay-checkout',
            ] as $url) {
                $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('document.type');
            }
        }
        // Quantity deductions can have a positive credit-note aggregate too.
        $payload['lines'] = [
            ['name' => 'Credit', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
            ['name' => 'Deduction', 'quantity' => -1, 'unit_price' => 15, 'tax_rate' => 0],
        ];
        $this->postJson('/api/invoicing/ephemeral/btcpay-checkout', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('document.type');
        $this->assertDatabaseCount('ephemeral_btcpay_checkouts', 0);
        Http::assertNothingSent();
    }

    public static function supportedTaxRates(): array
    {
        return [
            'zero' => [0, '0.00', '85.00'],
            'three decimals' => [8.875, '7.55', '92.55'],
            'four decimals' => [8.8749, '7.54', '92.54'],
            'maximum' => [100, '85.00', '170.00'],
        ];
    }

    #[DataProvider('supportedTaxRates')]
    public function test_fractional_tax_rates_survive_save_issue_and_prepared_exports(float $rate, string $tax, string $total): void
    {
        $payload = $this->payload($rate);
        $id = $this->postJson($this->base(), $payload)->assertCreated()
            ->assertJsonPath('data.lines.0.tax_rate', number_format($rate, 4, '.', ''))->json('data.id');
        $this->postJson($this->base()."/{$id}/issue")->assertOk()
            ->assertJsonPath('data.subtotal', '85.00')->assertJsonPath('data.tax_total', $tax)->assertJsonPath('data.total', $total);
        $document = BusinessDocument::findOrFail($id);
        $this->assertSame(number_format($rate, 4, '.', ''), $document->lines[0]->tax_rate);
        foreach (['ubl', 'isdoc'] as $format) {
            $xml = $this->get($this->base()."/{$id}/{$format}")->assertOk()->getContent();
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml));
            $rates = (new \DOMXPath($dom))->query('//*[local-name()="Percent"]');
            $this->assertGreaterThan(0, $rates->length);
            foreach ($rates as $node) {
                $this->assertEqualsWithDelta($rate, (float) $node->textContent, 0.000001);
            }
        }
        Http::assertNothingSent();
    }

    public function test_signed_rate_validation_rejects_out_of_range_and_unsupported_precision(): void
    {
        foreach ([-0.0001, 100.0001, 8.87501] as $rate) {
            $this->postJson($this->base(), $this->payload($rate))
                ->assertUnprocessable()->assertJsonValidationErrors('lines.0.tax_rate');
        }
        $this->assertDatabaseCount('business_documents', 0);
    }

    public function test_nearby_fractional_percentages_keep_distinct_tax_buckets(): void
    {
        foreach ([CompanyJurisdiction::EuSk, CompanyJurisdiction::Us] as $jurisdiction) {
            $this->company->update(['jurisdiction' => $jurisdiction, 'app_settings' => ['us_sales_tax_provider' => 'manual']]);
            $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($this->company, [
                ['name' => 'First', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 8.875],
                ['name' => 'Second', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 8.8751],
            ]);
            $this->assertCount(2, $canonical->taxBreakdown);
            $this->assertEquals([8.875, 8.8751], array_map(fn ($row) => $row->ratePercent, $canonical->taxBreakdown));
        }
    }

    public function test_distinct_signed_tax_buckets_cannot_mask_a_negative_tax_base(): void
    {
        foreach ([CompanyJurisdiction::EuSk, CompanyJurisdiction::Us] as $jurisdiction) {
            $this->company->update(['jurisdiction' => $jurisdiction, 'app_settings' => ['us_sales_tax_provider' => 'manual']]);
            $payload = $this->payload(8.875);
            $payload['lines'][1]['tax_rate'] = 8.8751;
            $id = $this->postJson($this->base(), $payload)->assertCreated()->json('data.id');
            $this->postJson($this->base()."/{$id}/issue")->assertUnprocessable()->assertJsonValidationErrors('lines');
            $this->assertNull(BusinessDocument::findOrFail($id)->number);
        }
    }

    public function test_manual_us_deduction_offsets_its_own_tax_bucket(): void
    {
        $this->company->update(['jurisdiction' => CompanyJurisdiction::Us, 'app_settings' => ['us_sales_tax_provider' => 'manual']]);
        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($this->company, $this->payload(8.875)['lines']);
        $this->assertCount(1, $canonical->taxBreakdown);
        $this->assertSame('85.00', $canonical->taxBreakdown[0]->taxableAmount);
        $this->assertSame('7.55', $canonical->taxBreakdown[0]->taxAmount);
        $this->assertSame('92.55', $canonical->taxBreakdown[0]->grossAmount);
    }

    public static function zeroTaxRates(): array
    {
        return ['zero rate' => [0], 'tax rounded to zero' => [0.0001]];
    }

    #[DataProvider('zeroTaxRates')]
    public function test_manual_us_zero_tax_deduction_cannot_hide_a_negative_tax_base(float $rate): void
    {
        $this->company->update(['jurisdiction' => CompanyJurisdiction::Us, 'app_settings' => ['us_sales_tax_provider' => 'manual']]);
        foreach ([[-1, 15], [1, -15]] as [$quantity, $price]) {
            $payload = $this->payload();
            $payload['lines'][1] = ['name' => 'Deduction', 'quantity' => $quantity, 'unit_price' => $price, 'tax_rate' => $rate];
            $id = $this->postJson($this->base(), $payload)->assertCreated()->json('data.id');
            $this->postJson($this->base()."/{$id}/issue")->assertUnprocessable()->assertJsonValidationErrors('lines');
            $this->assertNull(BusinessDocument::findOrFail($id)->number);
        }
        $this->assertDatabaseCount('company_document_sequences', 0);
        $this->assertDatabaseCount('company_stock_item_movements', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('zeroTaxRates')]
    public function test_manual_us_zero_tax_deduction_with_a_nonnegative_same_rate_base_remains_issuable(float $rate): void
    {
        $this->company->update(['jurisdiction' => CompanyJurisdiction::Us, 'app_settings' => ['us_sales_tax_provider' => 'manual']]);
        foreach ([[-1, 15], [1, -15]] as [$quantity, $price]) {
            $payload = $this->payload($rate);
            $payload['lines'][1]['quantity'] = $quantity;
            $payload['lines'][1]['unit_price'] = $price;
            $id = $this->postJson($this->base(), $payload)->assertCreated()->json('data.id');
            $this->postJson($this->base()."/{$id}/issue")->assertOk()
                ->assertJsonPath('data.subtotal', '85.00')->assertJsonPath('data.tax_total', '0.00')->assertJsonPath('data.total', '85.00');
            $canonical = app(CanonicalInvoiceBuilder::class)->fromDocument(BusinessDocument::findOrFail($id));
            $this->assertCount(1, $canonical->taxBreakdown);
            $this->assertSame('85.00', $canonical->taxBreakdown[0]->taxableAmount);
            $this->assertEqualsWithDelta($rate, $canonical->taxBreakdown[0]->ratePercent, 0.000001);
        }
        Http::assertNothingSent();
    }

    public function test_unsigned_manual_us_tax_breakdown_preserves_zero_tax_display_behavior(): void
    {
        $this->company->update(['jurisdiction' => CompanyJurisdiction::Us, 'app_settings' => ['us_sales_tax_provider' => 'manual']]);
        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($this->company, [
            ['name' => 'Taxed service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 23],
            ['name' => 'Untaxed service', 'quantity' => 1, 'unit_price' => 25, 'tax_rate' => 0],
        ]);
        $this->assertCount(1, $canonical->taxBreakdown);
        $this->assertSame('100.00', $canonical->taxBreakdown[0]->taxableAmount);
        $this->assertSame('23.00', $canonical->taxBreakdown[0]->taxAmount);
    }

    public static function stockAssociations(): array
    {
        return [['company_stock_item_id'], ['company_warehouse_id']];
    }

    #[DataProvider('stockAssociations')]
    public function test_negative_price_deductions_reject_stock_links_before_create_update_and_issue(string $field): void
    {
        $item = CompanyStockItem::create(['company_id' => $this->company->id, 'name' => 'Synthetic stock', 'track_inventory' => true]);
        $warehouse = CompanyWarehouse::create(['company_id' => $this->company->id, 'name' => 'Synthetic warehouse', 'type' => 'own']);
        $associationId = $field === 'company_stock_item_id' ? $item->id : $warehouse->id;
        $payload = $this->payload();
        $payload['lines'][1]['quantity'] = 1;
        $payload['lines'][1]['unit_price'] = -15;
        $linkedPayload = $payload;
        $linkedPayload['lines'][1][$field] = $associationId;
        $this->postJson($this->base(), $linkedPayload)->assertUnprocessable()->assertJsonValidationErrors('lines.1.unit_price');
        $this->assertDatabaseCount('business_documents', 0);

        $id = $this->postJson($this->base(), $payload)->assertCreated()->json('data.id');
        $this->patchJson($this->base()."/{$id}", $linkedPayload)->assertUnprocessable()->assertJsonValidationErrors('lines.1.unit_price');
        $document = BusinessDocument::findOrFail($id);
        $line = $document->lines()->where('sort_order', 1)->firstOrFail();
        $this->assertNull($line->getAttribute($field));
        // Imported or preexisting rows must meet the same guard when issued.
        $line->update([$field => $associationId]);
        $this->postJson($this->base()."/{$id}/issue")->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->assertNull($document->fresh()->number);
        $this->assertDatabaseCount('company_document_sequences', 0);
        $this->assertDatabaseCount('company_stock_item_movements', 0);
        Http::assertNothingSent();
    }

    public function test_credit_note_normalizes_negative_price_without_changing_invoice_or_financial_totals(): void
    {
        $payload = $this->payload();
        $payload['lines'][1]['quantity'] = 1;
        $payload['lines'][1]['unit_price'] = -15;
        $id = $this->postJson($this->base(), $payload)->assertCreated()->json('data.id');
        $this->postJson($this->base()."/{$id}/issue")->assertOk();
        $source = BusinessDocument::with('lines')->findOrFail($id);
        $original = $source->getAttributes();
        $originalLines = $source->lines->map->getAttributes()->all();
        $noteId = $this->postJson($this->base().'/credit-note-from-invoice', ['invoice_id' => $id])
            ->assertCreated()->assertJsonPath('data.lines.1.quantity', '-1.0000')
            ->assertJsonPath('data.lines.1.unit_price', '15.00')->json('data.id');
        $this->postJson($this->base()."/{$noteId}/issue")->assertOk()
            ->assertJsonPath('data.subtotal', '85.00')->assertJsonPath('data.tax_total', '19.55')->assertJsonPath('data.total', '104.55');
        foreach (['ubl', 'isdoc', 'pdf'] as $format) {
            $this->get($this->base()."/{$noteId}/{$format}")->assertOk();
        }
        $this->assertSame($original, $source->fresh()->getAttributes());
        $this->assertSame($originalLines, $source->fresh('lines')->lines->map->getAttributes()->all());
        $this->assertDatabaseCount('company_stock_item_movements', 0);
        Http::assertNothingSent();
    }

    public function test_compliance_dispatch_waits_for_outermost_commit_and_observes_paid_state(): void
    {
        $document = $this->eligibleDraft();
        DB::beginTransaction();
        try {
            DB::beginTransaction();
            app(BusinessDocumentMarkPaidService::class)->markPaid($document);
            Queue::assertNothingPushed();
            DB::commit();
            Queue::assertNothingPushed();
            $this->assertSame(BusinessDocumentStatus::Paid, $document->fresh()->status);
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        Queue::assertPushed(SubmitBusinessDocumentCompliance::class, fn ($job) => $job->businessDocumentId === $document->id
            && BusinessDocument::findOrFail($job->businessDocumentId)->status === BusinessDocumentStatus::Paid);
        Queue::assertPushed(SubmitBusinessDocumentCompliance::class, 1);
        Http::assertNothingSent();
    }

    public function test_compliance_dispatch_is_discarded_when_mark_paid_outer_transaction_rolls_back(): void
    {
        $document = $this->eligibleDraft();
        DB::beginTransaction();
        try {
            DB::beginTransaction();
            app(BusinessDocumentMarkPaidService::class)->markPaid($document);
            DB::commit();
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack(0);
        }
        Queue::assertNothingPushed();
        $this->assertSame(BusinessDocumentStatus::Draft, $document->fresh()->status);
        $this->assertNull($document->fresh()->number);
        $this->assertDatabaseCount('company_document_sequences', 0);
        Http::assertNothingSent();
    }

    public function test_eligible_compliance_without_transaction_dispatches_immediately(): void
    {
        $document = $this->eligibleDraft();
        $document->update(['status' => BusinessDocumentStatus::Issued]);
        $this->assertSame(0, DB::transactionLevel());
        app(ComplianceSubmissionService::class)->queueIfEligible($document);
        Queue::assertPushed(SubmitBusinessDocumentCompliance::class, 1);
        Http::assertNothingSent();
    }

    public function test_tax_rate_migration_refuses_lossy_rollback_and_preserves_rows_on_safe_roundtrip(): void
    {
        $id = $this->postJson($this->base(), $this->payload(8.875))->assertCreated()->json('data.id');
        $document = BusinessDocument::with('lines')->findOrFail($id);
        $migration = require database_path('migrations/2026_11_14_030000_preserve_fractional_document_line_tax_rates.php');
        try {
            $migration->down();
            $this->fail('Fractional tax percentages must not be narrowed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Cannot narrow document tax rates', $e->getMessage());
        }
        $this->assertSame('8.8750', $document->lines[0]->fresh()->tax_rate);
        $document->lines()->update(['tax_rate' => 23]);
        $before = $document->lines()->get()->map->getAttributes()->all();
        $migration->down();
        $migration->up();
        $this->assertSame($before, $document->lines()->get()->map->getAttributes()->all());
    }

    private function eligibleDraft(): BusinessDocument
    {
        Queue::fake();
        config(['efaktura.enabled' => false]);
        $id = $this->postJson($this->base(), $this->payload())->assertCreated()->json('data.id');
        $this->company->update(['app_settings' => [
            'efaktura_auto_send' => true, 'efaktura_sapi_base_url' => 'https://sapi.test',
        ]]);
        config(['efaktura.enabled' => true]);
        $this->mock(ComplianceSubmissionGateway::class)->shouldReceive('supports')->andReturn(true);

        return BusinessDocument::findOrFail($id);
    }

    private function payload(float $rate = 23): array
    {
        return ['type' => BusinessDocumentType::Invoice->value, 'payment_btc_enabled' => false, 'lines' => [
            ['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => $rate],
            ['name' => 'Deduction', 'quantity' => -1, 'unit_price' => 15, 'tax_rate' => $rate],
        ]];
    }

    private function base(): string
    {
        return "/api/invoicing/companies/{$this->company->id}/documents";
    }
}
