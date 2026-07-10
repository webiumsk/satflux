<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\StoreSettlement;
use App\Services\Boltz\SettlementLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles the local settlement ledger against BTCPay.
 *
 * - re-syncs recent settled invoices (idempotent upserts fill any missing rows)
 * - flags ledger rows stuck in a non-settled payment status for too long
 * - flags Boltz rows whose settlement asset does not match the store configuration
 *
 * Flags are additive JSON markers on the row (never destructive); re-running the
 * command clears flags that no longer apply.
 */
class ReconcileStoreSettlements extends Command
{
    protected $signature = 'boltz:reconcile-settlements
        {--store= : Only this store (local UUID)}
        {--limit=50 : Recent settled invoices to re-sync per store}
        {--stuck-hours=24 : Hours after which a non-settled payment row is flagged stuck}';

    protected $description = 'Re-sync and reconcile the store settlement ledger against BTCPay';

    public function handle(SettlementLedgerService $ledger): int
    {
        $stores = Store::query()
            ->when($this->option('store'), fn ($q, $id) => $q->whereKey($id))
            ->whereNotNull('btcpay_store_id')
            ->get();

        $stuckHours = max(1, (int) $this->option('stuck-hours'));
        $limit = max(1, (int) $this->option('limit'));

        foreach ($stores as $store) {
            $synced = ['invoices' => 0, 'rows' => 0];
            try {
                $synced = $ledger->syncRecent($store, $limit);
            } catch (\Throwable $e) {
                Log::warning('Settlement reconciliation: sync failed', [
                    'store_id' => $store->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $flagged = $this->reconcileFlags($store, $stuckHours);

            $this->info(sprintf(
                'store %s: synced %d invoice(s) / %d row(s), flagged %d',
                $store->id,
                $synced['invoices'],
                $synced['rows'],
                $flagged,
            ));
        }

        return self::SUCCESS;
    }

    protected function reconcileFlags(Store $store, int $stuckHours): int
    {
        $flagged = 0;

        $rows = StoreSettlement::query()
            ->where('store_id', $store->id)
            ->get();

        foreach ($rows as $row) {
            $flags = [];

            $isSettled = strcasecmp((string) $row->payment_status, 'Settled') === 0;
            if (! $isSettled && $row->paid_at && $row->paid_at->lt(now()->subHours($stuckHours))) {
                $flags['stuck'] = [
                    'payment_status' => $row->payment_status,
                    'flagged_at' => now()->toIso8601String(),
                ];
            }

            if (
                $row->category === 'lightning_boltz'
                && $row->settlement_asset !== null
                && $row->settlement_asset !== 'LBTC'
            ) {
                $flags['settlement_asset_mismatch'] = [
                    'expected' => 'LBTC',
                    'actual' => $row->settlement_asset,
                ];
            }

            $current = $row->flags ?? [];
            $reconcilable = ['stuck', 'settlement_asset_mismatch'];
            $next = array_merge(
                array_diff_key($current, array_flip($reconcilable)),
                $flags,
            );

            if ($next !== $current) {
                $row->flags = $next === [] ? null : $next;
                $row->save();
            }
            if ($flags !== []) {
                $flagged++;
            }
        }

        return $flagged;
    }
}
