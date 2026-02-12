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

    public function test_webhook_accepts_signature_with_sha256_prefix(): void
    {
        config(['services.btcpay.webhook_secret' => 'test-secret']);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-123'];
        $body = json_encode($payload);
        $hmac = hash_hmac('sha256', $body, 'test-secret');
        // BTCPay Server sends signature as "sha256=HMAC_HEX"
        $signature = 'sha256=' . $hmac;

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
        $this->assertTrue(WebhookEvent::first()->verified);
    }

    public function test_webhook_verifies_with_per_store_secret_when_store_has_secret(): void
    {
        config(['services.btcpay.webhook_secret' => null]);
        $store = Store::factory()->create([
            'btcpay_store_id' => 'btcpay-store-789',
            'webhook_secret' => 'store-level-secret',
        ]);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-789'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'store-level-secret');

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
        $this->assertTrue($event->verified);
        $this->assertSame($store->id, $event->store_id);
    }

    public function test_webhook_falls_back_to_global_secret_when_store_has_no_secret(): void
    {
        config(['services.btcpay.webhook_secret' => 'global-secret']);
        $store = Store::factory()->create([
            'btcpay_store_id' => 'btcpay-store-global',
            'webhook_secret' => null,
        ]);
        $payload = ['type' => 'InvoiceCreated', 'storeId' => 'btcpay-store-global'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'global-secret');

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
        $this->assertTrue($event->verified);
        $this->assertSame($store->id, $event->store_id);
    }

    public function test_webhook_per_store_secret_takes_precedence_over_global(): void
    {
        config(['services.btcpay.webhook_secret' => 'global-secret']);
        $store = Store::factory()->create([
            'btcpay_store_id' => 'btcpay-store-priority',
            'webhook_secret' => 'store-secret-wins',
        ]);
        $payload = ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-priority'];
        $body = json_encode($payload);
        // Sign with store secret; global would be wrong
        $signature = hash_hmac('sha256', $body, 'store-secret-wins');

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
        $this->assertTrue(WebhookEvent::first()->verified);
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
}
