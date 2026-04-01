<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetupStoreWebhooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_with_failure_when_api_key_not_configured(): void
    {
        config(['services.btcpay.api_key' => '']);

        $this->artisan('stores:setup-webhooks')
            ->assertFailed();
    }

    public function test_dry_run_lists_stores_without_webhook(): void
    {
        config(['services.btcpay.api_key' => 'test-key']);
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        Store::factory()->create([
            'btcpay_store_id' => 'store-1',
            'name' => 'My Store',
            'btcpay_webhook_id' => null,
        ]);

        $this->artisan('stores:setup-webhooks', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 store(s) would get webhooks');
    }

    public function test_dry_run_reports_nothing_when_all_stores_have_webhooks(): void
    {
        config(['services.btcpay.api_key' => 'test-key']);
        Store::factory()->create([
            'btcpay_store_id' => 'store-1',
            'btcpay_webhook_id' => 'wh-existing',
            'webhook_secret' => 'existing-secret',
        ]);

        $this->artisan('stores:setup-webhooks', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('All stores already have webhooks');
    }

    public function test_command_creates_webhook_and_updates_store(): void
    {
        config(['services.btcpay.api_key' => 'test-key']);
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        config(['app.url' => 'https://panel.test']);
        Http::fake([
            'https://btcpay.test/api/v1/stores/store-abc/webhooks' => Http::sequence()
                ->push([], 200, ['Content-Type' => 'application/json'])
                ->push(['id' => 'btcpay-wh-1', 'secret' => 'btcpay-secret-1'], 200),
        ]);

        $store = Store::factory()->create([
            'btcpay_store_id' => 'store-abc',
            'name' => 'Test Store',
            'btcpay_webhook_id' => null,
            'webhook_secret' => null,
        ]);

        $this->artisan('stores:setup-webhooks')
            ->assertSuccessful()
            ->expectsOutputToContain('Created webhook for store: Test Store');

        $store->refresh();
        $this->assertSame('btcpay-wh-1', $store->btcpay_webhook_id);
        $this->assertSame('btcpay-secret-1', $store->webhook_secret);
    }

    public function test_command_skips_stores_that_already_have_webhook(): void
    {
        config(['services.btcpay.api_key' => 'test-key']);
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        Http::fake();

        $store = Store::factory()->create([
            'btcpay_store_id' => 'store-xyz',
            'btcpay_webhook_id' => 'already-set',
            'webhook_secret' => 'already-secret',
        ]);

        $this->artisan('stores:setup-webhooks')
            ->assertSuccessful()
            ->expectsOutputToContain('All stores already have webhooks');

        Http::assertNothingSent();
        $store->refresh();
        $this->assertSame('already-set', $store->btcpay_webhook_id);
    }

    public function test_repair_dry_run_counts_panel_webhooks_without_http(): void
    {
        config(['services.btcpay.api_key' => 'test-key']);
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        config(['app.url' => 'https://panel.test']);
        Http::fake([
            'https://btcpay.test/api/v1/stores/store-repair/webhooks' => Http::response([
                ['id' => 'w1', 'url' => 'https://panel.test/api/webhooks/btcpay'],
                ['id' => 'w2', 'url' => 'https://other.example/hook'],
            ]),
        ]);

        Store::factory()->create([
            'btcpay_store_id' => 'store-repair',
            'name' => 'Repair Me',
            'btcpay_webhook_id' => 'existing',
            'webhook_secret' => 'x',
        ]);

        $this->artisan('stores:setup-webhooks', ['--repair' => true, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('would remove 1 panel URL webhook(s)');

        Http::assertSentCount(1);
    }
}
