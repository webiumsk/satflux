<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;

class CashuSafetyDiagnosticsTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

    private const UNKNOWN_PAYMENT_PARTITION = [
        'failed_payments' => null, 'other_non_terminal_payments' => null, 'terminal_recovery_payments' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
    }

    private function store(): Store
    {
        $user = User::factory()->create(['btcpay_api_key' => 'diagnostic-merchant-key']);

        return Store::factory()->create(['user_id' => $user->id, 'wallet_type' => 'cashu', 'btcpay_store_id' => 'diagnostic-store']);
    }

    public static function statuses(): array
    {
        return [[true, 'safe'], [false, 'blocked'], [null, 'unavailable'], ['true', 'unavailable'], [1, 'unavailable'], [0, 'unavailable']];
    }

    #[DataProvider('statuses')]
    public function test_only_a_strict_provider_boolean_controls_the_diagnostic_status(mixed $boolean, string $expected): void
    {
        Http::fake(['*' => Http::response(['safeToChangeWallet' => $boolean, 'unresolvedPayments' => 0, 'unresolvedChange' => 0])]);
        $this->assertSame([
            'status' => $expected, 'unresolved_payments' => 0, 'unresolved_change' => 0,
            ...self::UNKNOWN_PAYMENT_PARTITION,
        ], app(CashuService::class)->getWalletSafety('diagnostic-store', 'diagnostic-merchant-key'));
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public static function counters(): array
    {
        return [
            'zero' => [0, 0], 'positive' => [3, 3], 'maximum' => [2147483647, 2147483647],
            'missing' => [null, null], 'negative' => [-1, null], 'oversized' => [2147483648, null],
            'true' => [true, null], 'false' => [false, null], 'integer string' => ['3', null],
            'zero string' => ['0', null], 'fraction' => [1.5, null], 'whole float' => [1.0, null],
            'array' => [[], null],
        ];
    }

    #[DataProvider('counters')]
    public function test_counters_are_bounded_nonnegative_integers_without_coercion(mixed $value, ?int $expected): void
    {
        $body = ['safeToChangeWallet' => false, 'unresolvedPayments' => $value, 'unresolvedChange' => $value];
        Http::fake(['*' => Http::response(json_encode($body, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json'])]);
        $this->assertSame([
            'status' => 'blocked', 'unresolved_payments' => $expected, 'unresolved_change' => $expected,
            ...self::UNKNOWN_PAYMENT_PARTITION,
        ], app(CashuService::class)->getWalletSafety('diagnostic-store', 'diagnostic-merchant-key'));
    }

    public static function paymentPartitions(): array
    {
        $valid = ['unresolvedPayments' => 3, 'failedPayments' => 2, 'otherNonTerminalPayments' => 1, 'terminalRecoveryPayments' => 0];
        $cases = [
            'two failed out of three obligations' => [$valid, [2, 1, 0]],
            'terminal recovery obligation' => [array_replace($valid, ['otherNonTerminalPayments' => 0, 'terminalRecoveryPayments' => 1]), [2, 0, 1]],
            'zero' => [array_fill_keys(array_keys($valid), 0), [0, 0, 0]],
            'maximum' => [array_replace($valid, ['unresolvedPayments' => 2147483647, 'failedPayments' => 2147483647, 'otherNonTerminalPayments' => 0]), [2147483647, 0, 0]],
            'legacy' => [['unresolvedPayments' => 3], null],
            'missing total' => [array_diff_key($valid, ['unresolvedPayments' => true]), null],
            'string total' => [array_replace($valid, ['unresolvedPayments' => '3']), null],
            'inconsistent sum' => [array_replace($valid, ['unresolvedPayments' => 2]), null],
            'oversized sum' => [array_replace($valid, ['failedPayments' => 2147483647, 'otherNonTerminalPayments' => 2147483647]), null],
        ];
        foreach (['failedPayments', 'otherNonTerminalPayments', 'terminalRecoveryPayments'] as $name) {
            $cases['missing '.$name] = [array_diff_key($valid, [$name => true]), null];
            foreach (['null' => null, 'true' => true, 'false' => false, 'string' => '0', 'float' => 0.0, 'negative' => -1, 'oversized' => 2147483648, 'array' => []] as $label => $value) {
                $cases[$name.' '.$label] = [array_replace($valid, [$name => $value]), null];
            }
        }

        return $cases;
    }

    #[DataProvider('paymentPartitions')]
    public function test_payment_partition_is_all_or_unknown_and_never_changes_the_safety_status(array $partition, ?array $expected): void
    {
        Http::fake(['*' => Http::response(json_encode([
            'safeToChangeWallet' => false, 'unresolvedChange' => 0,
            ...$partition, 'proofs' => 'synthetic-sensitive-marker',
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json'])]);

        $snapshot = app(CashuService::class)->getWalletSafety('diagnostic-store', 'diagnostic-merchant-key');

        $this->assertSame('blocked', $snapshot['status']);
        $this->assertSame($expected === null ? self::UNKNOWN_PAYMENT_PARTITION : [
            'failed_payments' => $expected[0], 'other_non_terminal_payments' => $expected[1], 'terminal_recovery_payments' => $expected[2],
        ], array_intersect_key($snapshot, self::UNKNOWN_PAYMENT_PARTITION));
        $this->assertSame(0, $snapshot['unresolved_change']);
        $this->assertStringNotContainsString('synthetic-sensitive-marker', json_encode($snapshot, JSON_THROW_ON_ERROR));
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public static function paymentDiagnosticMetadata(): array
    {
        $unknown = ['blocks_wallet_change' => null, 'wallet_change_block_reasons' => null, 'recovery_phase' => null];
        $reasons = ['non_terminal_settlement', 'retained_minted_proofs', 'retained_issuance_intent', 'retained_change_outputs', 'retained_payout_quote'];
        $cases = [
            'legacy' => [[], $unknown],
            'all reasons' => [[
                'blocksWalletChange' => true, 'walletChangeBlockReasons' => $reasons, 'recoveryPhase' => 'proofs_retained',
            ], ['blocks_wallet_change' => true, 'wallet_change_block_reasons' => $reasons, 'recovery_phase' => 'proofs_retained']],
            'explicitly unblocked' => [[
                'blocksWalletChange' => false, 'walletChangeBlockReasons' => [], 'recoveryPhase' => 'complete',
            ], ['blocks_wallet_change' => false, 'wallet_change_block_reasons' => [], 'recovery_phase' => 'complete']],
        ];
        foreach (['complete', 'expired_unpaid', 'terminal_recovery_review', 'original_settings_required', 'accounting_pending', 'payout_reconciliation', 'proofs_retained', 'issuance_restore', 'recovery_evidence_review', 'funding_verification'] as $phase) {
            $cases[$phase] = [['recoveryPhase' => $phase], array_replace($unknown, ['recovery_phase' => $phase])];
        }
        foreach (['string' => 'false', 'zero' => 0, 'one' => 1, 'array' => []] as $label => $value) {
            $cases['boolean '.$label] = [['blocksWalletChange' => $value], $unknown];
        }
        foreach ([
            'null' => null, 'string' => 'retained_minted_proofs', 'associative' => ['reason' => 'retained_minted_proofs'],
            'duplicate' => ['retained_minted_proofs', 'retained_minted_proofs'],
            'unknown' => ['retained_minted_proofs', 'synthetic-sensitive-marker'],
            'nested' => [['proof' => 'synthetic-sensitive-marker']], 'integer' => [0],
            'too many' => [...$reasons, 'non_terminal_settlement'],
        ] as $label => $value) {
            $cases['reasons '.$label] = [['walletChangeBlockReasons' => $value], $unknown];
        }
        foreach (['unknown' => 'synthetic-sensitive-marker', 'whitespace' => ' complete ', 'integer' => 0, 'array' => []] as $label => $value) {
            $cases['phase '.$label] = [['recoveryPhase' => $value], $unknown];
        }

        return $cases;
    }

    #[DataProvider('paymentDiagnosticMetadata')]
    public function test_payment_metadata_is_projected_strictly_without_changing_settlement_or_actions(array $metadata, array $expected): void
    {
        $store = $this->store();
        Http::fake(fn ($request) => str_ends_with($request->url(), '/safety')
            ? Http::response(['safeToChangeWallet' => false, 'unresolvedPayments' => 3, 'unresolvedChange' => 0,
                'failedPayments' => 2, 'otherNonTerminalPayments' => 0, 'terminalRecoveryPayments' => 1])
            : Http::response(['total' => 1, 'items' => [[
                'quoteId' => 'diagnostic-quote', 'settlementState' => 'PENDING', 'settledAt' => '2026-10-16T00:00:00Z',
                'state' => 'PAID', 'originalSettingsRequired' => false, 'needsManualReview' => false,
                ...$metadata, 'proofs' => ['C' => 'synthetic-sensitive-marker'], 'privateQuoteId' => 'synthetic-sensitive-marker',
            ]]]));

        $response = $this->actingAs($store->user)->getJson("/api/stores/{$store->id}/cashu/payments");

        $response->assertOk()
            ->assertJsonPath('data.items.0.settlement_state', 'SETTLED')
            ->assertJsonPath('data.items.0.reported_settlement_state', 'PENDING')
            ->assertJsonPath('data.items.0.state', 'PAID')
            ->assertJsonPath('data.items.0.original_settings_required', false)
            ->assertJsonPath('data.items.0.needs_manual_review', false)
            ->assertJsonPath('data.wallet_safety.failed_payments', 2)
            ->assertJsonPath('data.wallet_safety.terminal_recovery_payments', 1);
        foreach ($expected as $name => $value) {
            $response->assertJsonPath('data.items.0.'.$name, $value);
        }
        $this->assertStringNotContainsString('synthetic-sensitive-marker', $response->getContent());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer diagnostic-merchant-key'));
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_payment_list_preserves_history_and_exposes_only_allowlisted_safety_and_reported_states(): void
    {
        $store = $this->store();
        Http::fake(fn ($request) => str_ends_with($request->url(), '/safety')
            ? Http::response([
                'safeToChangeWallet' => false, 'unresolvedPayments' => 1, 'unresolvedChange' => 2,
                'proofs' => ['C' => 'synthetic-sensitive-marker'], 'storeId' => 'private-provider-id',
                'address' => 'private-address@example.com', 'error' => 'synthetic-private-error',
            ])
            : Http::response(['total' => 2, 'items' => [
                ['quoteId' => 'quote', 'settlementState' => ' pending ', 'settledAt' => '2026-10-16T00:00:00Z'],
                ['quoteId' => 'other', 'settlementState' => 'UNRECOGNIZED'],
            ]]));
        $response = $this->actingAs($store->user)->getJson("/api/stores/{$store->id}/cashu/payments");
        $response->assertOk()->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.settlement_state', 'SETTLED')
            ->assertJsonPath('data.items.0.reported_settlement_state', 'PENDING')
            ->assertJsonPath('data.items.1.reported_settlement_state', null)
            ->assertJsonPath('data.wallet_safety', ['status' => 'blocked', 'unresolved_payments' => 1, 'unresolved_change' => 2, ...self::UNKNOWN_PAYMENT_PARTITION]);
        foreach (['synthetic-sensitive-marker', 'private-provider-id', 'private-address@example.com', 'synthetic-private-error'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/safety')
            && $request->hasHeader('Authorization', 'Bearer diagnostic-merchant-key'));
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_unavailable_diagnostics_do_not_erase_an_available_payment_list(): void
    {
        $store = $this->store();
        Http::fake(fn ($request) => str_ends_with($request->url(), '/safety')
            ? Http::response(['error' => 'synthetic-private-error'], 503)
            : Http::response(['total' => 1, 'items' => [['quoteId' => 'available-history', 'settlementState' => 'EXPIRED_UNPAID']]]));
        $this->actingAs($store->user)->getJson("/api/stores/{$store->id}/cashu/payments")
            ->assertOk()->assertJsonPath('data.items.0.quote_id', 'available-history')
            ->assertJsonPath('data.wallet_safety', ['status' => 'unavailable', 'unresolved_payments' => null, 'unresolved_change' => null, ...self::UNKNOWN_PAYMENT_PARTITION]);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public static function deniedRequests(): array
    {
        return [['anonymous', 401], ['another-owner', 403], ['unverified', 403]];
    }

    #[DataProvider('deniedRequests')]
    public function test_diagnostics_retain_authentication_email_and_tenant_boundaries(string $actor, int $status): void
    {
        $store = $this->store();
        Http::fake();
        if ($actor !== 'anonymous') {
            $user = $actor === 'unverified' ? $store->user : User::factory()->create();
            if ($actor === 'unverified') {
                $user->forceFill(['email_verified_at' => null])->save();
            }
            $this->actingAs($user);
        }
        $this->getJson("/api/stores/{$store->id}/cashu/payments")->assertStatus($status);
        Http::assertNothingSent();
    }

    public static function blockedCounters(): array
    {
        return [[0, 0], [null, null], [4, 2]];
    }

    #[DataProvider('blockedCounters')]
    public function test_a_false_boolean_blocks_a_real_change_regardless_of_counters_and_keeps_the_email_grant(?int $payments, ?int $change): void
    {
        $store = $this->store();
        $grant = $this->grantWalletChange($store->user, $store);
        Http::fake(fn ($request) => str_ends_with($request->url(), '/safety')
            ? Http::response(['safeToChangeWallet' => false, 'unresolvedPayments' => $payments, 'unresolvedChange' => $change,
                'failedPayments' => $payments, 'otherNonTerminalPayments' => 0, 'terminalRecoveryPayments' => 0,
                'proofs' => 'synthetic-sensitive-marker'])
            : Http::response(['mintUrl' => 'https://mint.example', 'lightningAddress' => 'original@example.com', 'enabled' => true]));
        $response = $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", [
            'mint_url' => 'https://mint.example', 'lightning_address' => 'replacement@example.com', 'enabled' => true,
        ]);
        $response->assertUnprocessable()->assertJsonPath('code', 'cashu_outstanding_payments')
            ->assertJsonPath('wallet_safety', ['status' => 'blocked', 'unresolved_payments' => $payments, 'unresolved_change' => $change,
                ...($payments === null ? self::UNKNOWN_PAYMENT_PARTITION : [
                    'failed_payments' => $payments, 'other_non_terminal_payments' => 0, 'terminal_recovery_payments' => 0,
                ])]);
        $this->assertStringNotContainsString('synthetic-sensitive-marker', $response->getContent());
        $this->assertSame(0, WalletConfigurationAttempt::count());
        $this->assertNull($grant->fresh()->consumed_at);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_a_safe_display_snapshot_is_not_reused_to_authorize_a_later_real_change(): void
    {
        $store = $this->store();
        $this->grantWalletChange($store->user, $store);
        $safetyReads = 0;
        Http::fake(function ($request) use (&$safetyReads) {
            if (str_ends_with($request->url(), '/payments')) {
                return Http::response(['total' => 0, 'items' => []]);
            }
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => ++$safetyReads === 1, 'unresolvedPayments' => 0, 'unresolvedChange' => 0]);
            }

            return Http::response(['mintUrl' => 'https://mint.example', 'lightningAddress' => 'original@example.com', 'enabled' => true]);
        });
        $this->actingAs($store->user)->getJson("/api/stores/{$store->id}/cashu/payments")
            ->assertOk()->assertJsonPath('data.wallet_safety.status', 'safe');
        $this->putJson("/api/stores/{$store->id}/cashu/settings", [
            'mint_url' => 'https://mint.example', 'lightning_address' => 'replacement@example.com',
        ])->assertUnprocessable()->assertJsonPath('code', 'cashu_outstanding_payments');
        $this->assertSame(2, $safetyReads);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public static function loggingCases(): array
    {
        return [['success'], ['400'], ['422'], ['500'], ['429'], ['transport']];
    }

    #[DataProvider('loggingCases')]
    public function test_safety_logging_and_exceptions_never_include_provider_body_identifiers_or_error_details(string $failure): void
    {
        $records = [];
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$records) {
            $records[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('btcpay')->andReturn($logger);
        Log::shouldReceive('error')->never();
        Http::fake(function () use ($failure) {
            if ($failure === 'transport') {
                throw new ConnectionException('synthetic-sensitive-marker diagnostic-store https://private.example');
            }

            return Http::response([
                'safeToChangeWallet' => true, 'unresolvedPayments' => 0, 'unresolvedChange' => 0,
                'proofs' => ['C' => 'synthetic-sensitive-marker'], 'error' => 'synthetic-sensitive-marker',
                'errors' => ['private' => 'synthetic-sensitive-marker'], 'code' => 'synthetic-sensitive-marker',
            ], $failure === 'success' ? 200 : (int) $failure);
        });
        try {
            $client = new BtcPayClient('diagnostic-merchant-key');
            $client->get('/api/v1/stores/diagnostic-store/plugins/cashumelt/safety', ['private' => 'synthetic-sensitive-marker']);
            $this->assertSame('success', $failure);
        } catch (BtcPayException $e) {
            $this->assertNotSame('success', $failure);
            $this->assertStringNotContainsString('synthetic-sensitive-marker', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertNotEmpty($records);
        foreach ($records as [$message, $context]) {
            $this->assertSame('BTCPay API Request', $message);
            $this->assertSame(['method', 'endpoint', 'status'], array_keys($context));
            $this->assertSame('/api/v1/stores/[redacted]/plugins/cashumelt/safety', $context['endpoint']);
        }
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        foreach (['synthetic-sensitive-marker', 'diagnostic-store', 'diagnostic-merchant-key', 'private.example'] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
    }
}
