<?php

namespace Tests\Unit;

use App\Support\Invoicing\BankMimeDiagnosticRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BankMimeDiagnosticSummaryTest extends TestCase
{
    public static function summary(): array
    {
        $headers = [];
        foreach (['from', 'to', 'subject', 'mime-version', 'content-type', 'content-transfer-encoding'] as $header) {
            $headers[$header] = ['required' => true, 'signed' => null];
        }

        return ['version' => 1, 'status' => 'invalid', 'reason' => 'invalid_signature', 'signatures' => [[
            'verdict' => 'pass', 'bank_signer' => true, 'bank_aligned' => false,
            'algorithm_allowed' => null, 'time_valid' => true, 'full_body' => false, 'headers' => $headers,
        ]]];
    }

    public function test_preserves_the_difference_between_true_false_and_not_evaluated(): void
    {
        $summary = self::summary();
        $this->assertSame($summary, (new BankMimeDiagnosticRunner)->sanitizeSummary($summary));
    }

    public function test_unknown_enum_values_become_fixed_unknown_without_copying_strings(): void
    {
        $summary = self::summary();
        $summary['reason'] = "synthetic-private-reason\r\n";
        $summary['signatures'][0]['verdict'] = 'synthetic-private-verdict';
        $safe = (new BankMimeDiagnosticRunner)->sanitizeSummary($summary);
        $this->assertSame('unknown', $safe['reason']);
        $this->assertSame('unknown', $safe['signatures'][0]['verdict']);
        $this->assertStringNotContainsString('synthetic-private', json_encode($safe));
    }

    public static function malformed(): iterable
    {
        foreach ([
            ['version', '1'], ['status', 'INVALID'], ['reason', []], ['signatures', 'private'],
            ['signatures.0.bank_signer', 'true'], ['signatures.0.bank_aligned', 1],
            ['signatures.0.algorithm_allowed', []], ['signatures.0.time_valid', 0],
            ['signatures.0.full_body', 'false'], ['signatures.0.verdict', 1],
            ['signatures.0.headers.from.required', 1], ['signatures.0.headers.to.signed', 'false'],
            ['private', 'private'], ['signatures.0.private', 'private'], ['signatures.0.headers.private', []],
            ['signatures.0.headers.from.private', 'private'],
        ] as [$path, $value]) {
            $summary = self::summary();
            $parts = explode('.', $path);
            $reference = &$summary;
            foreach ($parts as $part) {
                $reference = &$reference[$part];
            }
            $reference = $value;
            unset($reference);
            yield $path => [$summary];
        }
        $summary = self::summary();
        unset($summary['signatures'][0]['headers']['to']);
        yield 'missing-header' => [$summary];
        $summary = self::summary();
        $summary['signatures'] = array_fill(0, 6, $summary['signatures'][0]);
        yield 'six-signatures' => [$summary];
        yield 'not-object' => ['private'];
    }

    #[DataProvider('malformed')]
    public function test_malformed_schema_fails_closed(mixed $summary): void
    {
        $this->assertNull((new BankMimeDiagnosticRunner)->sanitizeSummary($summary));
    }

    public function test_envelope_binds_the_exact_original_hash_and_verification_outcome(): void
    {
        $runner = new BankMimeDiagnosticRunner;
        $envelope = [
            'verification' => ['status' => 'invalid', 'reason' => 'invalid_signature'],
            'diagnostics' => self::summary(), 'contentSha256' => hash('sha256', 'Synthetic MIME'),
        ];
        $this->assertSame($envelope, $runner->validateEnvelope($envelope, 'Synthetic MIME'));
        $this->assertNull($runner->validateEnvelope($envelope, 'Different MIME'));
        $envelope['verification']['status'] = 'verified';
        $this->assertNull($runner->validateEnvelope($envelope, 'Synthetic MIME'));
        $envelope['verification'] = ['status' => 'retryable', 'reason' => 'invalid_signature'];
        $this->assertNull($runner->validateEnvelope($envelope, 'Synthetic MIME'));
    }

    public function test_runner_never_exposes_child_exceptions_or_previous_errors(): void
    {
        $runner = new class extends BankMimeDiagnosticRunner
        {
            protected function execute(string $rawMime, string $recipient): mixed
            {
                throw new RuntimeException('synthetic-private-child-output');
            }
        };
        try {
            $runner->run('synthetic-private-MIME', 'synthetic@private.test');
            $this->fail('Child failure must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Bank message diagnostics are temporarily unavailable.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
