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

        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260003',
            'total' => 99,
            'currency' => 'EUR',
            'payment_btc_enabled' => true,
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
    }
}
