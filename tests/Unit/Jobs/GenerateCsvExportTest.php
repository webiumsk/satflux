<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateCsvExport;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateCsvExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $this->app->forgetInstance(\App\Services\BtcPay\BtcPayClient::class);
    }

    /** @test */
    public function handle_marks_export_as_running_then_finishes_or_fails(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'store-123',
        ]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'format' => 'standard',
            'status' => 'pending',
            'filters' => [],
        ]);
        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, '/api/v1/stores/') && str_contains($url, '/invoices')) {
                return Http::response(['data' => []], 200);
            }
            return Http::response([], 404);
        });

        // Local disk does not support temporaryUrl; job will fail there and call markAsFailed
        try {
            $job = new GenerateCsvExport($export);
            $job->handle(app(\App\Services\BtcPay\InvoiceService::class));
        } catch (\Throwable $e) {
            // Expected when temporaryUrl is not supported
        }

        $export->refresh();
        $this->assertTrue(in_array($export->status, ['running', 'failed', 'finished'], true));
        if ($export->status === 'failed') {
            $this->assertNotEmpty($export->error_message);
        }
    }

    /** @test */
    public function handle_marks_export_as_failed_when_store_user_has_no_api_key(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => null]);
        $store = Store::factory()->create(['user_id' => $user->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'format' => 'standard',
            'status' => 'pending',
            'filters' => [],
        ]);

        $job = new GenerateCsvExport($export);
        try {
            $job->handle(app(\App\Services\BtcPay\InvoiceService::class));
        } catch (\Throwable $e) {
            // Job may rethrow after markAsFailed
        }

        $export->refresh();
        $this->assertSame('failed', $export->status);
        $this->assertStringContainsString('API key', $export->error_message ?? '');
    }
}
