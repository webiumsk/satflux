<?php

namespace App\Console\Commands;

use App\Services\Invoicing\Efaktura\ComplianceStatusSyncService;
use Illuminate\Console\Command;

class SyncEfakturaComplianceStatusCommand extends Command
{
    protected $signature = 'efaktura:sync-compliance-status';

    protected $description = 'Refresh stale SAPI-SK outbound compliance rows when send detail path is configured';

    public function handle(ComplianceStatusSyncService $statusSyncService): int
    {
        if (! config('efaktura.enabled')) {
            $this->info('E-faktura is disabled globally.');

            return self::SUCCESS;
        }

        $detailPath = (string) config('efaktura.providers.sapi_sk.send_detail_path', '');
        if ($detailPath === '') {
            $this->info('EFAKTURA_SAPI_SEND_DETAIL_PATH is not set; nothing to sync.');

            return self::SUCCESS;
        }

        $stats = $statusSyncService->syncStaleSubmissions();
        $this->info(sprintf(
            'Checked %d compliance row(s); updated %d.',
            $stats['checked'],
            $stats['updated'],
        ));

        return self::SUCCESS;
    }
}
