<?php

namespace App\Console\Commands;

use App\Services\Invoicing\Efaktura\EpostmanStatusSyncService;
use Illuminate\Console\Command;

class SyncEpostmanStatusCommand extends Command
{
    protected $signature = 'efaktura:sync-epostman-status';

    protected $description = 'Refresh bounded due ePostman Enterprise observations for server and local invoices';

    public function handle(EpostmanStatusSyncService $service): int
    {
        $stats = $service->syncDue();
        $this->info(sprintf('Checked %d submission(s); updated %d.', $stats['checked'], $stats['updated']));

        return self::SUCCESS;
    }
}
