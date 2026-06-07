<?php

namespace App\Console\Commands;

use App\Services\Compliance\Sync\OfacSdnSync;
use App\Services\Compliance\Sync\OpenSanctionsEuSync;
use App\Services\Compliance\Sync\SanctionsListPersister;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncSanctionsLists extends Command
{
    protected $signature = 'compliance:sync-sanctions-lists';

    protected $description = 'Download and sync OFAC SDN and EU consolidated sanctions lists';

    public function handle(
        OfacSdnSync $ofacSdnSync,
        OpenSanctionsEuSync $euSync,
        SanctionsListPersister $persister,
    ): int {
        $this->info('Syncing OFAC SDN list...');

        try {
            $ofacCount = $persister->replaceSource('ofac_sdn', $ofacSdnSync->fetchEntries());
            $this->info("OFAC SDN: {$ofacCount} entries.");
        } catch (\Throwable $e) {
            $this->error('OFAC SDN sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Syncing EU consolidated list (OpenSanctions export)...');

        try {
            $euCount = $persister->replaceSource('eu_consolidated', $euSync->fetchEntries());
            $this->info("EU consolidated: {$euCount} entries.");
        } catch (\Throwable $e) {
            $this->error('EU sanctions sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        Cache::put('compliance.sanctions_list_version', sha1($ofacCount.'|'.$euCount.'|'.now()->toIso8601String()), now()->addDays(30));

        $this->info('Sanctions lists synced successfully.');

        return self::SUCCESS;
    }
}
