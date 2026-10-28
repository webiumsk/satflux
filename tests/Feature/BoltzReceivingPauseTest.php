<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConnection;
use App\Services\Boltz\BoltzAvailabilityService;
use App\Services\BtcPay\BoltzService;
use App\Services\BtcPay\LightningService;
use App\Services\StoreProvisioningService;
use App\Services\StoreResponseFormatter;
use App\Services\WalletConnectionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BoltzReceivingPauseTest extends TestCase
{
    use RefreshDatabase;

    private const DESCRIPTOR = 'ct(slip77(synthetic-test-blinding-key),elwpkh(synthetic-test-public-key))';

    public function test_new_boltz_receiving_is_disabled_by_default_for_both_wallet_brands(): void
    {
        $availability = app(BoltzAvailabilityService::class);
        $this->assertFalse($availability->receivingEnabled());
        $store = Store::factory()->withAquaBoltz()->create();

        $this->assertSame([
            'active' => true,
            'code' => 'boltz_receiving_unavailable',
            'new_connections_available' => false,
        ], $availability->payload($store));

        // A merchant moving away stops receiving the provider-specific warning.
        $store->wallet_type = 'nwc';
        $this->assertFalse($availability->isAffected($store));
    }

    public function test_descriptor_submission_and_direct_configure_fail_before_wallet_or_http_changes(): void
    {
        Http::fake();
        [$user, $store] = $this->ownedStore();
        $before = $this->fingerprint($store->fresh());

        $this->actingAs($user)->postJson("/api/stores/{$store->id}/wallet-connection", [
            'type' => 'aqua_descriptor', 'secret' => self::DESCRIPTOR,
            'fallback_lightning_address' => 'merchant@example.test',
        ])->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);

        $this->postJson("/api/stores/{$store->id}/wallet-connection/configure", [
            'connection_string' => self::DESCRIPTOR,
        ])->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);

        $this->assertSame($before, $this->fingerprint($store->fresh()));
        $this->assertDatabaseCount('wallet_connections', 0);
        $this->assertDatabaseCount('wallet_configuration_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_a_boltz_store_cannot_be_provisioned_or_selected_even_without_a_descriptor(): void
    {
        Http::fake();
        [$user, $store] = $this->ownedStore();

        $this->actingAs(User::factory()->create())->postJson('/api/stores', [
            'name' => 'Synthetic store', 'default_currency' => 'EUR',
            'timezone' => 'Europe/Prague', 'wallet_type' => 'aqua_boltz',
        ])->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);

        $this->actingAs($user)->patchJson("/api/stores/{$store->id}/wallet-type", ['wallet_type' => 'aqua_boltz'])
            ->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);

        $this->assertDatabaseCount('stores', 1);
        $this->assertNull($store->fresh()->wallet_type);
        Http::assertNothingSent();
    }

    public function test_samrock_creation_and_completion_do_not_change_existing_wallet_or_make_upstream_calls(): void
    {
        Http::fake();
        [$user, $store] = $this->ownedStore('aqua_boltz');
        $connection = WalletConnection::factory()->aquaBoltz()->connected()->create([
            'store_id' => $store->id, 'submitted_by_user_id' => $user->id,
        ]);
        $before = $this->fingerprint($connection->fresh());

        $this->actingAs($user)->postJson("/api/stores/{$store->id}/samrock/otps", ['btc' => true, 'btcln' => false])
            ->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);
        $this->postJson("/api/stores/{$store->id}/samrock/complete", [
            'otp' => 'synthetic-existing-otp', 'fallback_lightning_address' => 'merchant@example.test',
        ])->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);

        $this->assertSame($before, $this->fingerprint($connection->fresh()));
        $this->assertSame('aqua_boltz', $store->fresh()->wallet_type);
        Http::assertNothingSent();
    }

    public function test_existing_samrock_status_and_cancellation_remain_available_but_activation_qr_is_paused(): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        [$user, $store] = $this->ownedStore('aqua_boltz');
        Http::fake([
            'https://btcpay.test/api/v1/stores/*/samrock/otps/existing/qr' => Http::response('synthetic-qr', 200),
            'https://btcpay.test/api/v1/stores/*/samrock/otps/existing' => Http::response([
                'status' => 'pending',
                'expiresAt' => '2026-10-28T12:00:00Z',
                'setupUrl' => 'https://btcpay.test/samrock/synthetic-activation',
            ], 200),
        ]);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/samrock/otps/existing")
            ->assertOk()->assertJsonPath('data.setup_url', null)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.expires_at', '2026-10-28T12:00:00Z');
        $this->getJson("/api/stores/{$store->id}/samrock/otps/existing/qr")
            ->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);
        $this->deleteJson("/api/stores/{$store->id}/samrock/otps/existing")->assertOk();
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'wallet_type' => 'aqua_boltz']);
    }

    public function test_pause_overrides_a_previous_ready_cache_without_network_requests(): void
    {
        Http::fake();
        [$user, $store] = $this->ownedStore('aqua_boltz');
        Cache::put("boltz:readiness:{$store->id}", ['status' => 'ready', 'reasons' => []], 600);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/boltz/readiness")
            ->assertOk()->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.reasons', ['provider_suspended'])
            ->assertJsonPath('data.lightning_active', null);
        Http::assertNothingSent();
    }

    public function test_store_warning_survives_btcpay_failure_and_disappears_after_a_supported_connection_change(): void
    {
        [$user, $store] = $this->ownedStore('aqua_boltz');
        $formatter = app(StoreResponseFormatter::class);
        $this->assertTrue($formatter->fromLocal($store)['boltz_service_alert']['active']);
        $this->assertTrue($formatter->fromBtcPay(['name' => 'Synthetic'], $store)['boltz_service_alert']['active']);

        $store->update(['wallet_type' => 'lnaddress']);
        $this->assertFalse($formatter->fromLocal($store->fresh())['boltz_service_alert']['active']);
    }

    public function test_other_tenants_cannot_use_pause_or_recovery_routes_to_access_a_store(): void
    {
        Http::fake();
        [, $store] = $this->ownedStore('aqua_boltz');
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/stores/{$store->id}/samrock/otps", [])->assertForbidden();
        $this->getJson("/api/stores/{$store->id}/samrock/otps/existing")->assertForbidden();
        $this->getJson("/api/stores/{$store->id}/boltz/readiness")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_support_cannot_mark_a_pending_boltz_connection_as_working(): void
    {
        Http::fake();
        [$user, $store] = $this->ownedStore('aqua_boltz');
        $connection = WalletConnection::factory()->aquaBoltz()->needsSupport()->create([
            'store_id' => $store->id, 'submitted_by_user_id' => $user->id,
        ]);

        $this->actingAs(User::factory()->support()->create())
            ->putJson("/api/support/wallet-connections/{$connection->id}/mark-connected")
            ->assertStatus(503)->assertJsonPath('code', BoltzAvailabilityService::CODE);
        $this->assertSame('needs_support', $connection->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_queued_connection_retry_cannot_reactivate_a_paused_boltz_wallet(): void
    {
        Http::fake();
        Notification::fake();
        [$user, $store] = $this->ownedStore('aqua_boltz');
        $connection = WalletConnection::factory()->aquaBoltz()->needsSupport()->create([
            'store_id' => $store->id, 'submitted_by_user_id' => $user->id,
        ]);
        $before = $this->fingerprint($connection->fresh());

        $this->artisan('wallet-connections:attempt-config')->assertSuccessful();
        $this->assertSame($before, $this->fingerprint($connection->fresh()));
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_service_entry_points_cannot_bypass_the_pause(): void
    {
        Http::fake();
        [$user, $store] = $this->ownedStore();
        $attempts = [
            fn () => app(StoreProvisioningService::class)->create($user, ['wallet_type' => 'aqua_boltz']),
            fn () => app(StoreProvisioningService::class)->create($user, ['wallet_type' => 'nwc', 'connection_string' => self::DESCRIPTOR]),
            fn () => app(WalletConnectionService::class)->createOrUpdate($store, 'aqua_descriptor', self::DESCRIPTOR, $user),
            fn () => app(WalletConnectionService::class)->markSamRockConnected($store, $user),
            fn () => app(BoltzService::class)->importDescriptorAndEnableSetup('synthetic-store', 'synthetic-wallet', self::DESCRIPTOR),
            fn () => app(LightningService::class)->connectLightningNode('synthetic-store', 'BTC', self::DESCRIPTOR),
            fn () => app(LightningService::class)->connectLightningNode('synthetic-store', 'BTC', 'server=https://boltz.test; type=BOLTZ;walletId=synthetic'),
            fn () => app(LightningService::class)->testConnection('synthetic-store', 'BTC', self::DESCRIPTOR),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Paused provider accepted new configuration.');
            } catch (HttpResponseException $exception) {
                $this->assertSame(503, $exception->getResponse()->getStatusCode());
            }
        }

        $this->assertDatabaseCount('stores', 1);
        $this->assertDatabaseCount('wallet_connections', 0);
        Http::assertNothingSent();
    }

    private function fingerprint(Model $model): string
    {
        return hash('sha256', serialize($model->getAttributes()));
    }

    /** @return array{User, Store} */
    private function ownedStore(?string $walletType = null): array
    {
        $user = User::factory()->create();

        return [$user, Store::factory()->create(['user_id' => $user->id, 'wallet_type' => $walletType])];
    }
}
