<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\LightningAddressCapabilityService;
use App\Services\LnAddressLud21Prober;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Bolt11Test;

class LightningAddressCapabilityTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_missing_plugin_is_an_explicit_unavailable_alternative(): void
    {
        $store = $this->merchantStore();
        $this->probeResult('no_verify');
        $this->mock(CashuService::class)->shouldReceive('getSettings')
            ->andThrow(new BtcPayException('Synthetic missing plugin', 404));

        $decision = app(LightningAddressCapabilityService::class)->evaluate($store, 'migrated@coinos.io');
        $this->assertFalse($decision['alternatives'][0]['available']);
        $this->assertSame('cashu_plugin_unavailable', $decision['alternatives'][0]['blocked_reason']);
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
