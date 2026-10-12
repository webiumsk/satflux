<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\WebhookService;
use Illuminate\Console\Command;

class ReconcileBtcpayWebhooks extends Command
{
    protected $signature = 'btcpay:reconcile-webhooks {--dry-run : Explicitly request the default read-only behavior} {--server-stores : Inspect every store accessible to the configured server key} {--json : Emit a machine-readable report} {--deliveries : Read recent delivery diagnostics; never send or redeliver callbacks}';

    protected $description = 'Read-only webhook reconciliation; never changes subscriptions or sends callbacks';

    public function handle(WebhookService $webhooks, StoreService $stores): int
    {
        try {
            $canonical = $webhooks->getWebhookUrl();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $local = Store::with('user')->get()->keyBy('btcpay_store_id');
        $ids = $this->option('server-stores')
            ? array_column($stores->listStores(), 'id') : $local->keys()->all();
        $rows = [];
        foreach ($ids as $id) {
            try {
                $owner = $local->get($id)->user ?? null;
                $merchantKey = $owner instanceof User ? $owner->btcpay_api_key : null;
                $entries = $webhooks->listWebhooks($id, $merchantKey);
                $matches = array_filter($entries, fn ($entry) => $webhooks->webhookUrlsMatch((string) ($entry['url'] ?? ''), $canonical));
                foreach ($entries as $entry) {
                    $url = (string) ($entry['url'] ?? '');
                    $owned = ($local->get($id)->btcpay_webhook_id ?? null) === ($entry['id'] ?? null);
                    $enabled = $entry['enabled'] ?? true;
                    $action = 'retain';
                    $reason = 'Unrelated integration; preserve its destination and secret';
                    if ($webhooks->webhookUrlsMatch($url, $canonical)) {
                        $action = count($matches) > 1 ? ($owned ? 'retain' : 'disable') : 'retain';
                        $reason = count($matches) > 1 ? 'Duplicate canonical subscription; review ownership before disabling any entry' : 'Canonical callback';
                        if (! $enabled || (! $owned && count($matches) === 1)) {
                            $action = 'investigate';
                            $reason = 'Canonical subscription is disabled or its signing secret is not known locally';
                        }
                    } elseif ($owned) {
                        $action = 'update';
                        $reason = 'Satflux-owned subscription uses a previous destination; preserve ID and signing secret during a reviewed update';
                    }
                    try {
                        $webhooks->validateDestination($url);
                    } catch (\Throwable) {
                        $reason .= '; destination would require an explicit private/HTTP exception or is invalid';
                        if (! $owned) {
                            $action = 'investigate';
                        }
                    }
                    // Do not print URL credentials, tokens, query parameters, or signing secrets.
                    $parts = parse_url($url);
                    $destination = is_array($parts) && isset($parts['scheme'], $parts['host'])
                        ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '') : '[invalid URL]';
                    if ($this->option('deliveries') && isset($entry['id'])) {
                        $reason .= '; '.$webhooks->deliveryDiagnostic($id, $entry['id'], $merchantKey);
                    }
                    $rows[] = ['store' => $id, 'webhook' => $entry['id'] ?? '', 'enabled' => $enabled, 'destination' => $destination, 'action' => $action, 'reason' => $reason];
                }
                if ($local->has($id) && $matches === []) {
                    $rows[] = ['store' => $id, 'webhook' => '', 'enabled' => false, 'destination' => $canonical, 'action' => 'investigate', 'reason' => 'No canonical callback; review existing subscriptions before registering'];
                }
            } catch (\Throwable) {
                $rows[] = ['store' => $id, 'webhook' => '', 'enabled' => false, 'destination' => '', 'action' => 'investigate', 'reason' => 'Cannot read subscriptions: check store authorization, BTCPay availability, and API diagnostics'];
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode(['readOnly' => true, 'deliveryTested' => false, 'entries' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Store', 'Webhook', 'Enabled', 'Destination', 'Action', 'Reason'], array_map('array_values', $rows));
            $this->comment('Read-only report. Reachability and delivery must be tested from BTCPay; no callbacks were sent. Inspect BTCPay delivery errors for SSRF rejection, DNS, TLS, timeout, and HTTP status failures.');
        }

        return self::SUCCESS;
    }
}
