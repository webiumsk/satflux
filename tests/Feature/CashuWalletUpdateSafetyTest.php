<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\CashuWalletUpdateService;
use App\Services\WalletConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashuWalletUpdateSafetyTest extends TestCase
{
    use RefreshDatabase;

    private array $settings = ['mintUrl' => 'https://mint.example', 'lightningAddress' => 'merchant@example.com', 'enabled' => true];

    private function store(): Store
    {
        Notification::fake();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);

        return Store::factory()->create(['user_id' => $user->id, 'wallet_type' => null, 'btcpay_store_id' => 'cashu-store']);
    }

    public function test_lost_put_response_keeps_local_wallet_and_journal_then_recovers_by_reads_only(): void
    {
        $store = $this->store();
        $actual = null;
        Http::fake(function ($request) use (&$actual) {
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => true]);
            }
            if ($request->method() === 'PUT') {
                $actual = $request->data();
                throw new ConnectionException('Response lost after BTCPay committed');
            }
            if (str_ends_with($request->url(), '/settings')) {
                return Http::response($actual);
            }

            return Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true]]);
        });
        try {
            app(CashuWalletUpdateService::class)->update($store, $store->user, $this->settings, false);
            $this->fail('Unknown remote outcome must not be accepted.');
        } catch (BtcPayException) {
            $this->assertNull($store->fresh()->wallet_type);
        }
        $attempt = WalletConfigurationAttempt::sole();
        $this->assertSame('uncertain', $attempt->status);
        $this->assertNotNull($attempt->encrypted_secret);
        try {
            app(WalletConnectionService::class)->assertWalletCanChange($store);
            $this->fail('Pending journal must block another wallet update.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('reconciliation', $e->getMessage());
        }
        $writes = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() !== 'GET')->count();
        $this->assertTrue(app(CashuWalletUpdateService::class)->reconcile($store, $store->user, $attempt));
        $this->assertSame('cashu', $store->fresh()->wallet_type);
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->encrypted_secret);
        $this->assertSame($writes, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() !== 'GET')->count());
    }

    public function test_readback_with_active_lightning_does_not_confirm_cashu_switch(): void
    {
        $store = $this->store();
        Http::fake(fn ($r) => str_ends_with($r->url(), '/safety')
            ? Http::response(['safeToChangeWallet' => true])
            : (str_contains($r->url(), '/payment-methods') && $r->method() === 'GET'
                ? Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true], ['paymentMethodId' => 'BTC-LN', 'enabled' => true]])
                : Http::response($this->settings)));
        try {
            app(CashuWalletUpdateService::class)->update($store, $store->user, $this->settings, false);
            $this->fail('Both active payment methods must keep reconciliation pending.');
        } catch (ValidationException) {
            $this->assertNull($store->fresh()->wallet_type);
            $this->assertSame('uncertain', WalletConfigurationAttempt::sole()->status);
        }
    }

    public function test_unresolved_cashu_assets_block_even_the_first_connection_before_any_write(): void
    {
        $store = $this->store();
        Http::fake(['*' => Http::response(['safeToChangeWallet' => false, 'unresolvedChange' => 1])]);
        try {
            app(CashuWalletUpdateService::class)->update($store, $store->user, $this->settings, false);
            $this->fail('Unresolved ecash must prevent changing the payout destination.');
        } catch (ValidationException) {
            $this->assertSame(0, WalletConfigurationAttempt::count());
            Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
        }
    }
}
