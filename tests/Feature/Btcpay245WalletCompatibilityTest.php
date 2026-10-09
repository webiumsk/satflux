<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Models\WalletConnection;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\InvoiceService;
use App\Services\BtcPay\LightningService;
use App\Services\Invoicing\BusinessDocumentBtcPayService;
use App\Services\WalletConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Btcpay245WalletCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_old;wallet-id=old';

    private const NEW = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_new;wallet-id=new';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
    }

    private function connectedStore(): Store
    {
        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'wallet-store', 'wallet_type' => 'blink']);
        WalletConnection::create([
            'store_id' => $store->id, 'type' => 'blink', 'status' => 'connected',
            'encrypted_secret' => Crypt::encryptString(self::OLD), 'submitted_by_user_id' => $user->id,
        ]);

        return $store;
    }

    public function test_pending_archived_invoice_blocks_replacement_before_any_write(): void
    {
        $store = $this->connectedStore();
        Http::fake(['*/invoices*' => Http::response([[
            'status' => 'Expired', 'archived' => true, 'monitoringExpiration' => now()->addHour()->timestamp,
        ]])]);
        try {
            app(WalletConnectionService::class)->createOrUpdate($store, 'blink', self::NEW, $store->user, 'pending');
            $this->fail('A monitored invoice must prevent changing its receiving wallet.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still being monitored', $e->getMessage());
        }
        $this->assertSame(self::OLD, Crypt::decryptString($store->walletConnection->encrypted_secret));
        $this->assertSame(0, WalletConfigurationAttempt::count());
        Http::assertNotSent(fn ($r) => in_array($r->method(), ['PUT', 'POST', 'DELETE'], true));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'includeArchived=1') && $r->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public function test_unknown_remote_result_blocks_invoices_and_recovers_from_persisted_journal(): void
    {
        $store = $this->connectedStore();
        $actual = null;
        Http::fake(function ($r) use (&$actual) {
            if (str_contains($r->url(), '/invoices')) {
                return Http::response([]);
            }
            if ($r->method() === 'GET' && str_contains($r->url(), '/payment-methods')) {
                return $actual === null ? Http::response([], 503) : Http::response([
                    ['paymentMethodId' => 'BTC-LN', 'enabled' => true, 'config' => ['connectionString' => $actual]],
                ]);
            }

            return Http::response([], 503);
        });
        try {
            app(WalletConnectionService::class)->createOrUpdate($store, 'blink', self::NEW, $store->user, 'pending');
            $this->fail('An unknown result must require reconciliation.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('wallet_configuration_attempts', ['store_id' => $store->id, 'status' => 'uncertain']);
        }
        $this->assertSame(self::OLD, Crypt::decryptString($store->walletConnection->encrypted_secret));
        try {
            app(InvoiceService::class)->createInvoice('wallet-store', ['amount' => '1', 'currency' => 'BTC'], 'merchant-key');
            $this->fail('New invoices must be blocked until the wallet is known.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('paused', $e->getMessage());
        }
        $actual = self::NEW;
        // A fresh service instance uses only persisted state, as after a restart.
        $this->assertTrue(app(WalletConnectionService::class)->reconcileWalletUpdate($store->fresh(), $store->user));
        $this->assertDatabaseHas('wallet_configuration_attempts', ['store_id' => $store->id, 'status' => 'confirmed']);
        $this->assertSame(self::NEW, Crypt::decryptString($store->fresh()->walletConnection->encrypted_secret));
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/invoices'));
    }

    public function test_nwc_uses_typed_endpoint_with_merchant_credentials_and_unmodified_uri(): void
    {
        $uri = 'nostr+walletconnect://'.str_repeat('a', 64).'?relay=wss%3A%2F%2Frelay.example&secret='.str_repeat('b', 64);
        Http::fake(['*/nwc/connection' => Http::response(['enabled' => true])]);
        app(LightningService::class)->connectLightningNode('store', 'BTC', 'type=nwc;key='.$uri, 'merchant-key');
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['uri'] === $uri && $r->hasHeader('Authorization', 'Bearer merchant-key'));
        Http::assertSentCount(1);
    }

    public function test_wallet_service_enforces_owner_even_outside_controller(): void
    {
        $store = $this->connectedStore();
        $this->expectException(HttpException::class);
        app(WalletConnectionService::class)->createOrUpdate($store, 'blink', self::NEW, User::factory()->create(), 'pending');
    }

    public function test_archived_unpaid_checkout_is_not_reused_or_replaced_by_pdf_flow(): void
    {
        $store = $this->connectedStore();
        Http::fake(['*/invoices/archived' => Http::response([
            'status' => 'New', 'archived' => true, 'checkoutLink' => 'https://btcpay.test/i/archived',
        ])]);
        $state = app(BusinessDocumentBtcPayService::class)->ephemeralCheckoutState($store, 'archived');
        $this->assertSame('unknown', $state['state']);
        $this->assertNull($state['checkout_link']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_confirmed_rejection_keeps_previous_wallet_without_an_uncertain_hold(): void
    {
        $store = $this->connectedStore();
        Http::fake(function ($r) {
            if ($r->method() === 'PUT') {
                return Http::response(['message' => 'Invalid wallet'], 422);
            }
            if (str_contains($r->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'BTC-LN', 'enabled' => true, 'config' => ['connectionString' => self::OLD]]]);
            }

            return Http::response([]);
        });
        try {
            app(WalletConnectionService::class)->createOrUpdate($store, 'blink', self::NEW, $store->user, 'pending');
            $this->fail('A rejected wallet must not be selected.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('wallet_configuration_attempts', ['store_id' => $store->id, 'status' => 'rejected', 'encrypted_secret' => null]);
        }
        $this->assertSame(self::OLD, Crypt::decryptString($store->fresh()->walletConnection->encrypted_secret));
    }

    public function test_old_configuration_after_timeout_requires_operator_confirmation(): void
    {
        $store = $this->connectedStore();
        WalletConfigurationAttempt::create([
            'store_id' => $store->id, 'connection_type' => 'blink', 'encrypted_secret' => Crypt::encryptString(self::NEW), 'status' => 'uncertain',
        ]);
        Http::fake(['*/payment-methods*' => Http::response([
            ['paymentMethodId' => 'BTC-LN', 'enabled' => true, 'config' => ['connectionString' => self::OLD]],
        ])]);
        $wallets = app(WalletConnectionService::class);
        $this->assertFalse($wallets->reconcileWalletUpdate($store, $store->user));
        $this->assertDatabaseHas('wallet_configuration_attempts', ['store_id' => $store->id, 'status' => 'uncertain']);
        $this->assertTrue($wallets->reconcileWalletUpdate($store, $store->user, confirmRejected: true));
        $this->assertDatabaseHas('wallet_configuration_attempts', ['store_id' => $store->id, 'status' => 'rejected']);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_wallet_write_is_not_retried_after_a_transport_timeout(): void
    {
        $writes = 0;
        Http::fake(function ($r) use (&$writes) {
            if ($r->method() === 'PUT') {
                $writes++;
                throw new ConnectionException('Response lost');
            }

            return Http::response([]);
        });
        try {
            app(LightningService::class)->connectLightningNode('store', 'BTC', self::NEW, 'merchant-key');
            $this->fail('The unknown result must be returned to the journal.');
        } catch (BtcPayException) {
            $this->assertSame(1, $writes);
        }
    }
}
