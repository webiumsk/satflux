<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Models\WalletConnection;
use App\Services\WalletConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;
use Tests\Unit\Bolt11Test;

class WalletProbeMonitoringTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

    private const OLD = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_old;wallet-id=old';

    private const NEW = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_new;wallet-id=new';

    private function connectedStore(): Store
    {
        Notification::fake();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'probe-store', 'wallet_type' => 'blink']);
        WalletConnection::create([
            'store_id' => $store->id, 'type' => 'blink', 'status' => 'connected',
            'encrypted_secret' => Crypt::encryptString(self::OLD), 'submitted_by_user_id' => $user->id,
        ]);

        return $store;
    }

    public function test_archived_automatic_probe_blocks_only_until_its_actual_monitoring_deadline(): void
    {
        $this->freezeTime();
        $store = $this->connectedStore();
        $invoices = [];
        Http::fake(function (Request $request) use (&$invoices) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/api/v1/stores/probe-store/invoices' && $request->method() === 'POST') {
                // Model pinned BTCPay 2.4.5: omitted monitoring inherits one day.
                $expires = now()->addMinutes($request['checkout']['expirationMinutes']);
                $invoices[] = [
                    'id' => 'probe', 'status' => 'New', 'archived' => false,
                    'metadata' => $request['metadata'], 'expirationTime' => $expires->timestamp,
                    'monitoringExpiration' => $expires->copy()->addMinutes($request['checkout']['monitoringMinutes'] ?? 1440)->timestamp,
                ];

                return Http::response($invoices[0]);
            }
            if ($path === '/api/v1/stores/probe-store/invoices/probe' && $request->method() === 'DELETE') {
                // Archive does not remove the invoice or change its monitoring.
                $invoices[0]['archived'] = true;

                return Http::response([]);
            }
            if ($path === '/api/v1/stores/probe-store/invoices' && $request->method() === 'GET') {
                return Http::response($invoices);
            }
            if ($path === '/api/v1/stores/probe-store/invoices/probe/payment-methods') {
                return Http::response([['paymentMethodId' => 'BTC-LN', 'destination' => Bolt11Test::SPEC_COFFEE, 'payments' => []]]);
            }
            if ($path === '/api/v1/stores/probe-store/payment-methods' && $request->method() === 'GET') {
                return Http::response([['paymentMethodId' => 'BTC-LN', 'enabled' => true, 'config' => ['connectionString' => self::OLD]]]);
            }
            throw new \RuntimeException('Unexpected synthetic BTCPay request.');
        });

        $wallets = app(WalletConnectionService::class);
        $wallets->markConnected($store->walletConnection, $store->user);
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $store->walletConnection->fresh()->payee_pubkeys);
        $this->assertCount(1, $invoices, 'Connecting a wallet creates a probe without a merchant checkout.');
        $this->assertTrue($invoices[0]['archived']);
        $this->assertSame(now()->addMinutes(2)->timestamp, $invoices[0]['monitoringExpiration']);

        $grant = $this->grantWalletChange($store->user, $store);
        $writes = count(Http::recorded(fn (Request $r) => $r->method() !== 'GET'));
        foreach ([0, 119] as $advance) {
            $this->travel($advance)->seconds();
            try {
                $wallets->createOrUpdate($store, 'blink', self::NEW, $store->user, 'pending');
                $this->fail('An archived probe must block replacement while monitoring is active.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('still being monitored', $e->getMessage());
            }
            $this->assertSame(self::OLD, Crypt::decryptString($store->walletConnection->fresh()->encrypted_secret));
            $this->assertNull($grant->fresh()->consumed_at);
            $this->assertDatabaseCount('wallet_configuration_attempts', 0);
            $this->assertSame($writes, count(Http::recorded(fn (Request $r) => $r->method() !== 'GET')));
        }
        $this->travel(2)->seconds();
        $wallets->assertWalletCanChange($store->fresh());
        $this->assertSame($writes, count(Http::recorded(fn (Request $r) => $r->method() !== 'GET')));
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_contains($r->url(), '/stores/probe-store/invoices?')
            && str_contains($r->url(), 'includeArchived=true')
            && $r->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public static function unsafeInvoiceStates(): array
    {
        return [
            'legacy automatic probe' => ['Expired', 86400, true],
            'forged canary metadata' => ['New', 3600, true],
            'ordinary archived invoice' => ['Expired', 3600, false],
            'processing after monitoring' => ['Processing', -60, true],
            'missing monitoring deadline' => ['New', null, true],
            'invalid monitoring deadline' => ['Invalid', 'invalid-date', true],
            'unknown invoice status' => ['Unknown', -60, true],
        ];
    }

    #[DataProvider('unsafeInvoiceStates')]
    public function test_no_marker_or_archive_state_bypasses_payment_safety(string $status, int|string|null $seconds, bool $marker): void
    {
        $store = $this->connectedStore();
        $grant = $this->grantWalletChange($store->user, $store);
        $invoice = [
            'status' => $status, 'archived' => true, 'amount' => '0.00000100',
            'metadata' => ['satflux_canary' => $marker, 'itemDesc' => 'Satflux wallet check (not payable)'],
            'monitoringExpiration' => is_int($seconds) ? now()->addSeconds($seconds)->timestamp : $seconds,
        ];
        Http::fake(['*/invoices*' => Http::response([$invoice])]);
        $this->assertReplacementBlocked($store);
        $this->assertNull($grant->fresh()->consumed_at);
    }

    public function test_archived_expired_invoices_on_later_pages_still_block(): void
    {
        $store = $this->connectedStore();
        $this->grantWalletChange($store->user, $store);
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(($query['skip'] ?? null) === '0'
                ? array_fill(0, 100, ['status' => 'Expired', 'archived' => true, 'monitoringExpiration' => now()->subMinute()->timestamp])
                : [['status' => 'Expired', 'archived' => true, 'monitoringExpiration' => now()->addDay()->timestamp]]);
        });
        $this->assertReplacementBlocked($store);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'skip=100')
            && str_contains($r->url(), '/stores/probe-store/invoices?')
            && $r->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public static function unverifiableLists(): array
    {
        return [
            'upstream outage' => [503, []],
            'missing store or permission' => [404, []],
            'malformed successful list' => [200, ['unexpected' => 'object']],
        ];
    }

    #[DataProvider('unverifiableLists')]
    public function test_unverifiable_invoice_list_never_permits_a_wallet_write(int $status, array $body): void
    {
        $store = $this->connectedStore();
        $grant = $this->grantWalletChange($store->user, $store);
        Http::fake(['*/invoices*' => Http::response($body, $status)]);
        $this->assertReplacementBlocked($store);
        $this->assertNull($grant->fresh()->consumed_at);
    }

    private function assertReplacementBlocked(Store $store): void
    {
        try {
            app(WalletConnectionService::class)->createOrUpdate($store, 'blink', self::NEW, $store->user, 'pending');
            $this->fail('Unresolved invoice evidence must block a replacement.');
        } catch (ValidationException) {
            $this->assertSame(self::OLD, Crypt::decryptString($store->walletConnection->fresh()->encrypted_secret));
            $this->assertSame(0, WalletConfigurationAttempt::count());
            Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
        }
    }
}
