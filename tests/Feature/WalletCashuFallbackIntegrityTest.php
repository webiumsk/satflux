<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Models\UserMessage;
use App\Models\WalletConnection;
use App\Notifications\WalletConfigDriftNotification;
use App\Services\WalletConnectionService;
use App\Services\WalletSecurity\PayeeAttestationService;
use App\Services\WalletSecurity\WalletConfigIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WalletCashuFallbackIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $lightningConnection = '';

    private ?bool $cashuEnabled = null;

    private bool $swapLightningOnCashuSave = false;

    private bool $failSnapshotAfterCashuSave = false;

    private bool $snapshotDown = false;

    private bool $unexpectedCashuConfig = false;

    private ?\Closure $duringCashuSave = null;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.discord.support_webhook_url' => null]);
        $this->mock(PayeeAttestationService::class)->shouldReceive('learn')->andReturn(false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/plugins/cashumelt/settings')) {
                if ($request->method() === 'PUT') {
                    $this->cashuEnabled = (bool) $request['enabled'];
                    if ($this->swapLightningOnCashuSave) {
                        $this->lightningConnection = 'type=lnaddress;ln-address=attacker@example.com;server=https://example.com;';
                    }
                    $this->snapshotDown = $this->failSnapshotAfterCashuSave;
                    if ($this->duringCashuSave !== null) {
                        ($this->duringCashuSave)();
                    }
                }

                return Http::response(['enabled' => $this->cashuEnabled ?? false,
                    'mintUrl' => 'https://mint.example.com', 'lightningAddress' => 'merchant@coinos.io']);
            }
            if (str_contains($request->url(), '/payment-methods/BTC-LN') && $request->method() === 'PUT') {
                $this->lightningConnection = $request['config']['connectionString'];

                return Http::response([]);
            }
            if (preg_match('~/payment-methods/(CASHU|CASHUMELT)$~', $request->url()) && $request->method() === 'DELETE') {
                $this->cashuEnabled = null;

                return Http::response([], 200);
            }
            if (str_contains($request->url(), '/payment-methods') && $request->method() === 'GET') {
                if ($this->snapshotDown) {
                    return Http::response([], 503);
                }
                $methods = [['paymentMethodId' => 'BTC-LN', 'enabled' => true,
                    'config' => ['connectionString' => $this->lightningConnection]]];
                if ($this->cashuEnabled !== null) {
                    $methods[] = ['paymentMethodId' => 'CASHU', 'enabled' => $this->cashuEnabled,
                        'config' => $this->unexpectedCashuConfig
                            ? ['enabled' => $this->cashuEnabled, 'destination' => 'unexpected']
                            : ['enabled' => $this->cashuEnabled]];
                }

                return Http::response($methods);
            }

            return Http::response([]);
        });
    }

    private function connectCoinos(): WalletConnection
    {
        $user = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        return app(WalletConnectionService::class)->createOrUpdate(
            $store, 'lnaddress', 'merchant@coinos.io', $user, 'pending',
        );
    }

    public function test_new_coinos_store_with_cashu_fallback_does_not_raise_security_messages_or_mail(): void
    {
        $connection = $this->connectCoinos();

        $this->assertSame('connected', $connection->status);
        $this->assertTrue($connection->store->fresh()->cashu_fallback_enabled);
        $this->assertArrayHasKey('CASHU', $connection->config_fingerprint);
        $this->assertSame('ok', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
        $this->assertSame(0, UserMessage::where('type', 'security')->count());
        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.drift_detected')->count());
        Notification::assertNotSentTo($connection->store->user, WalletConfigDriftNotification::class);
    }

    public function test_authorized_fallback_does_not_adopt_a_concurrent_lightning_wallet_swap(): void
    {
        $this->swapLightningOnCashuSave = true;
        $connection = $this->connectCoinos();

        $result = app(WalletConfigIntegrityService::class)->verify($connection);

        $this->assertSame('drift', $result['status']);
        $this->assertSame(['BTC-LN'], $result['diff']['changed']);
        $this->assertSame([], $result['diff']['added']);
    }

    public function test_failed_readback_keeps_the_original_baseline(): void
    {
        $this->failSnapshotAfterCashuSave = true;
        $connection = $this->connectCoinos();
        $this->assertArrayNotHasKey('CASHU', $connection->config_fingerprint);

        $this->snapshotDown = false;
        $this->assertSame('drift', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
    }

    public function test_external_cashu_change_still_raises_drift(): void
    {
        $connection = $this->connectCoinos();
        $this->cashuEnabled = false;

        $result = app(WalletConfigIntegrityService::class)->verify($connection);

        $this->assertSame('drift', $result['status']);
        $this->assertSame(['CASHU'], $result['diff']['changed']);
    }

    public function test_verification_skips_a_store_while_its_wallet_is_being_configured(): void
    {
        $status = null;
        $this->duringCashuSave = function () use (&$status) {
            $connection = WalletConnection::firstOrFail();
            $status = app(WalletConfigIntegrityService::class)->verify($connection)['status'];
        };
        $connection = $this->connectCoinos();

        $this->assertSame('skipped', $status);
        $this->assertSame('ok', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
        Notification::assertNotSentTo($connection->store->user, WalletConfigDriftNotification::class);
    }

    public function test_verification_uses_the_latest_baseline_after_waiting_for_wallet_configuration(): void
    {
        $connection = $this->connectCoinos();
        $stale = $connection->fresh();
        $this->cashuEnabled = false;
        app(WalletConfigIntegrityService::class)->baseline($connection);

        $this->assertSame('ok', app(WalletConfigIntegrityService::class)->verify($stale)['status']);
    }

    public function test_disabling_and_removing_fallback_is_an_authorized_cashu_change(): void
    {
        $connection = $this->connectCoinos();
        $connection = app(WalletConnectionService::class)->createOrUpdate(
            $connection->store, 'blink', 'type=blink;server=https://api.blink.sv/graphql;api-key=test;wallet-id=wallet;',
            $connection->store->user, 'pending',
        );

        $this->assertSame('connected', $connection->status);
        $this->assertArrayNotHasKey('CASHU', $connection->config_fingerprint);
        $this->assertSame('ok', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
        Notification::assertNotSentTo($connection->store->user, WalletConfigDriftNotification::class);
    }

    public function test_public_fallback_setup_also_holds_the_wallet_lock_and_records_cashu(): void
    {
        $connection = $this->connectCoinos();
        $status = null;
        $this->duringCashuSave = function () use ($connection, &$status) {
            $status = app(WalletConfigIntegrityService::class)->verify($connection)['status'];
        };
        $store = $connection->store;
        app(WalletConnectionService::class)->configureCashuFallback(
            $store, 'merchant@coinos.io', $store->user->getBtcPayApiKeyOrFail(), $store->user,
        );

        $this->assertSame('skipped', $status);
        $this->assertSame('ok', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
    }

    public function test_verification_does_not_read_remote_config_under_another_wallet_lock(): void
    {
        $connection = $this->connectCoinos();
        $lock = Cache::lock('wallet-update:'.$connection->store_id, 1800);
        $this->assertTrue($lock->get());
        $requestCount = count(Http::recorded());
        try {
            $this->assertSame('skipped', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
            $this->assertSame($requestCount, count(Http::recorded()));
        } finally {
            $lock->release();
        }
    }

    public function test_fallback_setup_does_not_accept_unexpected_cashu_configuration(): void
    {
        $this->unexpectedCashuConfig = true;
        $connection = $this->connectCoinos();

        $this->assertArrayNotHasKey('CASHU', $connection->config_fingerprint);
        $result = app(WalletConfigIntegrityService::class)->verify($connection);
        $this->assertSame('drift', $result['status']);
        $this->assertSame(['CASHU'], $result['diff']['added']);
    }

    public function test_verification_skips_a_connection_removed_during_a_wallet_change(): void
    {
        $connection = $this->connectCoinos();
        $connection->delete();

        $this->assertSame('skipped', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
    }

    public function test_late_baselining_releases_the_wallet_lock_before_learning_the_payee(): void
    {
        $connection = $this->connectCoinos();
        $connection->update(['config_fingerprint' => null]);
        $this->mock(PayeeAttestationService::class)
            ->shouldReceive('learn')->once()->withArgs(fn ($row, $by, $reason) => $row->id === $connection->id && $reason === 'late')
            ->andReturnUsing(function () use ($connection) {
                $this->assertTrue(Cache::lock('wallet-update:'.$connection->store_id, 1800)->get(fn () => true));

                return true;
            });
        $this->app->forgetInstance(WalletConfigIntegrityService::class);

        $this->assertSame('baselined', app(WalletConfigIntegrityService::class)->verify($connection)['status']);
    }
}
