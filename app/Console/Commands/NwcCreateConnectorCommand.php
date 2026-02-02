<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\NwcConnectorService;
use Illuminate\Console\Command;

class NwcCreateConnectorCommand extends Command
{
    protected $signature = 'nwc:create-connector {store : Store UUID}';

    protected $description = 'Create an NWC connector for a store (for testing without UI)';

    public function handle(NwcConnectorService $nwcService): int
    {
        $storeId = $this->argument('store');
        $store = Store::find($storeId);

        if (! $store) {
            $this->error("Store not found: {$storeId}");
            return Command::FAILURE;
        }

        try {
            $result = $nwcService->createConnector($store);
        } catch (\Throwable $e) {
            $this->error('Failed to create connector: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $store->update(['nwc_connector_id' => $result['connector_id']]);

        $this->info('NWC connector created.');
        $this->line('Connector ID: ' . $result['connector_id']);
        $this->newLine();
        $this->line('BTCPay connection string (paste in Lightning → Add connection):');
        $this->line($result['connection_string']);
        $this->newLine();
        $this->line('NWC URI only:');
        $this->line($result['nwc_uri'] ?? '');

        return Command::SUCCESS;
    }
}
