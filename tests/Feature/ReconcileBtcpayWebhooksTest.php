<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconcileBtcpayWebhooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_preserves_private_integrations_and_only_reads_btcpay(): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'test-key']);
        Store::factory()->create(['btcpay_store_id' => 'store', 'btcpay_webhook_id' => 'owned', 'webhook_secret' => 'must-not-print']);
        Http::fake(['*/webhooks' => Http::response([
            ['id' => 'owned', 'enabled' => true, 'url' => 'http://localhost:8080/api/webhooks/btcpay'],
            ['id' => 'private-integration', 'enabled' => true, 'url' => 'http://user:password@localhost:9000/hook?token=secret'],
        ])]);
        $this->assertSame(0, Artisan::call('btcpay:reconcile-webhooks', ['--dry-run' => true, '--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"action": "update"', $output);
        $this->assertStringContainsString('"action": "investigate"', $output);
        foreach (['must-not-print', 'password', 'token=secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }
}
