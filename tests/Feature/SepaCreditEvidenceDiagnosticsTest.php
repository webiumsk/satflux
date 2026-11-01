<?php

namespace Tests\Feature;

use App\Enums\BankTransactionDirection;
use App\Models\AuditLog;
use App\Models\Store;
use App\Services\BtcPay\SepaService;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Services\SepaEmailConfirmationService;
use App\Support\Invoicing\ParsedBankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;
use Tests\Unit\TatraBankSepaEvidenceDiagnosticsTest;

class SepaCreditEvidenceDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        Http::preventStrayRequests();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            '_verified_bank_domain' => 'tatrabanka.sk', 'from' => 'notify@tatrabanka.sk',
            'body' => TatraBankSepaEvidenceDiagnosticsTest::body(),
        ], $overrides);
    }

    private function row(array $overrides = []): ParsedBankTransaction
    {
        return new ParsedBankTransaction(...array_replace([
            'bookedAt' => now(), 'amount' => 12.5, 'currency' => 'EUR', 'direction' => BankTransactionDirection::Credit,
            'recipientIban' => 'SK4811000000002944116480', 'isExplicitCredit' => true, 'hasExplicitCurrency' => true,
            'e2eReference' => 'QR-ab29e346f1d841c8a95a63d857490818', 'isReferenceAmbiguous' => false,
            'sepaCreditIdentity' => '9860306e4ad7d2e1d68a7dd255bc569ecd1b885e502016a62ee3299e175f4137',
        ], $overrides));
    }

    private function expectedBits(array $overrides = []): array
    {
        return array_replace([
            'explicit_credit' => true, 'explicit_currency' => true, 'unambiguous_reference' => true,
            'valid_receiving_account' => true, 'trusted_reference' => true, 'strict_profile_identity' => true,
        ], $overrides);
    }

    private function noProvider(): SepaService
    {
        $provider = $this->mock(SepaService::class);
        $provider->shouldNotReceive('getSettings');
        $provider->shouldNotReceive('reportPayment');

        return $provider;
    }

    private function evidence(): array
    {
        return AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata['credit_evidence'];
    }

    public static function missingBits(): iterable
    {
        yield 'explicit-credit' => [['isExplicitCredit' => false], ['explicit_credit' => false]];
        yield 'explicit-currency' => [['hasExplicitCurrency' => false], ['explicit_currency' => false]];
        yield 'unambiguous-reference' => [['isReferenceAmbiguous' => true], ['unambiguous_reference' => false, 'trusted_reference' => false]];
        yield 'receiving-account' => [['recipientIban' => 'SK4811000000002944116481'], ['valid_receiving_account' => false]];
        yield 'trusted-reference' => [['e2eReference' => null], ['trusted_reference' => false]];
    }

    #[DataProvider('missingBits')]
    public function test_each_existing_eligibility_predicate_is_explained_without_provider_calls(array $rowOverrides, array $bits): void
    {
        $this->noProvider();
        $result = app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload(), [$this->row($rowOverrides)]);
        $this->assertSame(['store_id' => $this->store->id, 'reported' => 0, 'outcome' => 'review'], $result);
        $this->assertSame([
            'reason' => 'incomplete_credit_evidence',
            'credit_evidence' => [
                'version' => 1, 'credit_count' => 1, 'truncated' => false,
                'eligibility' => [$this->expectedBits($bits)], 'tatra_profile_stage' => 'matched',
            ],
        ], AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata);
        Http::assertNothingSent();
    }

    public function test_trusted_reference_uses_the_existing_variable_symbol_and_ambiguity_rules(): void
    {
        $this->noProvider();
        $rows = [
            $this->row(['isExplicitCredit' => false, 'e2eReference' => null, 'variableSymbol' => '1234567890', 'isVariableSymbolExplicit' => true]),
            $this->row(['isExplicitCredit' => false, 'e2eReference' => null, 'variableSymbol' => '1234567890', 'isVariableSymbolExplicit' => false]),
            $this->row(['isExplicitCredit' => false, 'e2eReference' => '', 'isReferenceAmbiguous' => true]),
        ];
        app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload(), $rows);
        $this->assertSame([true, false, false], array_column($this->evidence()['eligibility'], 'trusted_reference'));
        Http::assertNothingSent();
    }

    #[TestWith([3, false])]
    #[TestWith([4, true])]
    #[TestWith([20, true])]
    public function test_credit_diagnostics_are_capped_at_three_rows_and_a_capped_count(int $count, bool $truncated): void
    {
        $this->noProvider();
        $rows = array_fill(0, $count, $this->row(['isExplicitCredit' => false]));
        array_unshift($rows, $this->row(['direction' => BankTransactionDirection::Debit]));
        app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload(), $rows);
        $this->assertSame(3, $this->evidence()['credit_count']);
        $this->assertSame($truncated, $this->evidence()['truncated']);
        $this->assertCount(3, $this->evidence()['eligibility']);
        Http::assertNothingSent();
    }

    #[TestWith(['slsp'])]
    #[TestWith(['missing-body'])]
    #[TestWith(['non-string-body'])]
    #[TestWith(['oversized-body'])]
    public function test_profile_stage_is_not_evaluated_outside_the_bounded_verified_tatra_payload(string $case): void
    {
        $this->noProvider();
        $overrides = match ($case) {
            'slsp' => ['_verified_bank_domain' => 'slsp.sk', 'from' => 'notify@slsp.sk'],
            'missing-body' => ['body' => null],
            'non-string-body' => ['body' => ['synthetic-private-body']],
            'oversized-body' => ['body' => str_repeat('x', 65536)],
        };
        app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload($overrides), [$this->row(['isExplicitCredit' => false])]);
        $this->assertSame('not_evaluated', $this->evidence()['tatra_profile_stage']);
        Http::assertNothingSent();
    }

    #[TestWith(['mail.tatrabanka.sk', 'notify@tatrabanka.sk'])]
    #[TestWith(['mail.tatrabanka.sk', 'notify@notify.tatrabanka.sk'])]
    public function test_existing_aligned_tatra_subdomain_proofs_receive_the_same_profile_diagnostics(string $verifiedDomain, string $from): void
    {
        $this->noProvider();
        app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload([
            '_verified_bank_domain' => $verifiedDomain, 'from' => $from,
        ]), [$this->row(['isExplicitCredit' => false])]);
        $this->assertSame('matched', $this->evidence()['tatra_profile_stage']);
        Http::assertNothingSent();
    }

    public function test_actual_strict_stage_is_recorded_without_copying_body_or_parsed_values(): void
    {
        $this->noProvider();
        $private = 'synthetic-private-note-marker';
        $payload = $this->payload([
            'from' => 'synthetic-private-sender@tatrabanka.sk', 'subject' => $private,
            'body' => str_replace('Referencia platitela: QR-ab29e346f1d841c8a95a63d857490818', 'Referencia platitela: '.$private, TatraBankSepaEvidenceDiagnosticsTest::body()),
        ]);
        app(SepaEmailConfirmationService::class)->handle($this->store, $payload, [$this->row(['isExplicitCredit' => false])]);
        $metadata = AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata;
        $this->assertSame('trusted_reference', $metadata['credit_evidence']['tatra_profile_stage']);
        foreach ([$private, $payload['from'], 'SK4811000000002944116480', 'QR-ab29e346f1d841c8a95a63d857490818', '12.5', $this->store->id] as $value) {
            $this->assertStringNotContainsString($value, json_encode($metadata, JSON_THROW_ON_ERROR));
        }
        foreach ($metadata['credit_evidence']['eligibility'][0] as $bit) {
            $this->assertIsBool($bit);
        }
        Http::assertNothingSent();
    }

    public function test_diagnostic_exception_keeps_original_review_and_never_exposes_exception_text(): void
    {
        $provider = $this->noProvider();
        $service = Mockery::mock(SepaEmailConfirmationService::class, [$provider, app(BankEmailOriginVerifier::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('creditEvidenceDiagnostics')->once()->andThrow(new \RuntimeException('synthetic-private-exception-marker'));
        Log::spy();
        $result = $service->handle($this->store, $this->payload(), [$this->row(['isExplicitCredit' => false])]);
        $this->assertSame(['store_id' => $this->store->id, 'reported' => 0, 'outcome' => 'review'], $result);
        $this->assertSame([
            'reason' => 'incomplete_credit_evidence', 'credit_evidence' => ['version' => 1, 'status' => 'unavailable'],
        ], AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata);
        Log::shouldHaveReceived('notice')->once()->with('SEPA b-mail requires manual verification', [
            'store_id' => $this->store->id, 'reason' => 'incomplete_credit_evidence',
        ]);
        Http::assertNothingSent();
    }

    public function test_unverified_origin_does_not_gain_credit_diagnostics(): void
    {
        $this->noProvider();
        app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload(['_verified_bank_domain' => null]), [$this->row(['isExplicitCredit' => false])]);
        $this->assertSame(['reason' => 'unverified_bank_origin'], AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata);
        Http::assertNothingSent();
    }

    public function test_no_credits_keep_the_existing_empty_outcome_without_audit_or_provider(): void
    {
        $this->noProvider();
        $result = app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload(), [$this->row(['direction' => BankTransactionDirection::Debit])]);
        $this->assertSame(['store_id' => $this->store->id, 'reported' => 0, 'outcome' => null], $result);
        $this->assertSame(0, AuditLog::where('action', 'sepa.bmail_review_required')->count());
        Http::assertNothingSent();
    }

    public function test_identity_presence_is_informational_and_existing_accepted_flow_is_unchanged(): void
    {
        $provider = $this->mock(SepaService::class);
        $provider->shouldReceive('getSettings')->once()->andReturn(['confirmationContractVersion' => 2]);
        $provider->shouldReceive('reportPayment')->once()->with($this->store->btcpay_store_id, Mockery::on(fn (array $report) => $report['reference'] === 'QR-ab29e346f1d841c8a95a63d857490818'
            && $report['amount'] === 12.5 && $report['currency'] === 'EUR'
            && $report['creditorIban'] === 'SK4811000000002944116480'
            && $report['bankOriginVerified'] === true && strlen($report['dedupKey']) === 64
        ), Mockery::type('string'))->andReturn(['outcome' => 'settled']);
        $result = app(SepaEmailConfirmationService::class)->handle($this->store, $this->payload(), [$this->row(['sepaCreditIdentity' => null])]);
        $this->assertSame(['store_id' => $this->store->id, 'reported' => 1, 'outcome' => 'settled'], $result);
        $this->assertSame(['reported' => 1, 'outcome' => 'settled'], AuditLog::where('action', 'sepa.bmail_processed')->sole()->metadata);
        $this->assertSame(0, AuditLog::where('action', 'sepa.bmail_review_required')->count());
    }
}
