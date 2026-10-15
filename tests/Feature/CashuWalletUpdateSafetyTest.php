<?php

namespace Tests\Feature;

use App\Exceptions\CashuWalletSafetyException;
use App\Exceptions\WalletChangeConfirmationException;
use App\Models\EmailVerificationChallenge;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\Auth\EmailCodeChallengeService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\CashuWalletUpdateService;
use App\Services\WalletChangeConfirmationGuard;
use App\Services\WalletConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;

class CashuWalletUpdateSafetyTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

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

    private function configuredStore(array $attributes = []): Store
    {
        $store = $this->store();
        $store->user->forceFill([
            'guest_recovery_public_key' => str_repeat('a', 64),
            'guest_recovery_enrolled_at' => now(),
        ])->save();
        $store->update(['wallet_type' => 'cashu', ...$attributes]);

        return $store;
    }

    private function payload(): array
    {
        return ['mint_url' => $this->settings['mintUrl'], 'lightning_address' => $this->settings['lightningAddress'], 'enabled' => true];
    }

    private function fakeSuccessfulSettings(bool $alreadyMatches = false): void
    {
        $settingsWritten = $alreadyMatches;
        Http::fake(function ($request) use (&$settingsWritten) {
            if ($request->method() === 'PUT') {
                $settingsWritten = true;
            }
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => true]);
            }
            if (str_contains($request->url(), '/invoices')) {
                return Http::response([]);
            }
            if (str_contains($request->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true]]);
            }

            return Http::response($settingsWritten ? $this->settings : array_merge($this->settings, ['lightningAddress' => 'previous@example.com']));
        });
    }

    public function test_existing_cashu_without_connection_requires_email_confirmation_before_any_provider_write(): void
    {
        $store = $this->configuredStore();
        Http::fake();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertStatus(409)->assertJsonPath('code', 'wallet_change_confirmation_required');
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $this->assertSame(0, WalletConfigurationAttempt::count());
    }

    public function test_existing_cashu_request_and_confirm_email_grants_one_update_without_a_wallet_secret(): void
    {
        $store = $this->configuredStore();
        $this->fakeSuccessfulSettings();
        $this->actingAs($store->user)->getJson("/api/stores/{$store->id}/cashu/settings")
            ->assertOk()->assertJsonPath('data.change_confirmation.required', true);
        $this->postJson("/api/stores/{$store->id}/wallet-connection/change/request")
            ->assertOk()->assertJsonPath('data.required', true);
        $this->postJson("/api/stores/{$store->id}/wallet-connection/change/confirm", ['code' => $this->lastEmailCode()])
            ->assertOk()->assertJsonPath('data.type', 'cashu')->assertJsonPath('data.secret', null);
        $this->assertNotNull(EmailVerificationChallenge::sole()->verified_at);
        $this->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertOk()->assertJsonPath('data.change_confirmation.granted_until', null);
        $this->assertNotNull(EmailVerificationChallenge::sole()->consumed_at);
        $this->assertSame('confirmed', WalletConfigurationAttempt::sole()->status);
        $writes = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() !== 'GET')->count();
        $this->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertStatus(409);
        $this->assertSame($writes, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() !== 'GET')->count());
    }

    public function test_expired_cashu_grant_and_other_store_grant_cannot_write(): void
    {
        $store = $this->configuredStore();
        Http::fake();
        $grant = $this->grantWalletChange($store->user, $store);
        $grant->update(['verified_at' => now()->subMinutes(16)]);
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertStatus(409);
        $grant->update(['verified_at' => now(), 'payload' => ['store_id' => 'another-local-store']]);
        $this->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertStatus(409);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_guest_cannot_edit_existing_cashu_but_initial_setup_stays_ungated(): void
    {
        $store = $this->configuredStore();
        $store->user->update(['is_guest' => true]);
        $this->fakeSuccessfulSettings();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertStatus(403)->assertJsonPath('code', 'guest_upgrade_required');
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $store->update(['wallet_type' => null]);
        $response = $this->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload());
        $this->assertSame(200, $response->status(), json_encode($response->json()));
    }

    public function test_unresolved_cashu_funds_refuse_update_without_consuming_confirmed_grant(): void
    {
        $store = $this->configuredStore();
        $grant = $this->grantWalletChange($store->user, $store);
        Http::fake(['*' => Http::response(['safeToChangeWallet' => false, 'unresolvedPayments' => 1])]);
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors(['cashu']);
        $this->assertNull($grant->fresh()->consumed_at);
        $this->assertSame(0, WalletConfigurationAttempt::count());
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_direct_cashu_update_service_also_requires_confirmation(): void
    {
        $store = $this->configuredStore();
        Http::fake();
        try {
            app(CashuWalletUpdateService::class)->update($store, $store->user, $this->settings, false);
            $this->fail('Direct service calls must not bypass the email grant.');
        } catch (WalletChangeConfirmationException) {
            Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        }
    }

    public function test_uncertain_cashu_edit_preserves_grant_and_read_only_reconciliation_consumes_it(): void
    {
        $store = $this->configuredStore();
        $grant = $this->grantWalletChange($store->user, $store);
        $applied = false;
        $writes = 0;
        Http::fake(function ($request) use (&$applied, &$writes) {
            if ($request->method() === 'PUT') {
                $applied = true;
                $writes++;
                throw new ConnectionException('Synthetic lost response after remote commit');
            }
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => true]);
            }
            if (str_contains($request->url(), '/invoices')) {
                return Http::response([]);
            }
            if (str_contains($request->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true]]);
            }

            return Http::response($applied ? $this->settings : array_merge($this->settings, ['lightningAddress' => 'previous@example.com']));
        });
        try {
            app(CashuWalletUpdateService::class)->update($store, $store->user, $this->settings, false);
            $this->fail('Unknown remote result must remain unresolved.');
        } catch (BtcPayException) {
            $this->assertNull($grant->fresh()->consumed_at);
        }
        $attempt = WalletConfigurationAttempt::sole();
        $this->assertSame('uncertain', $attempt->status);
        $writesBeforeReconciliation = $writes;
        $this->assertGreaterThan(0, $writesBeforeReconciliation);
        $this->assertTrue(app(CashuWalletUpdateService::class)->reconcile($store, $store->user, $attempt));
        $this->assertNotNull($grant->fresh()->consumed_at);
        $this->assertSame($writesBeforeReconciliation, $writes);
    }

    public function test_unchanged_cashu_save_is_read_only_even_with_outstanding_funds_and_keeps_the_grant(): void
    {
        $store = $this->configuredStore();
        $grant = $this->grantWalletChange($store->user, $store);
        $before = $store->fresh()->getRawOriginal();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => false, 'unresolvedPayments' => 2, 'unresolvedChange' => 1]);
            }
            if (str_contains($request->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true]]);
            }

            return Http::response([...$this->settings, 'unit' => 'sat', 'trustedMintUrls' => null,
                'maxMeltFeeReserveSats' => null, 'maxMeltFeeReservePercentOfMinted' => null]);
        });
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", [...$this->payload(), 'unit' => 'sat', 'trusted_mint_urls' => null,
            'max_melt_fee_reserve_sats' => null, 'max_melt_fee_reserve_percent_of_minted' => null])
            ->assertOk()->assertJsonPath('data.lightning_address', 'merchant@example.com');
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $this->assertSame($before, $store->fresh()->getRawOriginal());
        $this->assertSame(0, WalletConfigurationAttempt::count());
        $this->assertNull($grant->fresh()->consumed_at);
    }

    public function test_unchanged_settings_cannot_bypass_an_unresolved_configuration_journal(): void
    {
        $store = $this->configuredStore();
        $this->grantWalletChange($store->user, $store);
        $attempt = WalletConfigurationAttempt::create([
            'store_id' => $store->id, 'connection_type' => 'cashu', 'status' => 'uncertain',
            'encrypted_secret' => 'synthetic-opaque-journal',
        ]);
        Http::fake();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors(['cashu']);
        Http::assertNothingSent();
        $this->assertSame('synthetic-opaque-journal', $attempt->fresh()->encrypted_secret);
    }

    public function test_malformed_cashu_safety_response_is_unavailable_and_never_changes_wallet(): void
    {
        $store = $this->configuredStore();
        $this->grantWalletChange($store->user, $store);
        Http::fake(['*' => Http::response(['safeToChangeWallet' => 'true'])]);
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertUnprocessable()->assertJsonPath('code', 'cashu_safety_unavailable');
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $this->assertSame(0, WalletConfigurationAttempt::count());
    }

    public function test_legacy_cashu_journal_reconciles_without_consuming_a_new_unrelated_grant(): void
    {
        $store = $this->configuredStore();
        $grant = $this->grantWalletChange($store->user, $store);
        $attempt = WalletConfigurationAttempt::create([
            'store_id' => $store->id, 'connection_type' => 'cashu', 'status' => 'uncertain',
            'encrypted_secret' => Crypt::encryptString(json_encode($this->settings, JSON_THROW_ON_ERROR)),
        ]);
        $this->fakeSuccessfulSettings(true);
        $this->assertTrue(app(CashuWalletUpdateService::class)->reconcile($store, $store->user, $attempt));
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertNull($grant->fresh()->consumed_at);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_cashu_to_lightning_service_reasserts_email_confirmation_inside_its_lock(): void
    {
        $store = $this->configuredStore();
        Http::fake();
        try {
            app(WalletConnectionService::class)->createOrUpdate($store, 'lnaddress', 'merchant@coinos.io', $store->user, 'pending');
            $this->fail('Cashu to Lightning must not bypass email confirmation through the service.');
        } catch (WalletChangeConfirmationException) {
            $this->assertSame('cashu', $store->fresh()->wallet_type);
            Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        }
    }

    public function test_standalone_fallback_destination_change_keeps_outstanding_funds_and_grant_untouched(): void
    {
        $store = $this->configuredStore(['wallet_type' => 'lnaddress', 'cashu_fallback_enabled' => true, 'cashu_fallback_address' => 'previous@example.com']);
        $grant = $this->grantWalletChange($store->user, $store);
        Http::fake(fn ($request) => str_ends_with($request->url(), '/safety')
            ? Http::response(['safeToChangeWallet' => false, 'unresolvedChange' => 1])
            : Http::response([...$this->settings, 'lightningAddress' => 'previous@example.com']));
        try {
            app(WalletConnectionService::class)->configureCashuFallback($store, 'new@example.com', 'merchant-key', $store->user);
            $this->fail('Fallback changes must not bypass unresolved ecash.');
        } catch (CashuWalletSafetyException $e) {
            $this->assertSame('cashu_outstanding_payments', $e->errorCode);
            $this->assertSame('previous@example.com', $store->fresh()->cashu_fallback_address);
            $this->assertNull($grant->fresh()->consumed_at);
            Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        }
    }

    public function test_confirmed_cashu_write_does_not_consume_a_newer_grant_issued_while_the_provider_writes(): void
    {
        $store = $this->configuredStore();
        $original = $this->grantWalletChange($store->user, $store);
        $newer = null;
        $applied = false;
        Http::fake(function ($request) use ($store, $original, &$newer, &$applied) {
            if ($request->method() === 'PUT') {
                $applied = true;
                $original->update(['superseded_at' => now()]);
                $newer = $this->grantWalletChange($store->user, $store);
            }
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => true]);
            }
            if (str_contains($request->url(), '/invoices')) {
                return Http::response([]);
            }
            if (str_contains($request->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true]]);
            }

            return Http::response($applied ? $this->settings : [...$this->settings, 'lightningAddress' => 'previous@example.com']);
        });
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertOk();
        $this->assertNotNull($newer);
        $this->assertNull($newer->fresh()->consumed_at);
        $this->assertSame('confirmed', WalletConfigurationAttempt::sole()->status);
        $this->assertNull(WalletConfigurationAttempt::sole()->encrypted_secret);
    }

    public function test_cashu_email_code_cannot_confirm_a_different_store_without_a_connection_row(): void
    {
        $store = $this->configuredStore();
        $other = Store::factory()->create(['user_id' => $store->user_id, 'wallet_type' => 'cashu']);
        Http::fake();
        $this->actingAs($store->user)->postJson("/api/stores/{$store->id}/wallet-connection/change/request")->assertOk();
        $this->postJson("/api/stores/{$other->id}/wallet-connection/change/confirm", ['code' => $this->lastEmailCode()])
            ->assertStatus(410)->assertJsonPath('code', 'challenge_missing');
        $this->putJson("/api/stores/{$other->id}/cashu/settings", $this->payload())->assertStatus(409);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_cashu_email_resend_rotates_the_code_and_preserves_the_existing_confirmation_contract(): void
    {
        $store = $this->configuredStore();
        Http::fake(['*' => Http::response($this->settings)]);
        $this->actingAs($store->user)->postJson("/api/stores/{$store->id}/wallet-connection/change/request")->assertOk();
        $previous = $this->lastEmailCode();
        $this->travel(61)->seconds();
        $this->postJson("/api/stores/{$store->id}/wallet-connection/change/resend")->assertOk();
        $this->postJson("/api/stores/{$store->id}/wallet-connection/change/confirm", ['code' => $previous])
            ->assertUnprocessable()->assertJsonPath('code', 'code_mismatch');
        $this->postJson("/api/stores/{$store->id}/wallet-connection/change/confirm", ['code' => $this->lastEmailCode()])
            ->assertOk()->assertJsonPath('data.type', 'cashu');
    }

    public static function firstSetupCases(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    #[DataProvider('firstSetupCases')]
    public function test_preselected_cashu_first_setup_uses_authoritative_empty_configuration_without_a_code(bool $guest, bool $missing): void
    {
        $store = $this->configuredStore();
        $store->user->update(['is_guest' => $guest]);
        $applied = false;
        Http::fake(function ($request) use ($store, $missing, &$applied) {
            if ($request->method() === 'PUT') {
                $applied = true;
            }
            if (str_ends_with($request->url(), '/settings')) {
                if ($applied) {
                    return Http::response($this->settings);
                }

                return $missing ? Http::response([], 404) : Http::response([
                    'storeId' => $store->btcpay_store_id, 'mintUrl' => null, 'unit' => 'sat', 'lightningAddress' => null,
                    'enabled' => false, 'trustedMintUrls' => null, 'maxMeltFeeReserveSats' => null, 'maxMeltFeeReservePercentOfMinted' => null,
                ]);
            }
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => true]);
            }
            if (str_contains($request->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'CASHU', 'enabled' => true]]);
            }

            return Http::response([]);
        });
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertOk();
        $this->assertSame(0, EmailVerificationChallenge::count());
        $this->assertSame('confirmed', WalletConfigurationAttempt::sole()->status);
    }

    public function test_known_cashu_configuration_cannot_lose_its_email_gate_when_remote_settings_disappear(): void
    {
        $store = $this->configuredStore();
        WalletConfigurationAttempt::create(['store_id' => $store->id, 'connection_type' => 'cashu', 'status' => 'confirmed']);
        Http::fake(['*' => Http::response([], 404)]);
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertStatus(409)->assertJsonPath('code', 'wallet_change_confirmation_required');
        Http::assertNothingSent();
    }

    public static function unavailableSafetyCases(): array
    {
        return [['missing'], ['unavailable'], ['transport']];
    }

    #[DataProvider('unavailableSafetyCases')]
    public function test_unavailable_safety_fails_closed_with_a_specific_code_and_preserves_the_grant(string $failure): void
    {
        $store = $this->configuredStore();
        $grant = $this->grantWalletChange($store->user, $store);
        Http::fake(function ($request) use ($failure) {
            if (str_ends_with($request->url(), '/safety')) {
                if ($failure === 'transport') {
                    throw new ConnectionException('Synthetic unavailable safety transport');
                }

                return Http::response([], $failure === 'missing' ? 404 : 503);
            }

            return Http::response([...$this->settings, 'lightningAddress' => 'previous@example.com']);
        });
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())
            ->assertUnprocessable()->assertJsonPath('code', 'cashu_safety_unavailable')->assertJsonValidationErrors(['cashu']);
        $this->assertNull($grant->fresh()->consumed_at);
        $this->assertSame(0, WalletConfigurationAttempt::count());
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_grant_persistence_failure_keeps_lightning_intent_for_read_only_reconciliation(): void
    {
        $store = $this->configuredStore();
        $grant = $this->grantWalletChange($store->user, $store);
        $secret = 'type=lnaddress;ln-address=merchant@coinos.io;server=https://coinos.io;';
        Http::fake(function ($request) use ($secret) {
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => true]);
            }
            if (str_contains($request->url(), '/invoices')) {
                return Http::response([]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/payment-methods')) {
                return Http::response([['paymentMethodId' => 'BTC-LN', 'enabled' => true, 'config' => ['connectionString' => $secret]]]);
            }

            return Http::response($this->settings);
        });
        $guard = \Mockery::mock(WalletChangeConfirmationGuard::class, [app(EmailCodeChallengeService::class)])->makePartial();
        $guard->shouldReceive('consumeCapturedGrant')->once()->andThrow(new \RuntimeException('Synthetic grant persistence failure'));
        $this->app->instance(WalletChangeConfirmationGuard::class, $guard);
        try {
            app(WalletConnectionService::class)->createOrUpdate($store, 'lnaddress', $secret, $store->user, 'pending');
            $this->fail('The synthetic persistence failure must escape.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic grant persistence failure', $e->getMessage());
        }
        $attempt = WalletConfigurationAttempt::sole();
        $this->assertSame('uncertain', $attempt->status);
        $this->assertNotNull($attempt->encrypted_secret);
        $this->assertSame($secret, json_decode(Crypt::decryptString($attempt->encrypted_secret), true)['secret']);
        $this->assertNull($grant->fresh()->consumed_at);
        $this->app->instance(WalletChangeConfirmationGuard::class, new WalletChangeConfirmationGuard(app(EmailCodeChallengeService::class)));
        $writes = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() !== 'GET')->count();
        $this->assertTrue(app(WalletConnectionService::class)->reconcileWalletUpdate($store->fresh(), $store->user));
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->encrypted_secret);
        $this->assertNotNull($grant->fresh()->consumed_at);
        $this->assertSame($writes, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() !== 'GET')->count());
    }
}
