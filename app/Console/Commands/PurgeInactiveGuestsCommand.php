<?php

namespace App\Console\Commands;

use App\Services\GuestPurgeService;
use Illuminate\Console\Command;

class PurgeInactiveGuestsCommand extends Command
{
    protected $signature = 'guests:purge-inactive
                            {--dry-run : List guests that would be purged without deleting anything}
                            {--days= : Override idle window in days (default from config)}
                            {--force : Run even when GUEST_PURGE_ENABLED is false}';

    protected $description = 'Purge inactive guest accounts (no login and no invoices in the idle window). Requires GUEST_PURGE_ENABLED=true unless --force.';

    public function handle(GuestPurgeService $guestPurgeService): int
    {
        $enabled = (bool) config('guest.purge_enabled', false);
        $force = (bool) $this->option('force');

        if (! $enabled && ! $force) {
            $this->warn('Guest purge is disabled (GUEST_PURGE_ENABLED=false). Use --force for a manual run.');

            return self::FAILURE;
        }

        $daysOption = $this->option('days');
        $daysOverride = $daysOption !== null && $daysOption !== ''
            ? max(1, (int) $daysOption)
            : null;

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run: no accounts will be deleted.');
        }

        $stats = $guestPurgeService->run($dryRun, $daysOverride);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Guests considered', $stats['considered']],
                ['Skipped (still active)', $stats['skipped_active']],
                ['Skipped (API / check error)', $stats['skipped_error']],
                ['Skipped (BTCPay decommission failed)', $stats['skipped_btcpay_error']],
                [$dryRun ? 'Would purge' : 'Purged', $dryRun ? $stats['dry_run_would_purge'] : $stats['purged']],
            ],
        );

        return self::SUCCESS;
    }
}
