<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

    public function test_authenticated_user_can_get_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'user@example.com']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => 'user@example.com', 'name' => 'Test User']);
        $response->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_user_payload_exposes_guest_upgrade_email_only_flag(): void
    {
        config(['guest.upgrade_email_only' => true]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('guest_upgrade_email_only', true);

        config(['guest.upgrade_email_only' => false]);

        $this->getJson('/api/user')
            ->assertJsonPath('guest_upgrade_email_only', false);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => __('messages.profile_updated')]);
        $response->assertJsonPath('user.name', 'New Name');
        $response->assertJsonPath('user.email', 'old@example.com');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('old@example.com', $user->email);
    }

    public function test_profile_update_cannot_change_email(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'name' => 'Me',
            'email' => 'other@example.com',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertSame('me@example.com', $user->email);
    }

    public function test_user_can_set_evolu_relay_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'evolu_relay_url' => 'wss://evolu.satflux.io',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.evolu_relay_url', 'wss://evolu.satflux.io');
        $user->refresh();
        $this->assertSame('wss://evolu.satflux.io', $user->evolu_relay_url);
    }

    public function test_evolu_relay_url_must_be_wss(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'evolu_relay_url' => 'https://evolu.satflux.io',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_clear_evolu_relay_url(): void
    {
        $user = User::factory()->create(['evolu_relay_url' => 'wss://evolu.satflux.io']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'evolu_relay_url' => null,
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertNull($user->evolu_relay_url);
    }

    public function test_relay_default_opt_in_is_false_until_explicitly_changed(): void
    {
        $user = User::factory()->create();
        $user->refresh();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')->assertJsonPath('evolu_relay_add_default', false);
        $this->putJson('/api/user', [
            'evolu_relay_url' => 'wss://relay.example.com',
            'evolu_relay_add_default' => true,
        ])->assertOk()->assertJsonPath('user.evolu_relay_add_default', true);

        $this->assertTrue($user->fresh()->evolu_relay_add_default);
        $this->getJson('/api/user')->assertJsonPath('evolu_relay_add_default', true);

        // Older clients updating only the URL or name preserve this preference.
        $this->putJson('/api/user', ['name' => 'Relay user'])
            ->assertOk()->assertJsonPath('user.evolu_relay_add_default', true);
        $this->putJson('/api/user', ['evolu_relay_url' => null])
            ->assertOk()->assertJsonPath('user.evolu_relay_add_default', true);
        $this->putJson('/api/user', ['evolu_relay_add_default' => false])
            ->assertOk()->assertJsonPath('user.evolu_relay_add_default', false);

        $this->assertFalse($user->fresh()->evolu_relay_add_default);
    }

    #[DataProvider('validRelayOrigins')]
    public function test_relay_origins_are_normalized_without_discarding_components(string $input, string $expected): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user', ['evolu_relay_url' => $input])
            ->assertOk()->assertJsonPath('user.evolu_relay_url', $expected);
        $this->assertSame($expected, $user->fresh()->evolu_relay_url);
    }

    public static function validRelayOrigins(): array
    {
        return [
            'secure origin' => ['wss://relay.example.com', 'wss://relay.example.com'],
            'canonical scheme and default port' => ['WSS://RELAY.EXAMPLE.COM:443/', 'wss://relay.example.com'],
            'custom port' => ['wss://relay.example.com:8443/', 'wss://relay.example.com:8443'],
            'ipv4' => ['wss://127.0.0.1:8443', 'wss://127.0.0.1:8443'],
            'ipv4 trailing dot' => ['wss://127.0.0.1.:8443', 'wss://127.0.0.1.:8443'],
            'hostname trailing dot' => ['wss://relay.example.com.', 'wss://relay.example.com.'],
            'ipv6' => ['wss://[::1]:8443/', 'wss://[::1]:8443'],
        ];
    }

    #[DataProvider('invalidRelayOrigins')]
    public function test_invalid_relay_origins_do_not_change_either_setting(string $input): void
    {
        $user = User::factory()->create(['evolu_relay_url' => 'wss://original.example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/user', [
            'evolu_relay_url' => $input,
            'evolu_relay_add_default' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('evolu_relay_url');

        $this->assertSame('wss://original.example.com', $user->fresh()->evolu_relay_url);
        $this->assertFalse($user->fresh()->evolu_relay_add_default);
    }

    public static function invalidRelayOrigins(): array
    {
        return array_map(fn (string $url) => [$url], [
            'ws://relay.example.com',
            'https://relay.example.com',
            'wss://',
            'wss://user:password@relay.example.com',
            'wss://relay.example.com/path',
            'wss://relay.example.com?key=value',
            'wss://relay.example.com?',
            'wss://relay.example.com#fragment',
            'wss://relay.example.com#',
            'wss://relay.example.com:65536',
            'wss://relay.example.com:0',
            'wss://relay.example.com;script-src *',
            "wss://relay.example.com\nscript-src *",
            'wss://relay.example.com\\@evil.example.com',
            'wss://-invalid.example.com',
            'wss://relay..example.com',
            'wss://[invalid]',
            'wss://127.1',
            'wss://127.1.',
            'wss://2130706433',
            'wss://2130706433.',
            'wss://0177.0.0.1',
            'wss://0177.0.0.1.',
            'wss://0x7f000001',
            'wss://0x7f000001.',
            'wss://'.str_repeat('a', 510).'.example.com',
        ]);
    }

    /** Reject invalid mode values and verify profile writes cannot target another user. */
    public function test_relay_opt_in_requires_a_boolean_and_updates_only_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['evolu_relay_url' => 'wss://other.example.com']);
        Sanctum::actingAs($user);

        foreach ([null, 'true', 'yes', [], 2] as $invalid) {
            $this->putJson('/api/user', ['evolu_relay_add_default' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('evolu_relay_add_default');
        }
        $this->putJson('/api/user', [
            'id' => $other->id,
            'evolu_relay_url' => 'wss://mine.example.com',
            'evolu_relay_add_default' => true,
        ])->assertOk();

        $this->assertTrue($user->fresh()->evolu_relay_add_default);
        $this->assertSame('wss://other.example.com', $other->fresh()->evolu_relay_url);
        $this->assertFalse($other->fresh()->evolu_relay_add_default);
    }

    public function test_relay_settings_update_requires_authentication(): void
    {
        $this->putJson('/api/user', [
            'evolu_relay_url' => 'wss://relay.example.com',
            'evolu_relay_add_default' => true,
        ])->assertUnauthorized();
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('old-password'),
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/password', [
            'current_password' => 'old-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => __('messages.password_updated')]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_guest_upgrade_request_stages_code_without_touching_guest_row(): void
    {
        Notification::fake();
        $guest = $this->makeGuest('guest+abc@guest.satflux.io');
        Sanctum::actingAs($guest);

        $response = $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => '  Upgraded@Satflux.IO  ',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('challenge.email', 'upgraded@satflux.io')
            ->assertJsonPath('challenge.purpose', 'guest_upgrade')
            ->assertJsonPath('challenge.attempts_left', 5)
            ->assertJsonMissingPath('challenge.code_hash');

        $guest->refresh();
        $this->assertTrue((bool) $guest->is_guest);
        $this->assertSame('guest+abc@guest.satflux.io', $guest->email);
        $this->assertTrue(Hash::check('old-password', $guest->password));
        $this->assertNull($guest->privacy_consent_at);

        // The staged challenge is exposed on /api/user so the SPA can resume the code step.
        $this->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('pending_email_challenge.email', 'upgraded@satflux.io')
            ->assertJsonPath('is_guest', true);

        $this->lastEmailCode('upgraded@satflux.io');
    }

    public function test_guest_can_upgrade_with_code_and_then_login_with_new_credentials(): void
    {
        Notification::fake();
        $guest = $this->makeGuest('guest-upgrade@satflux.io');
        Sanctum::actingAs($guest);

        $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'upgraded@satflux.io',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]))->assertStatus(200);

        $confirm = $this->postJson('/api/user/guest/upgrade/confirm', ['code' => $this->lastEmailCode()]);

        $confirm->assertStatus(200);
        $confirm->assertJsonPath('user.email', 'upgraded@satflux.io');
        $confirm->assertJsonPath('user.is_guest', false);
        $confirm->assertJsonPath('user.allows_satflux_email_changes', true);

        $guest->refresh();
        $this->assertSame('upgraded@satflux.io', $guest->email);
        $this->assertFalse((bool) $guest->is_guest);
        $this->assertTrue((bool) $guest->allows_satflux_email_changes);
        // Proven by the code - no second verification round, no limbo.
        $this->assertNotNull($guest->email_verified_at);
        $this->assertNotNull($guest->privacy_consent_at);
        $this->assertNotNull($guest->terms_accepted_at);
        $this->assertTrue(Hash::check('new-secure-password', $guest->password));

        $this->assertTrue(
            auth()->guard('web')->validate([
                'email' => 'upgraded@satflux.io',
                'password' => 'new-secure-password',
            ])
        );

        $this->getJson('/api/user')->assertJsonPath('pending_email_challenge', null);

        // Second tab confirming again is a no-op success.
        $this->postJson('/api/user/guest/upgrade/confirm', ['code' => '000000'])
            ->assertStatus(200)
            ->assertJsonPath('user.is_guest', false);
    }

    public function test_guest_upgrade_wrong_code_reports_attempts_and_keeps_guest(): void
    {
        config(['guest.upgrade_email_only' => true]);
        Notification::fake();
        $guest = $this->makeGuest('guest-wrong@satflux.io');
        Sanctum::actingAs($guest);

        $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'wrong@satflux.io',
        ]))->assertStatus(200);
        $wrong = $this->wrongEmailCode($this->lastEmailCode());

        $this->postJson('/api/user/guest/upgrade/confirm', ['code' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('code', 'code_mismatch')
            ->assertJsonPath('attempts_left', 4);

        $this->assertTrue((bool) $guest->fresh()->is_guest);
    }

    public function test_guest_upgrade_confirm_without_request_is_410(): void
    {
        $guest = $this->makeGuest('guest-none@satflux.io');
        Sanctum::actingAs($guest);

        $this->postJson('/api/user/guest/upgrade/confirm', ['code' => '123456'])
            ->assertStatus(410)
            ->assertJsonPath('code', 'challenge_missing');
    }

    public function test_guest_upgrade_resend_respects_server_cooldown(): void
    {
        config(['guest.upgrade_email_only' => true]);
        Notification::fake();
        $guest = $this->makeGuest('guest-resend@satflux.io');
        Sanctum::actingAs($guest);

        $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'resend@satflux.io',
        ]))->assertStatus(200);

        $this->postJson('/api/user/guest/upgrade/resend')
            ->assertStatus(429)
            ->assertJsonPath('code', 'resend_cooldown')
            ->assertHeader('Retry-After');

        $this->travel(61)->seconds();

        $this->postJson('/api/user/guest/upgrade/resend')
            ->assertStatus(200)
            ->assertJsonPath('challenge.sends_left', 3);

        $this->postJson('/api/user/guest/upgrade/confirm', ['code' => $this->lastEmailCode()])
            ->assertStatus(200)
            ->assertJsonPath('user.is_guest', false);
    }

    public function test_guest_upgrade_fails_when_email_taken_between_request_and_confirm(): void
    {
        config(['guest.upgrade_email_only' => true]);
        Notification::fake();
        $guest = $this->makeGuest('guest-race@satflux.io');
        Sanctum::actingAs($guest);

        $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'race@satflux.io',
        ]))->assertStatus(200);
        $code = $this->lastEmailCode();

        User::factory()->create(['email' => 'race@satflux.io']);

        $this->postJson('/api/user/guest/upgrade/confirm', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $guest->refresh();
        $this->assertTrue((bool) $guest->is_guest);
        $this->assertSame('guest-race@satflux.io', $guest->email);
        // The burnt challenge is gone; the guest has to pick another address.
        $this->getJson('/api/user')->assertJsonPath('pending_email_challenge', null);
    }

    public function test_guest_upgrade_request_rejects_taken_email_upfront(): void
    {
        config(['guest.upgrade_email_only' => true]);
        User::factory()->create(['email' => 'taken@satflux.io']);
        $guest = $this->makeGuest('guest-taken@satflux.io');
        Sanctum::actingAs($guest);

        $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'taken@satflux.io',
        ]))->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_non_guest_cannot_request_guest_upgrade(): void
    {
        config(['guest.upgrade_email_only' => true]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'other@satflux.io',
        ]))->assertStatus(422);
    }

    public function test_legacy_put_guest_upgrade_route_is_gone(): void
    {
        config(['guest.upgrade_email_only' => true]);
        $guest = $this->makeGuest('guest-legacy@satflux.io');
        Sanctum::actingAs($guest);

        $this->putJson('/api/user/guest/upgrade', $this->guestUpgradePayload([
            'method' => 'email',
            'email' => 'legacy@satflux.io',
        ]))->assertStatus(405);
    }

    public function test_guest_upgrade_lightning_method_is_no_longer_supported(): void
    {
        $pk = '02'.str_repeat('ab', 32);
        $guest = User::factory()->guest()->create([
            'lightning_public_key' => $pk,
        ]);
        Sanctum::actingAs($guest);

        $response = $this->postJson('/api/user/guest/upgrade/request', $this->guestUpgradePayload([
            'method' => 'lightning',
            'email' => 'real@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }

    private function makeGuest(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => bcrypt('old-password'),
            'is_guest' => true,
            'email_verified_at' => now(),
            'btcpay_user_id' => null,
            'btcpay_api_key' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function guestUpgradePayload(array $overrides): array
    {
        return array_merge([
            'privacy_consent' => true,
            'terms_accepted' => true,
        ], $overrides);
    }
}
