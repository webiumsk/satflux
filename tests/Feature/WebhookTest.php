<?php

namespace Tests\Feature;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\Store;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_webhook_returns_401_when_secret_configured_and_signature_missing(): void
    {
        config(['services.btcpay.webhook_secret' => 'test-secret']);

        $response = $this->postJson('/api/webhooks/btcpay', [
            'type' => 'InvoiceSettled',
            'storeId' => 'btcpay-store-123',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Missing signature']);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_webhook_returns_401_when_signature_invalid(): void
    {
        config(['services.btcpay.webhook_secret' => 'test-secret']);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-123'];

        $response = $this->postJson('/api/webhooks/btcpay', $payload, [
            'BTCPay-Sig' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid signature']);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_webhook_accepts_valid_signature_and_stores_event(): void
    {
        config(['services.btcpay.webhook_secret' => 'test-secret']);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-123'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/btcpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_BTCpay-Sig' => $signature,
            ],
            $body
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'received']);

        $this->assertDatabaseCount('webhook_events', 1);
        $event = WebhookEvent::first();
        $this->assertSame('InvoiceSettled', $event->event_type);
        $this->assertSame($payload, $event->payload);
        $this->assertTrue($event->verified);
        $this->assertNull($event->store_id);

        Queue::assertPushed(ProcessBtcPayWebhook::class);
    }

    public function test_webhook_links_event_to_store_when_store_exists(): void
    {
        config(['services.btcpay.webhook_secret' => 'test-secret']);
        $store = Store::factory()->create(['btcpay_store_id' => 'btcpay-store-456']);
        $payload = ['type' => 'InvoiceCreated', 'storeId' => 'btcpay-store-456'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/btcpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_BTCpay-Sig' => $signature,
            ],
            $body
        );

        $response->assertStatus(200);
        $event = WebhookEvent::first();
        $this->assertSame($store->id, $event->store_id);
    }

    public function test_webhook_accepts_without_secret_in_dev_mode(): void
    {
        config(['services.btcpay.webhook_secret' => null]);
        $payload = ['type' => 'StoreUpdated', 'storeId' => 'any'];

        $response = $this->postJson('/api/webhooks/btcpay', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'received']);
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertFalse(WebhookEvent::first()->verified);
        Queue::assertPushed(ProcessBtcPayWebhook::class);
    }

    public function test_webhook_rejects_without_secret_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['services.btcpay.webhook_secret' => null]);
        $payload = ['type' => 'StoreUpdated', 'storeId' => 'any'];

        $response = $this->postJson('/api/webhooks/btcpay', $payload);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Webhook secret not configured']);
        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNotPushed(ProcessBtcPayWebhook::class);
    }

    public function test_webhook_uses_store_webhook_secret_when_store_has_one(): void
    {
        $store = Store::factory()->create([
            'btcpay_store_id' => 'btcpay-store-per-store',
            'webhook_secret' => 'store-secret',
        ]);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-per-store'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'store-secret');

        $response = $this->postJson('/api/webhooks/btcpay', $payload, [
            'BTCPay-Sig' => 'sha256=' . $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'received']);
        $event = WebhookEvent::first();
        $this->assertTrue($event->verified);
        $this->assertSame($store->id, $event->store_id);
        Queue::assertPushed(ProcessBtcPayWebhook::class);
    }

    public function test_webhook_returns_401_when_store_has_secret_but_request_signed_with_config_secret(): void
    {
        Store::factory()->create([
            'btcpay_store_id' => 'btcpay-store-own-secret',
            'webhook_secret' => 'store-secret',
        ]);
        config(['services.btcpay.webhook_secret' => 'config-secret']);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-own-secret'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'config-secret');

        $response = $this->postJson('/api/webhooks/btcpay', $payload, [
            'BTCPay-Sig' => 'sha256=' . $signature,
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid signature']);
        $this->assertDatabaseCount('webhook_events', 0);
    }
}
