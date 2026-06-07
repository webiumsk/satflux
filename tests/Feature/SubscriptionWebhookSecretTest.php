<?php

namespace Tests\Feature;

use App\Jobs\ProcessBtcPayWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionWebhookSecretTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function subscription_store_webhook_uses_subscription_webhook_secret(): void
    {
        Queue::fake();

        config([
            'services.btcpay.webhook_secret' => 'merchant-secret',
            'services.btcpay.subscription_webhook_secret' => 'subscription-secret',
            'services.btcpay.subscription_store_id' => 'sub-store-abc',
        ]);

        $payload = json_encode([
            'storeId' => 'sub-store-abc',
            'type' => 'InvoiceSettled',
            'invoiceData' => ['id' => 'inv-1'],
        ]);

        $signature = 'sha256='.hash_hmac('sha256', $payload, 'subscription-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/btcpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_BTCPay-Sig' => $signature,
            ],
            $payload,
        );

        $response->assertOk();
        Queue::assertPushed(ProcessBtcPayWebhook::class);
    }

    #[Test]
    public function subscription_store_webhook_rejects_merchant_secret(): void
    {
        Queue::fake();

        config([
            'services.btcpay.webhook_secret' => 'merchant-secret',
            'services.btcpay.subscription_webhook_secret' => 'subscription-secret',
            'services.btcpay.subscription_store_id' => 'sub-store-abc',
        ]);

        $payload = json_encode([
            'storeId' => 'sub-store-abc',
            'type' => 'InvoiceSettled',
        ]);

        $signature = 'sha256='.hash_hmac('sha256', $payload, 'merchant-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/btcpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_BTCPay-Sig' => $signature,
            ],
            $payload,
        );

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }
}
