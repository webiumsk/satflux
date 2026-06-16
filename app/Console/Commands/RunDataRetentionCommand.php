<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class RunDataRetentionCommand extends Command
{
    protected $signature = 'data:retention-run
                            {--dry-run : Report counts without deleting anything}
                            {--force : Run even when DATA_RETENTION_ENABLED is false}';

    protected $description = 'Purge old webhook events, audit logs, export files, stale drafts, cancelled expenses, closed integration inbox rows, and force-delete soft-deleted companies.';

    public function handle(DataRetentionService $retention): int
    {
        $enabled = (bool) config('data_retention.enabled', false);
        $force = (bool) $this->option('force');

        if (! $enabled && ! $force) {
            $this->warn('Data retention is disabled (DATA_RETENTION_ENABLED=false). Use --force for a manual run.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->info('Dry run: no data will be deleted.');
        }

        $stats = $retention->run($dryRun);

        $this->table(
            ['Metric', $dryRun ? 'Would process' : 'Processed'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all(),
        );

        return self::SUCCESS;
    }
}
