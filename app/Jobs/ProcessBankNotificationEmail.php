<?php

namespace App\Jobs;

use App\Services\Invoicing\BankInboundEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBankNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * @param  array{to: string, from: string, subject: string, body: string, headers?: string}  $payload
     */
    public function __construct(
        public array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bank inbound email processing failed permanently', [
            'to' => $this->payload['to'],
            'from' => $this->payload['from'],
            'error' => $exception->getMessage(),
        ]);
    }

    public function handle(BankInboundEmailService $service): void
    {
        try {
            $service->handle($this->payload);
        } catch (\Throwable $e) {
            Log::error('Bank inbound email processing failed', [
                'error' => $e->getMessage(),
                'to' => $this->payload['to'] ?? null,
            ]);
            throw $e;
        }
    }
}
