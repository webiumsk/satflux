<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMonthlyExports;
use Illuminate\Console\Command;

class RunMonthlyReports extends Command
{
    protected $signature = 'reports:run-monthly
                            {--month= : Month in Y-m format. Defaults to previous month}
                            {--store-id= : Optional local store UUID. Only this store will be processed}
                            {--sync : Run immediately in current process (safe for one-off runs)}';

    protected $description = 'Run automatic monthly reports, optionally for one specific store.';

    public function handle(): int
    {
        $month = $this->option('month') ?: null;
        $storeId = $this->option('store-id') ?: null;
        $sync = (bool) $this->option('sync');

        if ($month !== null && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Invalid --month format. Use Y-m, e.g. 2026-03.');

            return self::FAILURE;
        }

        $job = new ProcessMonthlyExports($month, $storeId);

        if ($sync) {
            app()->call([$job, 'handle']);
            $this->info('Monthly reports processed synchronously.');
        } else {
            dispatch($job);
            $this->info('Monthly reports job queued.');
        }

        $this->line('month=' . ($month ?? 'previous-month'));
        $this->line('store-id=' . ($storeId ?? 'all-stores'));

        return self::SUCCESS;
    }
}

