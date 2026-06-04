<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentPaymentWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentPayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pay_page_creates_btcpay_checkout_and_shows_redirect(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'test-key']);
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-1',
        ]);

        $token = Str::random(64);
        BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'total' => 250,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'payment_token' => $token,
            'issue_date' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 'btcpay-inv-fresh',
                'checkoutLink' => 'https://btcpay.example/i/fresh',
            ], 200),
        ]);

        $response = $this->get("/pay/i/{$token}");

        $response->assertOk();
        $response->assertSee('https://btcpay.example/i/fresh', false);

        $doc = BusinessDocument::where('payment_token', $token)->first();
        $this->assertSame('btcpay-inv-fresh', $doc->btcpay_invoice_id);
        $this->assertSame('https://btcpay.example/i/fresh', $doc->btcpay_checkout_link);
    }

    #[Test]
    public function pay_page_shows_already_paid_for_paid_document(): void
    {
        $token = Str::random(64);
        BusinessDocument::create([
            'company_id' => Company::create([
                'user_id' => User::factory()->create()->id,
                'legal_name' => 'Co',
                'jurisdiction' => CompanyJurisdiction::EuSk,
            ])->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Paid,
            'number' => '20260002',
            'total' => 100,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'payment_token' => $token,
            'paid_at' => now(),
            'issue_date' => now(),
        ]);

        Http::fake();

        $this->get("/pay/i/{$token}")
            ->assertOk()
            ->assertSee(__('messages.business_invoice_pay_already_paid'), false);

        Http::assertNothingSent();
    }

    #[Test]
    public function webhook_marks_business_document_paid(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-99',
        ]);

        $token = Str::random(64);
        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260003',
            'total' => 99,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'payment_token' => $token,
            'issue_date' => now(),
        ]);

        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'InvoiceSettled',
            [
                'storeId' => 'btcpay-store-99',
                'invoiceData' => [
                    'id' => 'inv-xyz',
                    'metadata' => [
                        'businessDocumentId' => $document->id,
                    ],
                ],
            ],
            $store
        );

        $this->assertTrue($handled);
        $document->refresh();
        $this->assertSame(BusinessDocumentStatus::Paid, $document->status);
        $this->assertNotNull($document->paid_at);
        $this->assertNull($document->payment_token);
    }

    #[Test]
    public function webhook_handles_invoice_settled_dot_notation(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-dot',
        ]);

        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260004',
            'total' => 50,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'btcpay_invoice_id' => 'btcpay-inv-dot',
            'issue_date' => now(),
        ]);

        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'invoice.settled',
            [
                'storeId' => 'btcpay-store-dot',
                'invoiceId' => 'btcpay-inv-dot',
            ],
            $store
        );

        $this->assertTrue($handled);
        $document->refresh();
        $this->assertSame(BusinessDocumentStatus::Paid, $document->status);
    }

    #[Test]
    public function pay_page_marks_paid_when_btcpay_invoice_already_settled(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'test-key']);
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-paid',
        ]);

        $token = Str::random(64);
        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260010',
            'total' => 75,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'payment_token' => $token,
            'btcpay_invoice_id' => 'btcpay-inv-paid',
            'btcpay_checkout_link' => 'https://btcpay.example/i/old',
            'issue_date' => now(),
        ]);

        Http::fake([
            '*/api/v1/stores/btcpay-store-paid/invoices/btcpay-inv-paid' => Http::response([
                'id' => 'btcpay-inv-paid',
                'status' => 'Settled',
                'metadata' => ['businessDocumentId' => $document->id],
            ], 200),
        ]);

        $this->get("/pay/i/{$token}")
            ->assertOk()
            ->assertSee(__('messages.business_invoice_pay_already_paid'), false);

        Http::assertSentCount(1);

        $document->refresh();
        $this->assertSame(BusinessDocumentStatus::Paid, $document->status);
        $this->assertNull($document->payment_token);
    }

    #[Test]
    public function webhook_resolves_document_via_api_metadata_when_missing_in_payload(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'test-key']);
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'btcpay_store_id' => 'btcpay-store-meta',
        ]);

        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260011',
            'total' => 30,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'issue_date' => now(),
        ]);

        Http::fake([
            '*/api/v1/stores/btcpay-store-meta/invoices/btcpay-inv-meta' => Http::response([
                'id' => 'btcpay-inv-meta',
                'status' => 'Settled',
                'metadata' => ['businessDocumentId' => $document->id],
            ], 200),
        ]);

        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'InvoiceSettled',
            [
                'storeId' => 'btcpay-store-meta',
                'invoiceId' => 'btcpay-inv-meta',
            ],
            $store
        );

        $this->assertTrue($handled);
        $document->refresh();
        $this->assertSame(BusinessDocumentStatus::Paid, $document->status);
        $this->assertNull($document->payment_token);
    }

    #[Test]
    public function webhook_marks_paid_on_invoice_processing_for_lightning(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'btcpay-store-ln',
        ]);
        $document = BusinessDocument::create([
            'company_id' => Company::create([
                'user_id' => $user->id,
                'legal_name' => 'Co',
                'jurisdiction' => CompanyJurisdiction::EuSk,
            ])->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260012',
            'total' => 20,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'btcpay_invoice_id' => 'btcpay-inv-ln',
            'issue_date' => now(),
        ]);

        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'invoice.processing',
            [
                'storeId' => 'btcpay-store-ln',
                'invoiceId' => 'btcpay-inv-ln',
                'metadata' => ['businessDocumentId' => $document->id],
            ],
            $store
        );

        $this->assertTrue($handled);
        $this->assertSame(BusinessDocumentStatus::Paid, $document->fresh()->status);
    }

    #[Test]
    public function webhook_ignores_invoice_received_payment(): void
    {
        $store = Store::factory()->create(['btcpay_store_id' => 'store-1']);
        $document = BusinessDocument::create([
            'company_id' => Company::create([
                'user_id' => $store->user_id,
                'legal_name' => 'Co',
                'jurisdiction' => CompanyJurisdiction::EuSk,
            ])->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260005',
            'total' => 10,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
            'issue_date' => now(),
        ]);

        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'invoice.receivedPayment',
            [
                'storeId' => $store->btcpay_store_id,
                'invoiceData' => [
                    'metadata' => ['businessDocumentId' => $document->id],
                ],
            ],
            $store
        );

        $this->assertFalse($handled);
        $this->assertSame(BusinessDocumentStatus::Issued, $document->fresh()->status);
    }
}
