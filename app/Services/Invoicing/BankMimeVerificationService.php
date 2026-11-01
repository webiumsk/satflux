<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Store;
use App\Support\Invoicing\PreparedBankMimePayload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Routes original MIME to company extraction or strict Store bank verification. */
class BankMimeVerificationService
{
    // Only fixed rejection codes may cross the subprocess audit boundary.
    private const VERIFIER_REJECTION_REASONS = [
        'invalid_input', 'message_too_large', 'invalid_headers', 'missing_identity',
        'unsigned_message', 'too_many_signatures', 'partial_body_signature',
        'unsupported_mime', 'invalid_mime', 'identity_mismatch', 'unsupported_sender',
        'unsupported_content', 'invalid_signature', 'input_too_large', 'invalid_json',
    ];

    public function __construct(
        private BankInboundAddressService $addresses,
        private BankEmailOriginVerifier $originVerifier,
    ) {}

    public function preparePayload(array $payload): ?PreparedBankMimePayload
    {
        if (! config('bank_inbound.enabled', false)) {
            throw ValidationException::withMessages(['inbound' => ['Bank inbound email is disabled.']]);
        }
        $owner = $this->addresses->resolveOwner((string) ($payload['to'] ?? ''));
        // Policy is selected only from the database recipient owner. Invalid
        // Store content must never fall through to permissive company import.
        $decoded = $owner instanceof Store
            ? $this->verifiedPayload($payload)
            : $this->companyPayload($owner, $payload);

        return $decoded === null ? null : new PreparedBankMimePayload($owner, $decoded);
    }

    /** @return array{to: string, from: string, subject: string, body: string, headers: string}|null */
    private function companyPayload(Company $company, array $payload): ?array
    {
        $rawMime = Crypt::decryptString($payload['_encrypted_bank_mime']);
        if (strlen($rawMime) > 262144) {
            return $this->companyReview($company, 'mime_size');
        }
        try {
            $result = $this->extractCompany($rawMime);
        } catch (\Throwable) {
            throw new RuntimeException('Bank message extraction is temporarily unavailable.');
        }
        $status = $result['status'] ?? null;
        if (! in_array($status, ['extracted', 'invalid'], true)) {
            throw new RuntimeException('Bank message extraction is temporarily unavailable.');
        }
        if ($status === 'invalid') {
            return $this->companyReview($company, 'unsupported_bank_mime');
        }
        foreach (['from', 'subject', 'body', 'headers', 'contentSha256'] as $field) {
            if (! is_string($result[$field] ?? null)) {
                throw new RuntimeException('Bank message extractor returned an invalid result.');
            }
        }
        if (strlen($result['from']) > 255 || strlen($result['subject']) > 500
            || strlen($result['body']) > 65535 || strlen($result['headers']) > 65535
            || ! hash_equals(hash('sha256', $rawMime), $result['contentSha256'])
            || ! mb_check_encoding(implode('', array_intersect_key($result, array_flip(['from', 'subject', 'body', 'headers']))), 'UTF-8')) {
            return $this->companyReview($company, 'invalid_bank_mime_binding');
        }
        $sender = isset($payload['_encrypted_bank_envelope_sender'])
            ? trim(Crypt::decryptString($payload['_encrypted_bank_envelope_sender'])) : '';
        if (strlen($sender) > 255 || ! mb_check_encoding($sender, 'UTF-8') || preg_match('/[\r\n\x00]/', $sender)) {
            return $this->companyReview($company, 'invalid_envelope_sender');
        }

        // Extracted company content is not a bank-origin attestation. Copy
        // only these fields; child/request proof markers are never propagated.
        return [
            'to' => $payload['to'],
            'from' => $sender !== '' ? $sender : $result['from'],
            'subject' => trim($result['subject']),
            'body' => trim($result['body']),
            'headers' => $result['headers'],
        ];
    }

    /** @return array<string, mixed> */
    protected function extractCompany(string $rawMime): array
    {
        // Binary-safe transport lets company charsets be decoded without
        // altering the original bytes or relaxing the strict Store profile.
        return $this->runProcess('company-cli.mjs', ['rawMimeBase64' => base64_encode($rawMime)]);
    }

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
            return $this->review($owner, 'unverified_bank_mime', 'invalid_utf8');
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
            return $this->review($owner, 'unverified_bank_mime', $this->verifierReason($result['reason'] ?? null));
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
        return $this->runProcess('cli.mjs', ['rawMime' => $rawMime, 'recipient' => $recipient]);
    }

    /** @return array<string, mixed> */
    private function runProcess(string $script, array $input): array
    {
        $process = new Process([
            (string) config('bank_inbound.node_binary', 'node'),
            base_path('runtime/bank-mail-verifier/'.$script),
        ], base_path(), null, json_encode($input, JSON_THROW_ON_ERROR), 15);
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

    private function verifierReason(mixed $reason): string
    {
        return is_string($reason) && in_array($reason, self::VERIFIER_REJECTION_REASONS, true)
            ? $reason : 'unknown';
    }

    private function review(Store $store, string $reason, ?string $verifierReason = null): null
    {
        $metadata = ['reason' => $reason];
        if ($verifierReason !== null) {
            $metadata['verifier_reason'] = $verifierReason;
        }
        AuditLog::log('sepa.bmail_review_required', 'store', $store->id, $metadata, $store->user_id);

        return null;
    }

    private function companyReview(Company $company, string $reason): null
    {
        AuditLog::log('bank_inbound.mime_rejected', 'company', $company->id, ['reason' => $reason], $company->user_id);

        return null;
    }
}
