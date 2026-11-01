<?php

namespace App\Support\Invoicing;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Private subprocess transport and strict output boundary for diagnostic facts. */
class BankMimeDiagnosticRunner
{
    private const REASONS = [
        'invalid_input', 'message_too_large', 'invalid_headers', 'missing_identity',
        'unsigned_message', 'too_many_signatures', 'partial_body_signature',
        'unsupported_mime', 'invalid_mime', 'identity_mismatch', 'unsupported_sender',
        'unsupported_content', 'invalid_signature', 'input_too_large', 'invalid_json',
        'verified', 'verification_unavailable', 'verification_timeout', 'dns_unavailable',
    ];

    private const HEADERS = ['from', 'to', 'subject', 'mime-version', 'content-type', 'content-transfer-encoding'];

    private const FACTS = ['bank_signer', 'bank_aligned', 'algorithm_allowed', 'time_valid', 'full_body'];

    public function run(string $rawMime, string $recipient): array
    {
        try {
            $envelope = $this->execute($rawMime, $recipient);
            $valid = $this->validateEnvelope($envelope, $rawMime);
            if ($valid !== null) {
                return $valid;
            }
        } catch (\Throwable) {
            // Neither child output, exception text nor a previous exception may escape.
        }

        throw new RuntimeException('Bank message diagnostics are temporarily unavailable.');
    }

    public function validateEnvelope(mixed $envelope, string $rawMime): ?array
    {
        if (! is_array($envelope) || ! $this->keys($envelope, ['verification', 'diagnostics', 'contentSha256'])
            || ! is_string($envelope['contentSha256']) || ! hash_equals(hash('sha256', $rawMime), $envelope['contentSha256'])
            || ! is_array($envelope['verification'])) {
            return null;
        }
        $verification = $envelope['verification'];
        $summary = $this->sanitizeSummary($envelope['diagnostics']);
        if ($summary === null || ! in_array($verification['status'] ?? null, ['verified', 'invalid', 'retryable'], true)
            || $summary['status'] !== $verification['status']
            || ! is_string($verification['reason'] ?? null)
            || $summary['reason'] !== $this->reason($verification['reason'])) {
            return null;
        }
        if ($verification['status'] === 'verified') {
            foreach (['from', 'subject', 'body', 'bankDomain', 'contentSha256'] as $field) {
                if (! is_string($verification[$field] ?? null)) {
                    return null;
                }
            }
            if (! hash_equals($envelope['contentSha256'], $verification['contentSha256'])) {
                return null;
            }
        }

        return ['verification' => $verification, 'diagnostics' => $summary, 'contentSha256' => $envelope['contentSha256']];
    }

    /** Rebuild only fixed names and enum values. Malformed facts invalidate the summary. */
    public function sanitizeSummary(mixed $summary): ?array
    {
        if (! is_array($summary) || ! $this->keys($summary, ['version', 'status', 'reason', 'signatures'])
            || $summary['version'] !== 1 || ! in_array($summary['status'], ['verified', 'invalid', 'retryable'], true)
            || ! is_string($summary['reason']) || ! is_array($summary['signatures'])
            || ! array_is_list($summary['signatures']) || count($summary['signatures']) > 5) {
            return null;
        }
        $signatures = [];
        foreach ($summary['signatures'] as $signature) {
            if (! is_array($signature) || ! $this->keys($signature, array_merge(['verdict', 'headers'], self::FACTS))
                || (! is_string($signature['verdict']) && $signature['verdict'] !== null)
                || ! is_array($signature['headers']) || ! $this->keys($signature['headers'], self::HEADERS)) {
                return null;
            }
            $safe = ['verdict' => $signature['verdict'] === null ? null : (
                in_array($signature['verdict'], ['pass', 'fail', 'neutral', 'policy', 'none', 'permerror', 'temperror', 'unknown'], true)
                    ? $signature['verdict'] : 'unknown'
            )];
            foreach (self::FACTS as $fact) {
                if (! is_bool($signature[$fact]) && $signature[$fact] !== null) {
                    return null;
                }
                $safe[$fact] = $signature[$fact];
            }
            $safe['headers'] = [];
            foreach (self::HEADERS as $header) {
                $facts = $signature['headers'][$header];
                if (! is_array($facts) || ! $this->keys($facts, ['required', 'signed'])) {
                    return null;
                }
                foreach (['required', 'signed'] as $fact) {
                    if (! is_bool($facts[$fact]) && $facts[$fact] !== null) {
                        return null;
                    }
                }
                $safe['headers'][$header] = ['required' => $facts['required'], 'signed' => $facts['signed']];
            }
            $signatures[] = $safe;
        }

        return ['version' => 1, 'status' => $summary['status'], 'reason' => $this->reason($summary['reason']), 'signatures' => $signatures];
    }

    protected function execute(string $rawMime, string $recipient): mixed
    {
        if (strlen($rawMime) > 262144 || strlen($recipient) > 255) {
            throw new RuntimeException('Bank message diagnostics input exceeded its limit.');
        }
        $process = new Process([
            (string) config('bank_inbound.node_binary', 'node'),
            base_path('runtime/bank-mail-verifier/diagnostic-cli.mjs'),
        ], base_path(), null, json_encode(['rawMime' => $rawMime, 'recipient' => $recipient], JSON_THROW_ON_ERROR), 15);
        $output = '';
        $bytes = 0;
        $process->run(function (string $type, string $buffer) use ($process, &$output, &$bytes): void {
            $bytes += strlen($buffer);
            if ($bytes > 300000) {
                $process->stop(0);
                throw new RuntimeException('Bank message diagnostics output exceeded its limit.');
            }
            if ($type === Process::OUT) {
                $output .= $buffer;
            }
            $process->clearOutput();
            $process->clearErrorOutput();
        });
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Bank message diagnostics failed.');
        }

        return json_decode($output, true, 16, JSON_THROW_ON_ERROR);
    }

    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    private function reason(string $reason): string
    {
        return in_array($reason, self::REASONS, true) ? $reason : 'unknown';
    }
}
