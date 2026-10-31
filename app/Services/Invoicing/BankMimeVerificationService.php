<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Verifies original bank MIME before any automatic SEPA parsing. */
class BankMimeVerificationService
{
    public function __construct(
        private BankInboundAddressService $addresses,
        private BankEmailOriginVerifier $originVerifier,
    ) {}

    /**
     * @return array{to: string, from: string, subject: string, body: string, headers: string, _verified_bank_domain: string}|null
     */
    public function verifiedPayload(array $payload): ?array
    {
        if (! config('bank_inbound.enabled', false)) {
            throw ValidationException::withMessages(['inbound' => ['Bank inbound email is disabled.']]);
        }
        $recipient = (string) ($payload['to'] ?? '');
        $owner = $this->addresses->resolveOwner($recipient);
        if (! $owner instanceof Store) {
            // Existing parsed company-import delivery remains unchanged.
            throw ValidationException::withMessages(['to' => ['Raw bank MIME requires a store address.']]);
        }
        $rawMime = Crypt::decryptString($payload['_encrypted_bank_mime']);
        if (strlen($rawMime) > 262144) {
            return $this->review($owner, 'mime_size');
        }
        if (! mb_check_encoding($rawMime, 'UTF-8')) {
            return $this->review($owner, 'unverified_bank_mime');
        }

        try {
            $result = $this->verify($rawMime, $recipient);
        } catch (\Throwable) {
            // Never include subprocess input, output or exception text in logs.
            throw new RuntimeException('Bank message verification is temporarily unavailable.');
        }
        $status = $result['status'] ?? null;
        if (! in_array($status, ['verified', 'invalid'], true)) {
            throw new RuntimeException('Bank message verification is temporarily unavailable.');
        }
        if ($status !== 'verified') {
            return $this->review($owner, 'unverified_bank_mime');
        }
        foreach (['from', 'subject', 'body', 'bankDomain', 'contentSha256'] as $field) {
            if (! is_string($result[$field] ?? null)) {
                throw new RuntimeException('Bank message verifier returned an invalid result.');
            }
        }
        if (strlen($result['body']) > 65535 || strlen($result['subject']) > 500 || strlen($result['from']) > 255
            || ! hash_equals(hash('sha256', $rawMime), $result['contentSha256'])
            || ! $this->originVerifier->matchesSender($result['bankDomain'], $result['from'])) {
            return $this->review($owner, 'invalid_bank_mime_binding');
        }

        return [
            'to' => $recipient,
            'from' => $result['from'],
            'subject' => $result['subject'],
            'body' => $result['body'],
            'headers' => '',
            '_verified_bank_domain' => $result['bankDomain'],
        ];
    }

    /** @return array<string, mixed> */
    protected function verify(string $rawMime, string $recipient): array
    {
        $process = new Process([
            (string) config('bank_inbound.node_binary', 'node'),
            base_path('runtime/bank-mail-verifier/cli.mjs'),
        ], base_path(), null, json_encode([
            'rawMime' => $rawMime,
            'recipient' => $recipient,
        ], JSON_THROW_ON_ERROR), 15);
        $output = '';
        $outputBytes = 0;
        $process->run(function (string $type, string $buffer) use ($process, &$output, &$outputBytes): void {
            $outputBytes += strlen($buffer);
            if ($outputBytes > 300000) {
                $process->stop(0);
                throw new RuntimeException('Bank message verifier output exceeded its limit.');
            }
            if ($type === Process::OUT) {
                $output .= $buffer;
            }
            $process->clearOutput();
            $process->clearErrorOutput();
        });
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Bank message verifier failed.');
        }
        $result = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($result)) {
            throw new RuntimeException('Bank message verifier returned an invalid result.');
        }

        return $result;
    }

    private function review(Store $store, string $reason): null
    {
        AuditLog::log('sepa.bmail_review_required', 'store', $store->id, ['reason' => $reason], $store->user_id);

        return null;
    }
}
