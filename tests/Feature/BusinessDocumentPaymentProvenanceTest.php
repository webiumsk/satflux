<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\User;
use App\Services\Integrations\WooCommerceDocumentService;
use App\Services\Invoicing\BusinessDocumentMarkPaidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessDocumentPaymentProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public static function sources(): array
    {
        return [
            ['manual', 'manual'], ['bulk_manual', 'manual'],
            ['btcpay_sync', 'btcpay'], ['subscription_webhook', 'btcpay'],
            ['bank_auto', 'bank_transfer'], ['bank_manual', 'bank_transfer'],
            ['unknown', null],
        ];
    }

    #[DataProvider('sources')]
    public function test_confirmed_payment_records_source_without_rewriting_paid_history(string $source, ?string $method): void
    {
        Http::preventStrayRequests();
        $company = $this->company();
        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'status' => BusinessDocumentStatus::Issued,
            'total' => 100,
            'payment_btc_enabled' => true,
        ]);
        $service = app(BusinessDocumentMarkPaidService::class);
        $partial = $service->markPaid($document, 50, null, $source);
        $this->assertSame(BusinessDocumentStatus::Issued, $partial->status);
        $this->assertNull($partial->paid_payment_method);
        $paid = $service->markPaid($partial, 100, null, $source);
        $this->assertSame($method, $paid->paid_payment_method);
        $this->assertSame($method, $service->markPaid($paid, null, null, 'manual')->paid_payment_method);
        $unpaid = $service->unmarkPaid($paid);
        $this->assertNull($unpaid->paid_payment_method);
        $this->assertNull($unpaid->paid_at);
    }

    public function test_legacy_paid_document_does_not_infer_bitcoin_from_enabled_checkout(): void
    {
        $document = BusinessDocument::create([
            'company_id' => $this->company()->id,
            'status' => BusinessDocumentStatus::Paid,
            'total' => 100,
            'payment_btc_enabled' => true,
            'btcpay_invoice_id' => 'old-checkout',
        ]);
        $this->assertNull(app(BusinessDocumentMarkPaidService::class)->markPaid($document)->paid_payment_method);
    }

    public function test_blank_woo_identifiers_preserve_existing_contact_identity(): void
    {
        $company = $this->company();
        $store = Store::factory()->create(['user_id' => $company->user_id, 'company_id' => $company->id]);
        StoreIntegration::createForStore($store);
        $integration = StoreIntegration::query()->where('store_id', $store->id)->firstOrFail();
        $contact = CompanyContact::create([
            'company_id' => $company->id, 'name' => 'Synthetic Buyer', 'email' => 'buyer@example.test',
            'registration_number' => '12345678', 'tax_id' => '1234567890', 'vat_id' => 'SK1234567890',
        ]);
        $result = app(WooCommerceDocumentService::class)->upsertContact($integration, [
            'email' => $contact->email, 'ico' => '', 'dic' => '  ', 'ic_dph' => '',
        ]);
        $this->assertSame($contact->id, $result->id);
        $this->assertSame('12345678', $result->registration_number);
        $this->assertSame('1234567890', $result->tax_id);
        $this->assertSame('SK1234567890', $result->vat_id);
    }

    public function test_stale_issued_caller_cannot_overwrite_recorded_bank_payment(): void
    {
        Http::preventStrayRequests();
        $document = $this->issuedDocument();
        $stale = $document->fresh();
        $service = app(BusinessDocumentMarkPaidService::class);
        $paid = $service->markPaid($document, 100, null, 'bank_auto');
        $paidAt = $paid->paid_at->toIso8601String();

        $result = $service->markPaid($stale, 100, null, 'manual');

        $this->assertSame('bank_transfer', $result->paid_payment_method);
        $this->assertSame('bank_transfer', $stale->paid_payment_method);
        $this->assertSame($paidAt, $result->paid_at->toIso8601String());
        $this->assertSame(1, AuditLog::query()->where('target_id', $document->id)->where('action', 'business_document.marked_paid')->count());
        Http::assertNothingSent();
    }

    public function test_stale_issued_caller_cannot_resurrect_cancelled_document(): void
    {
        Http::preventStrayRequests();
        $document = $this->issuedDocument();
        $stale = $document->fresh();
        $document->update(['status' => BusinessDocumentStatus::Cancelled]);

        try {
            app(BusinessDocumentMarkPaidService::class)->markPaid($stale, 100, null, 'btcpay_sync');
            $this->fail('A cancelled persisted document must reject a stale payment caller.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(BusinessDocumentStatus::Cancelled, $document->fresh()->status);
        $this->assertNull($document->fresh()->paid_payment_method);
        $this->assertNull($document->fresh()->amount_paid);
        $this->assertSame(0, AuditLog::query()->where('target_id', $document->id)->where('action', 'business_document.marked_paid')->count());
        Http::assertNothingSent();
    }

    public function test_stale_payment_is_compared_with_current_total(): void
    {
        Http::preventStrayRequests();
        $document = $this->issuedDocument();
        $stale = $document->fresh();
        $document->update(['total' => 200]);

        $result = app(BusinessDocumentMarkPaidService::class)->markPaid($stale, 100, null, 'btcpay_sync');

        $this->assertSame(BusinessDocumentStatus::Issued, $result->status);
        $this->assertSame('200.00', $stale->total);
        $this->assertSame('100.00', $result->amount_paid);
        $this->assertNull($result->paid_at);
        $this->assertNull($result->paid_payment_method);
        Http::assertNothingSent();
    }

    public function test_stale_paid_caller_cannot_unmark_cancelled_document(): void
    {
        $document = $this->issuedDocument();
        $service = app(BusinessDocumentMarkPaidService::class);
        $paid = $service->markPaid($document, 100, null, 'bank_manual');
        $stale = $paid->fresh();
        $paid->update(['status' => BusinessDocumentStatus::Cancelled]);

        $result = $service->unmarkPaid($stale);

        $this->assertSame(BusinessDocumentStatus::Cancelled, $result->status);
        $this->assertSame(BusinessDocumentStatus::Cancelled, $stale->status);
        $this->assertSame('bank_transfer', $result->paid_payment_method);
        $this->assertSame(0, AuditLog::query()->where('target_id', $document->id)->where('action', 'business_document.unmarked_paid')->count());
    }

    public function test_unmark_uses_fresh_paid_state_even_when_caller_still_says_issued(): void
    {
        $document = $this->issuedDocument();
        $stale = $document->fresh();
        $service = app(BusinessDocumentMarkPaidService::class);
        $service->markPaid($document, 100, null, 'bank_manual');

        $result = $service->unmarkPaid($stale);

        $this->assertSame(BusinessDocumentStatus::Issued, $result->status);
        $this->assertSame(BusinessDocumentStatus::Issued, $stale->status);
        $this->assertNull($result->paid_at);
        $this->assertNull($result->amount_paid);
        $this->assertNull($result->paid_payment_method);
        $this->assertSame(1, AuditLog::query()->where('target_id', $document->id)->where('action', 'business_document.unmarked_paid')->count());
    }

    private function issuedDocument(): BusinessDocument
    {
        return BusinessDocument::create([
            'company_id' => $this->company()->id,
            'status' => BusinessDocumentStatus::Issued,
            'total' => 100,
            'payment_btc_enabled' => true,
        ]);
    }

    private function company(): Company
    {
        return Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic Supplier', 'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }
}
