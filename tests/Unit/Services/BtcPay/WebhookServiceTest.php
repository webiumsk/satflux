<?php

namespace Tests\Unit\Services\BtcPay;

use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\WebhookService;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    public function test_get_webhook_url_returns_app_url_plus_path(): void
    {
        config(['app.url' => 'https://panel.example.com']);
        $service = new WebhookService(app(BtcPayClient::class));

        $url = $service->getWebhookUrl();

        $this->assertSame('https://panel.example.com/api/webhooks/btcpay', $url);
    }

    public function test_get_webhook_url_strips_trailing_slash_from_app_url(): void
    {
        config(['app.url' => 'https://panel.example.com/']);
        $service = new WebhookService(app(BtcPayClient::class));

        $url = $service->getWebhookUrl();

        $this->assertSame('https://panel.example.com/api/webhooks/btcpay', $url);
    }

    public function test_create_webhook_returns_id_and_secret_from_btcpay_response(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->method('post')->willReturn([
            'id' => 'webhook-id-1',
            'secret' => 'webhook-secret-1',
        ]);

        $service = new WebhookService($client);
        $result = $service->createWebhook('STORE123', null);

        $this->assertSame('webhook-id-1', $result['id']);
        $this->assertSame('webhook-secret-1', $result['secret']);
    }

    public function test_create_webhook_accepts_webhook_id_key_from_btcpay(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->method('post')->willReturn([
            'webhookId' => 'alt-id',
            'secret' => 'alt-secret',
        ]);

        $service = new WebhookService($client);
        $result = $service->createWebhook('STORE1', null);

        $this->assertSame('alt-id', $result['id']);
        $this->assertSame('alt-secret', $result['secret']);
    }

    public function test_list_webhooks_returns_array_from_client(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->method('get')->willReturn([
            ['id' => 'wh1'],
            ['id' => 'wh2'],
        ]);

        $service = new WebhookService($client);
        $list = $service->listWebhooks('STORE1', null);

        $this->assertIsArray($list);
        $this->assertCount(2, $list);
        $this->assertSame('wh1', $list[0]['id']);
    }

    public function test_delete_webhook_calls_client_delete(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->expects($this->once())->method('delete');

        $service = new WebhookService($client);
        $service->deleteWebhook('STORE1', 'WH1', null);
    }
}
