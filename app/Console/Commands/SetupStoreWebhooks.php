<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\WebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetupStoreWebhooks extends Command
{
    protected $signature = 'stores:setup-webhooks
                            {--dry-run : List stores that would get webhooks without making changes}';

    protected $description = 'Create BTCPay webhooks for stores that do not have one (e.g. created before programmatic webhooks). Safe to re-run.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $serverApiKey = config('services.btcpay.api_key');
        if (!$serverApiKey) {
            $this->error('Server-level BTCPAY_API_KEY is not configured. Cannot create webhooks.');
            return Command::FAILURE;
        }

        // Ensure client uses server key
        $client = app(BtcPayClient::class);
        $client->setApiKey($serverApiKey);

        $stores = Store::whereNull('btcpay_webhook_id')->get();
        $count = $stores->count();

        if ($count === 0) {
            $this->info('All stores already have webhooks. Nothing to do.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} store(s) would get webhooks:");
            foreach ($stores as $store) {
                $this->line("  - {$store->name} (btcpay_store_id: {$store->btcpay_store_id})");
            }
            return Command::SUCCESS;
        }

        $webhookService = app(WebhookService::class);
        $created = 0;
        $failed = 0;

        /** @var Store $store */
        foreach ($stores as $store) {
            try {
                $createdData = $webhookService->createWebhook($store->btcpay_store_id, null);
                $store->update([
                    'btcpay_webhook_id' => $createdData['id'],
                    'webhook_secret' => $createdData['secret'],
                ]);
                $this->info("Created webhook for store: {$store->name} ({$store->btcpay_store_id})");
                $created++;
            } catch (\Throwable $e) {
                $this->error("Failed for store {$store->name} ({$store->btcpay_store_id}): {$e->getMessage()}");
                Log::error('SetupStoreWebhooks: failed to create webhook', [
                    'store_id' => $store->id,
                    'btcpay_store_id' => $store->btcpay_store_id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, Failed: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
