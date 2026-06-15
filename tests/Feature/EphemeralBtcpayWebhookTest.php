<?php

namespace Tests\Feature;

use App\Models\EphemeralBtcpayCheckout;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentPaymentWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EphemeralBtcpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_marks_ephemeral_checkout_paid_without_business_document(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'btcpay-store-1']);

        EphemeralBtcpayCheckout::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'btcpay_invoice_id' => 'inv-ephemeral-1',
            'evolu_document_id' => 'evolu-doc-webhook',
            'status' => EphemeralBtcpayCheckout::STATUS_PENDING,
        ]);

        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'InvoiceSettled',
            [
                'storeId' => 'btcpay-store-1',
                'invoiceId' => 'inv-ephemeral-1',
                'metadata' => [
                    'ephemeral' => true,
                    'evoluDocumentId' => 'evolu-doc-webhook',
                ],
            ],
            $store,
        );

        $this->assertTrue($handled);
        $this->assertDatabaseHas('ephemeral_btcpay_checkouts', [
            'btcpay_invoice_id' => 'inv-ephemeral-1',
            'status' => 'paid',
        ]);
        $this->assertDatabaseCount('business_documents', 0);
    }
}
