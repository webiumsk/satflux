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
        ], app(CashuService::class)->getWalletSafety('diagnostic-store', 'diagnostic-merchant-key'));
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
            ->assertJsonPath('data.wallet_safety', ['status' => 'blocked', 'unresolved_payments' => 1, 'unresolved_change' => 2]);
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
            ->assertJsonPath('data.wallet_safety', ['status' => 'unavailable', 'unresolved_payments' => null, 'unresolved_change' => null]);
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
            ? Http::response(['safeToChangeWallet' => false, 'unresolvedPayments' => $payments, 'unresolvedChange' => $change, 'proofs' => 'synthetic-sensitive-marker'])
            : Http::response(['mintUrl' => 'https://mint.example', 'lightningAddress' => 'original@example.com', 'enabled' => true]));
        $response = $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", [
            'mint_url' => 'https://mint.example', 'lightning_address' => 'replacement@example.com', 'enabled' => true,
        ]);
        $response->assertUnprocessable()->assertJsonPath('code', 'cashu_outstanding_payments')
            ->assertJsonPath('wallet_safety', ['status' => 'blocked', 'unresolved_payments' => $payments, 'unresolved_change' => $change]);
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
