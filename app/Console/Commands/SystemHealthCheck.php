<?php

namespace App\Console\Commands;

use App\Services\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';

    protected $description = 'Run system health checks (database, queue, BTCPay, relay, disk, webhooks)';

    public function handle(SystemHealthService $health): int
    {
        $results = $health->runChecks();

        foreach ($results as $check => $result) {
            $line = sprintf('%-9s %s (%d ms) - %s', $check, $result['ok'] ? 'OK' : 'FAILED', $result['duration_ms'], $result['detail']);
            $result['ok'] ? $this->info($line) : $this->error($line);
        }

        if (! $health->allHealthy($results)) {
            Log::critical('System health check failed', [
                'failed' => collect($results)
                    ->filter(fn (array $result) => ! $result['ok'])
                    ->map(fn (array $result) => $result['detail'])
                    ->all(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
