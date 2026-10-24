<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\CashuWalletUpdateService;
use App\Services\LightningAddressCapabilityService;
use App\Services\LnAddressLud21Prober;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;
use Tests\Unit\Bolt11Test;

class LightningAddressCapabilityTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

    private function merchantStore(array $attributes = []): Store
    {
        return Store::factory()->create([
            'user_id' => User::factory()->create(['btcpay_api_key' => 'synthetic-merchant-key'])->id,
            ...$attributes,
        ]);
    }

    private function probeResult(string $reason): void
    {
        $this->mock(LnAddressLud21Prober::class)->shouldReceive('probe')
            ->andReturn(['lud21' => $reason === 'ok', 'reason' => $reason]);
    }

    private function cashuSettings(array $settings = [], string $safety = 'safe'): void
    {
        $cashu = $this->mock(CashuService::class);
        $cashu->shouldReceive('getSettings')->andReturn($settings);
        $cashu->shouldReceive('getWalletSafety')->andReturn(['status' => $safety]);
    }

    public function test_missing_verify_offers_the_existing_mint_without_changing_the_wallet(): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->cashuSettings(['mintUrl' => 'https://merchant-mint.example/Bitcoin/']);

        $this->actingAs($store->user)->postJson("/api/stores/{$store->id}/wallet-connection/lnaddress-probe", ['address' => 'migrated@coinos.io'])
            ->assertOk()->assertJsonPath('data.outcome', 'unsupported')
            ->assertJsonPath('data.code', 'lud21_unsupported')
            ->assertJsonPath('data.alternatives.0.available', true)
            ->assertJsonPath('data.alternatives.0.requires_consent', true)
            ->assertJsonPath('data.alternatives.0.mint_url', 'https://merchant-mint.example/Bitcoin');

        $this->assertDatabaseMissing('wallet_connections', ['store_id' => $store->id]);
        $this->assertNull($store->fresh()->wallet_type);
        Http::assertNothingSent();
    }

    public static function missingSettingsSafety(): array
    {
        return [
            'unconfigured but supported' => [['safeToChangeWallet' => true], 200, true, null],
            'missing plugin' => [[], 404, false, 'cashu_safety_unavailable'],
            'unreachable safety' => [[], 503, false, 'cashu_safety_unavailable'],
            'malformed safety' => [['safeToChangeWallet' => 'true'], 200, false, 'cashu_safety_unavailable'],
            'outstanding settlements' => [['safeToChangeWallet' => false], 200, false, 'cashu_outstanding_payments'],
        ];
    }

    #[DataProvider('missingSettingsSafety')]
    public function test_missing_settings_use_the_default_only_with_proven_plugin_safety(array $safety, int $status, bool $available, ?string $reason): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.cashu.default_mint_url' => 'https://operator-approved.example/mint']);
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        Http::fake([
            '*/plugins/cashumelt/settings' => Http::response([], 404),
            '*/plugins/cashumelt/safety' => Http::response($safety, $status),
        ]);

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertSame($available, $decision['alternatives'][0]['available']);
        $this->assertSame($reason, $decision['alternatives'][0]['blocked_reason']);
        $this->assertSame('https://operator-approved.example/mint', $decision['alternatives'][0]['mint_url']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/safety')
            && $request->hasHeader('Authorization', 'Bearer synthetic-merchant-key'));
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_other_settings_errors_never_offer_the_default_mint(): void
    {
        config(['services.cashu.default_mint_url' => 'https://operator-approved.example/mint']);
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $cashu = $this->mock(CashuService::class);
        $cashu->shouldReceive('getSettings')->andThrow(new BtcPayException('Synthetic settings failure', 503));
        $cashu->shouldNotReceive('getWalletSafety');

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertFalse($decision['alternatives'][0]['available']);
        $this->assertSame('cashu_settings_unavailable', $decision['alternatives'][0]['blocked_reason']);
    }

    public function test_repeated_presentation_reuses_invoice_scan_briefly_but_rechecks_cashu_safety(): void
    {
        $store = $this->merchantStore(['wallet_type' => 'blink']);
        $this->probeResult('no_verify');
        $cashu = $this->mock(CashuService::class);
        $cashu->shouldReceive('getSettings')->times(3)->andReturn(['mintUrl' => 'https://merchant-mint.example']);
        $cashu->shouldReceive('getWalletSafety')->times(3)->andReturn(['status' => 'safe']);
        Http::fake(['*/invoices*' => Http::response([])]);
        $capabilities = app(LightningAddressCapabilityService::class);

        $this->assertTrue($capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        $this->assertTrue($capabilities->evaluate($store, 'another@coinos.io')['alternatives'][0]['available']);
        Http::assertSentCount(1);
        $this->travel(11)->seconds();
        $this->assertTrue($capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        Http::assertSentCount(2);
        $this->travelBack();
    }

    public function test_advisory_invoice_scan_is_scoped_to_store_and_merchant_credentials(): void
    {
        $store = $this->merchantStore(['wallet_type' => 'blink']);
        $this->probeResult('no_verify');
        $this->cashuSettings(['mintUrl' => 'https://merchant-mint.example']);
        Http::fake(['*/invoices*' => Http::response([])]);
        $capabilities = app(LightningAddressCapabilityService::class);

        $this->assertTrue($capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        $store->user->update(['btcpay_api_key' => 'rotated-synthetic-merchant-key']);
        $this->assertTrue($capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        $other = Store::factory()->create(['user_id' => $store->user_id, 'wallet_type' => 'blink']);
        $this->assertTrue($capabilities->evaluate($other, 'migrated@coinos.io')['alternatives'][0]['available']);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer rotated-synthetic-merchant-key'));
    }

    public function test_failed_advisory_invoice_scan_is_not_cached(): void
    {
        $store = $this->merchantStore(['wallet_type' => 'blink']);
        $this->probeResult('no_verify');
        $this->cashuSettings(['mintUrl' => 'https://merchant-mint.example']);
        Http::fake(['*/invoices*' => Http::sequence()->push([['status' => 'Processing']])->push([])]);
        $capabilities = app(LightningAddressCapabilityService::class);

        $first = $capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0];
        $this->assertFalse($first['available']);
        $this->assertSame('wallet_change_unavailable', $first['blocked_reason']);
        $this->assertTrue($capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        Http::assertSentCount(2);
    }

    public function test_fresh_evaluation_bypasses_a_cached_successful_invoice_scan(): void
    {
        $store = $this->merchantStore(['wallet_type' => 'blink']);
        $this->probeResult('no_verify');
        $this->cashuSettings(['mintUrl' => 'https://merchant-mint.example']);
        Http::fake(['*/invoices*' => Http::sequence()->push([])->push([['status' => 'Processing']])]);
        $capabilities = app(LightningAddressCapabilityService::class);

        $this->assertTrue($capabilities->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        $decision = $capabilities->evaluate($store, 'migrated@coinos.io', fresh: true);
        $this->assertFalse($decision['alternatives'][0]['available']);
        $this->assertSame('wallet_change_unavailable', $decision['alternatives'][0]['blocked_reason']);
        Http::assertSentCount(2);
    }

    public function test_cached_presentation_cannot_authorize_cashu_save_after_an_invoice_appears(): void
    {
        $store = $this->merchantStore(['wallet_type' => 'blink']);
        $this->grantWalletChange($store->user, $store);
        $this->probeResult('no_verify');
        $settings = ['mintUrl' => 'https://merchant-mint.example', 'lightningAddress' => 'migrated@coinos.io', 'enabled' => true];
        $cashu = $this->mock(CashuService::class);
        $cashu->shouldReceive('getSettings')->once()->andReturn($settings);
        $cashu->shouldReceive('getWalletSafety')->once()->andReturn(['status' => 'safe']);
        $cashu->shouldReceive('assertNoUnresolvedSettlements')->once();
        $cashu->shouldNotReceive('saveSettings');
        Http::fake(['*/invoices*' => Http::sequence()->push([])->push([['status' => 'Processing']])]);

        $this->assertTrue(app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io')['alternatives'][0]['available']);
        try {
            app(CashuWalletUpdateService::class)->update($store, $store->user, $settings, true);
            $this->fail('An advisory cannot authorize changing a wallet after a new invoice appears.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still being monitored', $e->getMessage());
        }
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        $this->assertDatabaseMissing('wallet_configuration_attempts', ['store_id' => $store->id]);
        $this->assertSame('blink', $store->fresh()->wallet_type);
    }

    public static function safetyStates(): array
    {
        return [['blocked', 'cashu_outstanding_payments'], ['unavailable', 'cashu_safety_unavailable']];
    }

    #[DataProvider('safetyStates')]
    public function test_unknown_or_outstanding_settlements_prevent_the_cashu_suggestion(string $safety, string $reason): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->cashuSettings(['mintUrl' => 'https://merchant-mint.example'], $safety);

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertFalse($decision['alternatives'][0]['available']);
        $this->assertSame($reason, $decision['alternatives'][0]['blocked_reason']);
    }

    public function test_configured_default_is_used_only_when_no_mint_was_selected(): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->cashuSettings();
        config(['services.cashu.default_mint_url' => 'https://operator-approved.example/mint']);

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertTrue($decision['alternatives'][0]['available']);
        $this->assertSame('https://operator-approved.example/mint', $decision['alternatives'][0]['mint_url']);
    }

    public function test_invalid_selected_mint_is_not_replaced_by_the_default(): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->cashuSettings(['mintUrl' => 'http://unapproved.example']);

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertFalse($decision['alternatives'][0]['available']);
        $this->assertSame('cashu_mint_unavailable', $decision['alternatives'][0]['blocked_reason']);
    }

    public function test_unresolved_wallet_write_never_offers_a_second_configuration(): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->mock(CashuService::class)->shouldNotReceive('getSettings');
        WalletConfigurationAttempt::create(['store_id' => $store->id, 'connection_type' => 'lnaddress', 'status' => 'uncertain']);

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertSame('wallet_reconciliation_required', $decision['alternatives'][0]['blocked_reason']);
        $this->assertFalse($decision['alternatives'][0]['available']);
    }

    public static function inconclusiveProbes(): array
    {
        return [['unreachable', 'unavailable'], ['invalid_verify', 'invalid'], ['invalid_lnurlp', 'invalid']];
    }

    #[DataProvider('inconclusiveProbes')]
    public function test_direct_save_rejects_inconclusive_results_without_mutation(string $reason, string $outcome): void
    {
        $store = $this->merchantStore();
        $this->probeResult($reason);
        $this->mock(CashuService::class)->shouldNotReceive('getSettings');

        $this->actingAs($store->user)->postJson("/api/stores/{$store->id}/wallet-connection", ['type' => 'lnaddress', 'secret' => 'migrated@coinos.io'])
            ->assertStatus(422)->assertJsonPath('outcome', $outcome)->assertJsonPath('alternatives', []);
        $this->assertDatabaseMissing('wallet_connections', ['store_id' => $store->id]);
        $this->assertDatabaseMissing('wallet_configuration_attempts', ['store_id' => $store->id]);
        $this->assertNull($store->fresh()->wallet_type);
        Http::assertNothingSent();
    }

    public function test_direct_save_rechecks_a_previously_supported_address_before_mutation(): void
    {
        $store = $this->merchantStore();
        $prober = $this->mock(LnAddressLud21Prober::class);
        $prober->shouldReceive('probe')->with('migrated@coinos.io', false)->once()->andReturn(['lud21' => true, 'reason' => 'ok']);
        $prober->shouldReceive('probe')->with('migrated@coinos.io', true)->once()->andReturn(['lud21' => false, 'reason' => 'no_verify']);
        $this->cashuSettings(['mintUrl' => 'https://merchant-mint.example']);

        $this->actingAs($store->user)->postJson("/api/stores/{$store->id}/wallet-connection/lnaddress-probe", ['address' => 'migrated@coinos.io'])
            ->assertOk()->assertJsonPath('data.lud21', true);
        $this->postJson("/api/stores/{$store->id}/wallet-connection", ['type' => 'lnaddress', 'secret' => 'migrated@coinos.io'])
            ->assertStatus(422)->assertJsonPath('code', 'lud21_unsupported')->assertJsonPath('alternatives.0.available', true);
        $this->assertDatabaseMissing('wallet_connections', ['store_id' => $store->id]);
        $this->assertDatabaseMissing('wallet_configuration_attempts', ['store_id' => $store->id]);
        Http::assertNothingSent();
    }

    public static function legacyAddressTypes(): array
    {
        return [['blink', 'migrated@blink.sv'], ['blitz', 'migrated@blitzwalletapp.com'], ['flash', 'migrated@flashapp.me']];
    }

    #[DataProvider('legacyAddressTypes')]
    public function test_legacy_address_types_cannot_bypass_the_capability_gate(string $type, string $address): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->cashuSettings();

        $this->actingAs($store->user)->postJson("/api/stores/{$store->id}/wallet-connection", ['type' => $type, 'secret' => $address])
            ->assertStatus(422)->assertJsonPath('code', 'lud21_unsupported');
        $this->assertDatabaseMissing('wallet_connections', ['store_id' => $store->id]);
        Http::assertNothingSent();
    }

    public function test_same_domain_addresses_are_checked_separately_and_malformed_invoices_do_not_offer_cashu(): void
    {
        Cache::flush();
        $this->travelTo(now()->setTimestamp(1496314658));
        $prober = new class extends LnAddressLud21Prober
        {
            protected function getJson(string $url): ?array
            {
                if (str_contains($url, '/.well-known/')) {
                    $name = basename((string) parse_url($url, PHP_URL_PATH));

                    return ['tag' => 'payRequest', 'callback' => 'https://coinos.io/pay/'.$name, 'minSendable' => 250000000, 'maxSendable' => 250000000];
                }
                if (str_contains($url, '/verify/')) {
                    return ['status' => 'OK', 'settled' => false, 'pr' => Bolt11Test::SPEC_COFFEE];
                }
                if (str_contains($url, '/legacy?')) {
                    return ['pr' => Bolt11Test::SPEC_COFFEE, 'verify' => 'https://coinos.io/verify/legacy'];
                }

                return ['pr' => str_contains($url, '/malformed?') ? 'not-a-bolt11' : Bolt11Test::SPEC_COFFEE];
            }
        };

        $this->assertSame('ok', $prober->probe('legacy@coinos.io')['reason']);
        $this->assertSame('no_verify', $prober->probe('migrated@coinos.io')['reason']);
        $this->assertSame('invalid_lnurlp', $prober->probe('malformed@coinos.io')['reason']);
        $this->travelBack();
    }
}
