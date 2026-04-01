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
                            {--dry-run : List stores that would get webhooks without making changes}
                            {--repair : For every store: remove all Satflux panel URL webhooks in BTCPay, create one, update DB (fixes duplicates and secret mismatch)}';

    protected $description = 'Create BTCPay webhooks for stores missing one (stores:setup-webhooks). One webhook per Satflux store is normal — same APP_URL, different secrets per BTCPay store. Use --repair to dedupe and re-sync secrets.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $repair = $this->option('repair');

        $serverApiKey = config('services.btcpay.api_key');
        if (! $serverApiKey) {
            $this->error('Server-level BTCPAY_API_KEY is not configured. Cannot create webhooks.');

            return Command::FAILURE;
        }

        $client = app(BtcPayClient::class);
        $client->setApiKey($serverApiKey);

        $webhookService = app(WebhookService::class);
        $panelUrl = $webhookService->getWebhookUrl();

        if ($repair) {
            return $this->handleRepair($webhookService, $dryRun, $panelUrl);
        }

        $stores = Store::whereNull('btcpay_webhook_id')->get();
        $count = $stores->count();

        if ($count === 0) {
            $this->info('All stores already have webhooks. Nothing to do.');
            $this->comment('Tip: use --repair if BTCPay has duplicate webhooks or signatures fail (wrong secret in DB).');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} store(s) would get webhooks:");
            foreach ($stores as $store) {
                $this->line("  - {$store->name} (btcpay_store_id: {$store->btcpay_store_id})");
            }

            return Command::SUCCESS;
        }

        $created = 0;
        $failed = 0;

        /** @var Store $store */
        foreach ($stores as $store) {
            try {
                $createdData = $webhookService->replacePanelWebhookForStore($store->btcpay_store_id, null);
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

    protected function handleRepair(WebhookService $webhookService, bool $dryRun, string $panelUrl): int
    {
        $stores = Store::query()->orderBy('name')->get();

        if ($stores->isEmpty()) {
            $this->info('No stores in database.');

            return Command::SUCCESS;
        }

        $this->warn('Repair: each store will have exactly one BTCPay webhook for: '.$panelUrl);
        if (! $dryRun) {
            $this->warn('Existing panel webhooks for that URL are deleted first, then one new webhook is created.');
        }

        $ok = 0;
        $failed = 0;

        /** @var Store $store */
        foreach ($stores as $store) {
            try {
                $list = $webhookService->listWebhooks($store->btcpay_store_id, null);
                $matchCount = 0;
                foreach ($list as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $url = (string) ($item['url'] ?? $item['Url'] ?? '');
                    if ($webhookService->webhookUrlsMatch($url, $panelUrl)) {
                        $matchCount++;
                    }
                }

                if ($dryRun) {
                    $this->line("  {$store->name}: would remove {$matchCount} panel URL webhook(s), then create 1");

                    continue;
                }

                $createdData = $webhookService->replacePanelWebhookForStore($store->btcpay_store_id, null);
                $store->update([
                    'btcpay_webhook_id' => $createdData['id'],
                    'webhook_secret' => $createdData['secret'],
                ]);
                $this->info("Repaired: {$store->name} (removed {$matchCount} old panel webhook(s))");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("Repair failed for {$store->name} ({$store->btcpay_store_id}): {$e->getMessage()}");
                Log::error('SetupStoreWebhooks: repair failed', [
                    'store_id' => $store->id,
                    'btcpay_store_id' => $store->btcpay_store_id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. Run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Repair done. OK: {$ok}, Failed: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
