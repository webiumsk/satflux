<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorFailedJobs extends Command
{
    protected $signature = 'jobs:monitor-failed
                            {--hours=1 : Alert on jobs failed within this many hours}
                            {--threshold=5 : Minimum failures to trigger an alert}';

    protected $description = 'Check for recent failed jobs and log an alert if threshold is exceeded';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = (int) $this->option('threshold');

        if ($hours <= 0) {
            $this->error("Invalid --hours value ({$hours}): must be a positive integer.");

            return self::FAILURE;
        }
        if ($threshold <= 0) {
            $this->error("Invalid --threshold value ({$threshold}): must be a positive integer.");

            return self::FAILURE;
        }

        $count = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHours($hours))
            ->count();

        if ($count === 0) {
            return self::SUCCESS;
        }

        $recent = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHours($hours))
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['uuid', 'queue', 'failed_at', 'exception'])
            ->map(fn ($job) => [
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'exception_class' => preg_match('/^[\w\\\\]+/', $job->exception ?? '', $m) ? $m[0] : 'Unknown',
            ])
            ->all();

        if ($count >= $threshold) {
            Log::error("Failed jobs alert: {$count} jobs failed in the last {$hours} hour(s)", [
                'count' => $count,
                'threshold' => $threshold,
                'recent' => $recent,
            ]);
            $this->error("ALERT: {$count} failed jobs in the last {$hours} hour(s) (threshold: {$threshold})");
        } else {
            Log::warning("Failed jobs notice: {$count} jobs failed in the last {$hours} hour(s)", [
                'count' => $count,
                'recent' => $recent,
            ]);
            $this->warn("{$count} failed job(s) in the last {$hours} hour(s)");
        }

        return $count >= $threshold ? self::FAILURE : self::SUCCESS;
    }
}
