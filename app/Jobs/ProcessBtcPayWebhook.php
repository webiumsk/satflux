<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBtcPayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public WebhookEvent $webhookEvent
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     * 
     * Skeleton implementation - no business logic triggered yet.
     */
    public function handle(): void
    {
        // TODO: Process webhook event
        // This is a skeleton - no business logic implemented
        
        $this->webhookEvent->markAsProcessed();
    }
}

