<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\CashuWalletUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;

class CashuEnableOnlyUpdateTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

    private array $current = [
        'storeId' => 'toggle-store', 'mintUrl' => 'https://mint.example/Bitcoin', 'unit' => 'sat',
        'lightningAddress' => 'Receiver@example.com', 'enabled' => false,
        'trustedMintUrls' => null, 'maxMeltFeeReserveSats' => null, 'maxMeltFeeReservePercentOfMinted' => null,
    ];

    private bool $losePutResponse = false;

    private bool $rejectPut = false;

    private function store(): Store
    {
        Notification::fake();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $user = User::factory()->create([
            'btcpay_api_key' => 'merchant-key', 'guest_recovery_public_key' => str_repeat('a', 64),
            'guest_recovery_enrolled_at' => now(),
        ]);

        return Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'toggle-store', 'wallet_type' => 'cashu']);
    }

    private function fakeProvider(): void
    {
        Http::fake(function (Request $request) {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer merchant-key'));
            if ($request->method() === 'PUT' && str_ends_with($request->url(), '/settings')) {
                if ($this->rejectPut) {
                    return Http::response(['message' => 'Outstanding Cashu settlements'], 409);
                }
                $this->current = [...$this->current, ...$request->data()];
                if ($this->losePutResponse) {
                    throw new ConnectionException('Synthetic lost response');
                }

                return Http::response($this->current);
            }
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/settings')) {
                return Http::response($this->current);
            }
            if ($request->method() === 'GET' && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/payment-methods')) {
                return Http::response([
                    ['paymentMethodId' => 'CASHU', 'enabled' => $this->current['enabled']],
                    ['paymentMethodId' => 'BTC-LN', 'enabled' => true],
                    ['paymentMethodId' => 'BTC-LNURL', 'enabled' => true],
                ]);
            }
            if (str_ends_with($request->url(), '/safety')) {
                return Http::response(['safeToChangeWallet' => false, 'unresolvedPayments' => 1, 'unresolvedChange' => 0]);
            }
            throw new \RuntimeException('Unexpected synthetic request.');
        });
    }

    private function payload(): array
    {
        return ['mint_url' => $this->current['mintUrl'], 'lightning_address' => $this->current['lightningAddress'], 'enabled' => true];
    }

    public static function units(): array
    {
        return [['sat'], ['usd']];
    }

    #[DataProvider('units')]
    public function test_only_reenabling_preserves_full_target_and_lightning_methods_despite_unresolved_funds(string $unit): void
    {
        $store = $this->store();
        $this->current['unit'] = $unit;
        $before = $this->current;
        $grant = $this->grantWalletChange($store->user, $store);
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertOk();
        $this->assertSame([...$before, 'enabled' => true], $this->current);
        $this->assertSame('cashu', $store->fresh()->wallet_type);
        $this->assertNotNull($grant->fresh()->consumed_at);
        $this->assertSame('confirmed', WalletConfigurationAttempt::sole()->status);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' || str_ends_with($r->url(), '/safety') || str_contains($r->url(), '/invoices'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['unit'] === $unit
            && array_key_exists('trustedMintUrls', $r->data()) && array_key_exists('maxMeltFeeReserveSats', $r->data()));
    }

    public static function changedRisk(): array
    {
        return [
            'mint path case' => [['mint_url' => 'https://mint.example/bitcoin']],
            'payout localpart case' => [['lightning_address' => 'receiver@example.com']],
            'unit' => [['unit' => 'usd']],
            'explicit null unit' => [['unit' => null]],
            'trust' => [['trusted_mint_urls' => 'https://different.example']],
            'absolute fee' => [['max_melt_fee_reserve_sats' => 10]],
            'percentage fee' => [['max_melt_fee_reserve_percent_of_minted' => 2]],
        ];
    }

    #[DataProvider('changedRisk')]
    public function test_reenable_combined_with_any_risk_change_keeps_outstanding_fund_guard(array $change): void
    {
        $store = $this->store();
        $grant = $this->grantWalletChange($store->user, $store);
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", [...$this->payload(), ...$change])->assertUnprocessable();
        $this->assertFalse($this->current['enabled']);
        $this->assertNull($grant->fresh()->consumed_at);
        $this->assertDatabaseCount('wallet_configuration_attempts', 0);
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public static function invalidSnapshots(): array
    {
        return [
            'different store' => [['storeId' => 'another-store']],
            'invalid trust shape' => [['trustedMintUrls' => ['https://mint.example']]],
            'numeric string cap' => [['maxMeltFeeReserveSats' => '10']],
            'percentage out of bounds' => [['maxMeltFeeReservePercentOfMinted' => 101]],
            'unknown unit' => [['unit' => 'other']],
        ];
    }

    #[DataProvider('invalidSnapshots')]
    public function test_malformed_current_settings_never_enable_the_exception(array $change): void
    {
        $store = $this->store();
        $this->grantWalletChange($store->user, $store);
        $this->current = [...$this->current, ...$change];
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertUnprocessable();
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_lost_enable_response_recovers_by_reads_without_removing_lightning_or_losing_grant(): void
    {
        $store = $this->store();
        $grant = $this->grantWalletChange($store->user, $store);
        $this->losePutResponse = true;
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertServerError();
        $attempt = WalletConfigurationAttempt::sole();
        $this->assertSame('uncertain', $attempt->status);
        $this->assertTrue(json_decode(Crypt::decryptString($attempt->encrypted_secret), true)['enabled_only']);
        $this->assertNull($grant->fresh()->consumed_at);
        $writes = count(Http::recorded(fn (Request $r) => $r->method() !== 'GET'));
        $this->assertTrue(app(CashuWalletUpdateService::class)->reconcile($store, $store->user, $attempt));
        $this->assertSame($writes, count(Http::recorded(fn (Request $r) => $r->method() !== 'GET')));
        $this->assertNotNull($grant->fresh()->consumed_at);
        $this->assertSame('confirmed', $attempt->fresh()->status);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_older_provider_rejection_keeps_disabled_state_and_grant(): void
    {
        $store = $this->store();
        $grant = $this->grantWalletChange($store->user, $store);
        $this->rejectPut = true;
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertServerError();
        $this->assertFalse($this->current['enabled']);
        $this->assertNull($grant->fresh()->consumed_at);
        $this->assertSame('rejected', WalletConfigurationAttempt::sole()->status);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_enable_only_does_not_bypass_the_existing_email_confirmation(): void
    {
        $store = $this->store();
        Http::fake();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertStatus(409);
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_incomplete_current_settings_keep_the_fund_guard(): void
    {
        $store = $this->store();
        $grant = $this->grantWalletChange($store->user, $store);
        unset($this->current['trustedMintUrls']);
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertUnprocessable();
        $this->assertNull($grant->fresh()->consumed_at);
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_large_integer_fee_cap_changes_are_not_lost_to_float_comparison(): void
    {
        $store = $this->store();
        $this->grantWalletChange($store->user, $store);
        $this->current['maxMeltFeeReserveSats'] = PHP_INT_MAX - 1;
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", [
            ...$this->payload(), 'max_melt_fee_reserve_sats' => PHP_INT_MAX,
        ])->assertUnprocessable();
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_enable_does_not_bypass_pending_configuration_or_foreign_ownership(): void
    {
        $store = $this->store();
        $this->grantWalletChange($store->user, $store);
        WalletConfigurationAttempt::create([
            'store_id' => $store->id, 'connection_type' => 'cashu', 'status' => 'uncertain',
            'encrypted_secret' => 'synthetic-opaque-intent',
        ]);
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertUnprocessable();
        $this->actingAs(User::factory()->create())->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertForbidden();
        $this->assertSame('synthetic-opaque-intent', WalletConfigurationAttempt::sole()->encrypted_secret);
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_enable_reconciliation_cannot_change_a_store_that_now_uses_lightning(): void
    {
        $store = $this->store();
        $grant = $this->grantWalletChange($store->user, $store);
        $this->losePutResponse = true;
        $this->fakeProvider();
        $this->actingAs($store->user)->putJson("/api/stores/{$store->id}/cashu/settings", $this->payload())->assertServerError();
        $attempt = WalletConfigurationAttempt::sole();
        $store->update(['wallet_type' => 'lnaddress']);
        $writes = count(Http::recorded(fn (Request $r) => $r->method() !== 'GET'));
        $this->assertFalse(app(CashuWalletUpdateService::class)->reconcile($store->fresh(), $store->user, $attempt));
        $this->assertSame('lnaddress', $store->fresh()->wallet_type);
        $this->assertSame('uncertain', $attempt->fresh()->status);
        $this->assertNull($grant->fresh()->consumed_at);
        $this->assertSame($writes, count(Http::recorded(fn (Request $r) => $r->method() !== 'GET')));
    }
}
