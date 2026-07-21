<?php

namespace App\Jobs\RegWatch;

use App\Models\RegWatchSource;
use App\Services\RegWatch\SourceMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Check one monitored RegWatch source (dispatched per source by
 * regwatch:monitor). Writes detections into regwatch_changes only.
 */
class CheckRegWatchSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public string $sourceId,
    ) {}

    public function handle(SourceMonitor $monitor): void
    {
        $source = RegWatchSource::query()->find($this->sourceId);
        if (! $source || ! $source->active) {
            return;
        }

        $monitor->check($source);
    }
}
