<?php

namespace App\Console\Commands;

use App\Services\Invoicing\Efaktura\EfakturaInboundInboxService;
use Illuminate\Console\Command;

class PurgeEfakturaInboundInboxCommand extends Command
{
    protected $signature = 'efaktura:purge-inbound-inbox {--days= : Override the retention window in days}';

    protected $description = 'Compatibility check; unresolved e-faktura originals are retained until safely imported';

    public function handle(EfakturaInboundInboxService $inboxService): int
    {
        $days = $this->option('days');
        $purged = $inboxService->purgeStale(is_numeric($days) ? (int) $days : null);

        $this->info("Purged {$purged} stale inbox item(s).");

        return self::SUCCESS;
    }
}
