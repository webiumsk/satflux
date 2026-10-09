<?php

namespace Tests\Unit\Services\BtcPay;

use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\WebhookService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    public function test_get_webhook_url_returns_explicit_base_url_plus_path(): void
    {
        config(['services.btcpay.webhook_base_url' => 'https://panel.example.com']);
        $service = new WebhookService(app(BtcPayClient::class));

        $url = $service->getWebhookUrl();

        $this->assertSame('https://panel.example.com/api/webhooks/btcpay', $url);
    }

    public function test_get_webhook_url_strips_trailing_slash_from_base_url(): void
    {
        config(['services.btcpay.webhook_base_url' => 'https://panel.example.com/']);
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

    public function test_normalize_webhooks_list_wraps_webhooks_key(): void
    {
        $service = new WebhookService(app(BtcPayClient::class));
        $out = $service->normalizeWebhooksListResponse([
            'webhooks' => [['id' => 'a'], ['id' => 'b']],
        ]);
        $this->assertCount(2, $out);
        $this->assertSame('a', $out[0]['id']);
    }

    public function test_webhook_urls_match_ignores_trailing_slash_and_case(): void
    {
        $service = new WebhookService(app(BtcPayClient::class));
        $this->assertTrue($service->webhookUrlsMatch(
            'https://Panel.test/api/webhooks/btcpay',
            'https://panel.test/api/webhooks/btcpay/'
        ));
    }

    public function test_existing_subscription_and_secret_are_reused_without_writes(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->method('get')->willReturn([['id' => 'known', 'url' => 'https://panel.test/api/webhooks/btcpay', 'enabled' => true]]);
        foreach (['delete', 'post', 'put'] as $method) {
            $client->expects($this->never())->method($method);
        }
        $service = new WebhookService($client);
        $this->assertSame(['id' => 'known', 'secret' => 'local-secret'], $service->replacePanelWebhookForStore('store', null, 'known', 'local-secret'));
    }

    public function test_duplicate_subscriptions_require_operator_review(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->method('get')->willReturn([
            ['id' => 'a', 'url' => 'https://panel.test/api/webhooks/btcpay'],
            ['id' => 'b', 'url' => 'https://panel.test/api/webhooks/btcpay'],
        ]);
        foreach (['delete', 'post', 'put'] as $method) {
            $client->expects($this->never())->method($method);
        }
        $this->expectException(\RuntimeException::class);
        (new WebhookService($client))->replacePanelWebhookForStore('store');
    }

    public function test_private_callback_needs_exact_operator_origin(): void
    {
        $service = new WebhookService(app(BtcPayClient::class));
        config(['services.btcpay.webhook_private_origins' => ['http://localhost:8080']]);
        $service->validateDestination('http://localhost:8080/api/webhooks/btcpay');
        $this->expectException(\RuntimeException::class);
        $service->validateDestination('http://localhost:8081/api/webhooks/btcpay');
    }

    public function test_missing_explicit_base_url_never_falls_back_to_app_url(): void
    {
        config(['services.btcpay.webhook_base_url' => null, 'app.url' => 'https://panel.test']);
        $this->expectException(\RuntimeException::class);
        (new WebhookService(app(BtcPayClient::class)))->getWebhookUrl();
    }

    public function test_callback_path_is_case_sensitive(): void
    {
        $this->assertFalse((new WebhookService(app(BtcPayClient::class)))->webhookUrlsMatch(
            'https://panel.test/API/webhooks/btcpay', 'https://panel.test/api/webhooks/btcpay'));
    }

    public function test_webhook_creation_does_not_retry_an_uncertain_post(): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Response lost');
        });
        try {
            (new WebhookService(new BtcPayClient('test-key')))->createWebhook('store');
            $this->fail('The uncertain response should be reported.');
        } catch (BtcPayException) {
            $this->assertSame(1, $attempts, 'Retrying this POST could create duplicate subscriptions.');
        }
    }

    public function test_known_previous_destination_is_not_duplicated_or_rewritten(): void
    {
        $client = $this->createMock(BtcPayClient::class);
        $client->method('get')->willReturn([['id' => 'known', 'url' => 'http://localhost:8080/previous']]);
        foreach (['post', 'put', 'delete'] as $method) {
            $client->expects($this->never())->method($method);
        }
        $this->expectException(\RuntimeException::class);
        (new WebhookService($client))->replacePanelWebhookForStore('store', null, 'known', 'secret');
    }
}
