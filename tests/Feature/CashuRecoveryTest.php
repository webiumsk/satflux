<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashuRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function setupStore(bool $fallback = false): Store
    {
        $user = User::factory()->create(['btcpay_api_key' => 'recovery-merchant-key', 'password' => Hash::make('recovery-password')]);
        Sanctum::actingAs($user);

        return Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'btcpay-recovery-store',
            'wallet_type' => $fallback ? 'lnaddress' : 'cashu',
            'cashu_fallback_enabled' => $fallback,
        ]);
    }

    private function payload(): array
    {
        return [
            'mint_url' => 'https://original-mint.example/Bitcoin',
            'lightning_address' => 'merchant@linky.example',
            'confirmed_original' => true,
            'password' => 'recovery-password',
        ];
    }

    public function test_recovery_forwards_only_confirmed_original_data_using_merchant_key_and_audits_success(): void
    {
        $store = $this->setupStore();
        $before = $store->fresh()->getAttributes();
        Http::fake(['*/plugins/cashumelt/payments/original-quote/confirm-original' => Http::response(['confirmed' => true])]);
        $this->postJson("/api/stores/{$store->id}/cashu/payments/original-quote/confirm-original", $this->payload())
            ->assertOk()->assertJsonPath('data.confirmed', true);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer recovery-merchant-key')
            && $request->data() === ['mintUrl' => 'https://original-mint.example/Bitcoin', 'lightningAddress' => 'merchant@linky.example', 'confirmedOriginal' => true]);
        Http::assertSentCount(1);
        $this->assertSame($before, $store->fresh()->getAttributes());
        $audit = AuditLog::where('action', 'cashu.original_settings_confirmed')->sole();
        $this->assertSame($store->id, $audit->target_id);
        $this->assertStringNotContainsString('recovery-password', json_encode($audit->toArray()));
    }

    public function test_recovery_is_available_for_a_parallel_cashu_fallback(): void
    {
        $store = $this->setupStore(true);
        Http::fake(['*/confirm-original' => Http::response(['confirmed' => true])]);
        $this->postJson("/api/stores/{$store->id}/cashu/payments/quote/confirm-original", $this->payload())->assertOk();
    }

    public function test_recovery_phrase_account_confirms_using_its_authenticated_session(): void
    {
        $store = $this->setupStore();
        $store->user->update(['guest_recovery_public_key' => str_repeat('a', 64)]);
        Sanctum::actingAs($store->user->fresh());
        Http::fake(['*/confirm-original' => Http::response(['confirmed' => true])]);
        $payload = $this->payload();
        unset($payload['password']);
        $this->postJson("/api/stores/{$store->id}/cashu/payments/quote/confirm-original", $payload)->assertOk();
    }

    public function test_missing_consent_invalid_mint_and_wrong_password_never_reach_btcpay(): void
    {
        $store = $this->setupStore();
        Http::fake();
        foreach (['confirmed_original' => false, 'mint_url' => 'http://mint.example', 'password' => 'wrong'] as $field => $invalid) {
            $this->postJson("/api/stores/{$store->id}/cashu/payments/quote/confirm-original", array_replace($this->payload(), [$field => $invalid]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        Http::assertNothingSent();
    }

    public function test_recovery_cannot_access_another_merchants_store(): void
    {
        $this->setupStore();
        $foreign = Store::factory()->create(['wallet_type' => 'cashu']);
        Http::fake();
        $this->postJson("/api/stores/{$foreign->id}/cashu/payments/quote/confirm-original", $this->payload())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_recovery_requires_cashu_or_enabled_fallback(): void
    {
        $store = $this->setupStore();
        $store->update(['wallet_type' => 'lnaddress']);
        Http::fake();
        $this->postJson("/api/stores/{$store->id}/cashu/payments/quote/confirm-original", $this->payload())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_plugin_rejection_or_missing_recovery_endpoint_does_not_trigger_retry(): void
    {
        $store = $this->setupStore();
        foreach ([400, 404] as $status) {
            Http::fake(['*/confirm-original' => Http::response(['error' => 'Original mint does not match the invoice'], $status)]);
            $this->postJson("/api/stores/{$store->id}/cashu/payments/quote/confirm-original", $this->payload())
                ->assertUnprocessable()->assertJsonValidationErrors('recovery');
            Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/retry'));
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'cashu.original_settings_confirmed']);
    }

    public function test_payment_list_exposes_original_settings_requirement(): void
    {
        $store = $this->setupStore();
        Http::fake(['*/payments*' => Http::response(['items' => [[
            'quoteId' => 'q', 'invoiceId' => 'i', 'settlementState' => 'MANUAL_REVIEW', 'originalSettingsRequired' => true,
        ]]])]);
        $this->getJson("/api/stores/{$store->id}/cashu/payments")
            ->assertOk()->assertJsonPath('data.items.0.original_settings_required', true);
    }
}
