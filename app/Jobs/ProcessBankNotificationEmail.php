<?php

namespace App\Jobs;

use App\Services\Invoicing\BankInboundEmailService;
use App\Services\Invoicing\BankMimeVerificationService;
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
     * @param  array{to: string, from: string, subject: string, body: string, headers?: string, _verified_bank_domain?: ?string, _encrypted_bank_mime?: string}  $payload
     */
    public function __construct(
        public array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function failed(\Throwable $exception): void
    {
        if (isset($this->payload['_encrypted_bank_mime'])) {
            Log::error('Bank MIME processing failed permanently', ['exception_type' => get_class($exception)]);

            return;
        }
        Log::error('Bank inbound email processing failed permanently', [
            'to' => $this->payload['to'],
            'from' => $this->payload['from'],
            'error' => $exception->getMessage(),
        ]);
    }

    public function handle(BankInboundEmailService $service, BankMimeVerificationService $mimeVerifier): void
    {
        try {
            // Keep ciphertext on the job: retries and failed_jobs must never
            // serialize the original bank message or a decrypted payload.
            $payload = isset($this->payload['_encrypted_bank_mime'])
                ? $mimeVerifier->verifiedPayload($this->payload)
                : $this->payload;
            if ($payload !== null) {
                $service->handle($payload);
            }
        } catch (\Throwable $e) {
            Log::error('Bank inbound email processing failed', isset($this->payload['_encrypted_bank_mime']) ? [
                'exception_type' => get_class($e),
            ] : [
                'error' => $e->getMessage(),
                'to' => $this->payload['to'] ?? null,
            ]);
            if (isset($this->payload['_encrypted_bank_mime'])) {
                // failed_jobs and exception reporters must not retain a
                // downstream exception containing decrypted bank content.
                throw new \RuntimeException('Bank MIME processing is temporarily unavailable.');
            }
            throw $e;
        }
    }
}
