<?php

namespace App\Console\Commands;

use App\Models\SystemHealthSnapshot;
use App\Services\SystemHealthAlerter;
use App\Services\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';

    protected $description = 'Run system health checks, persist a snapshot and alert on failures';

    public function handle(SystemHealthService $health, SystemHealthAlerter $alerter): int
    {
        $results = $health->runChecks();
        $healthy = $health->allHealthy($results);

        foreach ($results as $check => $result) {
            $line = sprintf('%-9s %s (%d ms) - %s', $check, $result['ok'] ? 'OK' : 'FAILED', $result['duration_ms'], $result['detail']);
            $result['ok'] ? $this->info($line) : $this->error($line);
        }

        SystemHealthSnapshot::create([
            'healthy' => $healthy,
            'checks' => $results,
            'created_at' => now(),
        ]);
        $this->pruneSnapshots();

        $alerter->handle($results);

        if (! $healthy) {
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

    protected function pruneSnapshots(): void
    {
        $days = max(1, (int) config('monitoring.snapshot_retention_days', 7));
        SystemHealthSnapshot::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
