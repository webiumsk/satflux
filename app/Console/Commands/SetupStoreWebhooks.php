<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\WebhookService;
use App\Services\StoreWebhookProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SetupStoreWebhooks extends Command
{
    protected $signature = 'stores:setup-webhooks
                            {--dry-run : List stores that would get webhooks without making changes}
                            {--repair : Reuse the canonical subscription; adopt orphans in place; refuse duplicates or disabled subscriptions}
                            {--retry : Scheduled self-heal: only stores created in the last 7 days, with exponential backoff per failing store}';

    protected $description = 'Create BTCPay webhooks for stores missing one (stores:setup-webhooks). One webhook per Satflux store is normal - same BTCPAY_WEBHOOK_BASE_URL, different secrets per BTCPay store. Use btcpay:reconcile-webhooks for a read-only cleanup report.';

    /** Scheduled retries stop after this window - older stores need a manual run or --repair. */
    private const RETRY_WINDOW_DAYS = 7;

    private const RETRY_BASE_DELAY_MINUTES = 5;

    private const RETRY_MAX_DELAY_MINUTES = 360;

    /** Consecutive scheduled failures after which the store is escalated to an error log. */
    private const RETRY_ALERT_AFTER = 6;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $repair = $this->option('repair');
        $retry = (bool) $this->option('retry');

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

        $query = Store::whereNull('btcpay_webhook_id');
        if ($retry) {
            $query->where('created_at', '>=', now()->subDays(self::RETRY_WINDOW_DAYS));
        }
        $stores = $query->get();
        if ($retry) {
            $stores = $stores->reject(fn (Store $store) => $this->retryBackoffActive($store->id))->values();
        }
        $count = $stores->count();

        if ($count === 0) {
            $this->info('All stores already have webhooks. Nothing to do.');
            $this->comment('Tip: run btcpay:reconcile-webhooks --dry-run before repairing subscriptions.');

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
                if (app(StoreWebhookProvisioningService::class)->provisionMissing($store->id)) {
                    $this->info("Created webhook for store: {$store->name} ({$store->btcpay_store_id})");
                    $created++;
                }
                Cache::forget($this->retryCacheKey($store->id));
            } catch (\Throwable $e) {
                $this->error("Failed for store {$store->name} ({$store->btcpay_store_id}): {$e->getMessage()}");
                $context = [
                    'store_id' => $store->id,
                    'btcpay_store_id' => $store->btcpay_store_id,
                    'error' => $e->getMessage(),
                ];
                if ($retry) {
                    $failures = $this->recordRetryFailure($store->id);
                    $context['consecutive_failures'] = $failures;
                    // Transient BTCPay outages heal on the next run; only a persistent
                    // failure is worth an error-level alert, and only once.
                    if ($failures === self::RETRY_ALERT_AFTER) {
                        Log::error('SetupStoreWebhooks: webhook still missing after repeated retries', $context);
                    } else {
                        Log::warning('SetupStoreWebhooks: scheduled webhook retry failed', $context);
                    }
                } else {
                    Log::error('SetupStoreWebhooks: failed to create webhook', $context);
                }
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, Failed: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function retryCacheKey(string $storeId): string
    {
        return 'stores:setup-webhooks:retry:'.$storeId;
    }

    private function retryBackoffActive(string $storeId): bool
    {
        $state = Cache::get($this->retryCacheKey($storeId));

        return is_array($state) && ($state['next_at'] ?? 0) > now()->timestamp;
    }

    /** @return int consecutive scheduled failures for this store */
    private function recordRetryFailure(string $storeId): int
    {
        $state = Cache::get($this->retryCacheKey($storeId));
        $failures = (is_array($state) ? (int) ($state['failures'] ?? 0) : 0) + 1;
        $delayMinutes = min(self::RETRY_BASE_DELAY_MINUTES * (2 ** ($failures - 1)), self::RETRY_MAX_DELAY_MINUTES);

        Cache::put($this->retryCacheKey($storeId), [
            'failures' => $failures,
            'next_at' => now()->addMinutes($delayMinutes)->timestamp,
        ], now()->addDays(self::RETRY_WINDOW_DAYS + 1));

        return $failures;
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
            $this->warn('Matching subscriptions are reused; duplicates and disabled subscriptions require operator review.');
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
                    $this->line("  {$store->name}: found {$matchCount} canonical webhook(s); would reuse one, create if absent, or investigate duplicates");

                    continue;
                }

                $createdData = $webhookService->replacePanelWebhookForStore($store->btcpay_store_id, null, $store->btcpay_webhook_id, $store->webhook_secret);
                $store->update([
                    'btcpay_webhook_id' => $createdData['id'],
                    'webhook_secret' => $createdData['secret'],
                ]);
                $this->info("Repaired: {$store->name} (reused or created the canonical subscription)");
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
