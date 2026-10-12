<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\WalletConnectionService;
use Illuminate\Console\Command;

class ReconcileWalletUpdates extends Command
{
    protected $signature = 'btcpay:reconcile-wallet-updates {--dry-run : List unresolved attempts without contacting BTCPay or changing local state} {--store= : Limit to a local Satflux store ID} {--confirm-rejected : Operator confirms no wallet write remains in flight; release the hold only if BTCPay still matches the previous wallet}';

    protected $description = 'Recover local wallet state using read-only merchant API configuration checks';

    public function handle(WalletConnectionService $wallets): int
    {
        if ($this->option('confirm-rejected') && ! $this->option('store')) {
            $this->error('--confirm-rejected requires one explicit local --store ID.');

            return self::FAILURE;
        }
        $query = WalletConfigurationAttempt::unresolved();
        if ($id = $this->option('store')) {
            $query->where('store_id', $id);
        }
        $failed = false;
        foreach ($query->get() as $attempt) {
            if ($this->option('dry-run')) {
                $this->line("{$attempt->store_id}: {$attempt->connection_type}, {$attempt->status}");

                continue;
            }
            $store = Store::find($attempt->store_id);
            try {
                if ($store && $store->user instanceof User && $wallets->reconcileWalletUpdate($store, $store->user, (bool) $this->option('confirm-rejected'))) {
                    $this->info("{$attempt->store_id}: reconciled");

                    continue;
                }
            } catch (\Throwable) {
                // Do not echo credential-bearing upstream exceptions.
            }
            $failed = true;
            $this->warn("{$attempt->store_id}: unresolved; invoice creation and further wallet changes remain paused. Check BTCPay connectivity and compare the authorized store configuration.");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
