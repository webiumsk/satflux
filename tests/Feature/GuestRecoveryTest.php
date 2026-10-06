<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Services\GuestProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuestRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_signup_rejects_a_different_key_in_an_existing_session(): void
    {
        $user = User::factory()->guest()->create(['guest_recovery_public_key' => str_repeat('a', 64)]);
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/auth/guest', [
            'recovery_public_key' => str_repeat('b', 64),
        ])->assertStatus(409)->assertJsonPath('code', 'already_authenticated');

        $this->assertSame(str_repeat('a', 64), $user->fresh()->guest_recovery_public_key);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('stores', ['id' => $store->id]);
    }

    public function test_guest_enrollment_can_be_retried_with_the_same_key(): void
    {
        $user = User::factory()->guest()->create(['guest_recovery_public_key' => str_repeat('a', 64)]);
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/auth/guest', [
            'recovery_public_key' => str_repeat('A', 64),
        ])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('store_id', $store->id);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('stores', 1);
    }

    public function test_guest_signup_in_a_stale_password_session_does_not_attach_the_new_key(): void
    {
        $user = User::factory()->create(['guest_recovery_public_key' => null]);
        $this->assertTrue($user->canUsePasswordLogin());

        $this->actingAs($user)->postJson('/api/auth/guest', [
            'recovery_public_key' => str_repeat('b', 64),
        ])->assertStatus(409)->assertJsonPath('code', 'already_authenticated');

        $this->assertNull($user->fresh()->guest_recovery_public_key);
        $this->assertTrue($user->fresh()->canUsePasswordLogin());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account.recovery_key_enrolled']);
    }

    public function test_signed_in_account_enrolls_a_recovery_key_through_the_account_endpoint(): void
    {
        $user = User::factory()->create(['guest_recovery_public_key' => null]);

        $this->actingAs($user)->postJson('/api/account/recovery-key', [
            'recovery_public_key' => str_repeat('A', 64),
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->assertSame(str_repeat('a', 64), $user->fresh()->guest_recovery_public_key);
        $this->assertFalse($user->fresh()->canUsePasswordLogin());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.recovery_key_enrolled', 'user_id' => $user->id]);

        // Retry with the same key is idempotent and not audited twice.
        $this->postJson('/api/account/recovery-key', ['recovery_public_key' => str_repeat('a', 64)])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'account.recovery_key_enrolled')->count());
    }

    public function test_account_endpoint_refuses_to_replace_an_enrolled_key(): void
    {
        $user = User::factory()->guest()->create(['guest_recovery_public_key' => str_repeat('a', 64)]);

        $this->actingAs($user)->postJson('/api/account/recovery-key', [
            'recovery_public_key' => str_repeat('b', 64),
        ])->assertUnprocessable()->assertJsonValidationErrors('recovery_public_key');

        $this->assertSame(str_repeat('a', 64), $user->fresh()->guest_recovery_public_key);
    }

    public function test_account_endpoint_requires_a_session(): void
    {
        $this->postJson('/api/account/recovery-key', [
            'recovery_public_key' => str_repeat('a', 64),
        ])->assertUnauthorized();
    }

    public function test_legacy_account_can_enroll_a_key_but_cannot_replace_it_using_a_stale_user_model(): void
    {
        $user = User::factory()->create(['guest_recovery_public_key' => null]);
        $service = app(GuestProvisioningService::class);
        $service->attachRecoveryKey($user, str_repeat('a', 64));

        $this->expectException(ValidationException::class);
        $service->attachRecoveryKey($user, str_repeat('b', 64));
    }

    public function test_guest_recovery_restore_accepts_valid_signature_and_returns_store_id(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension is required for guest recovery tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKeyHex = strtolower(bin2hex($publicKey));

        $guest = User::factory()->create([
            'is_guest' => true,
            'guest_recovery_public_key' => $publicKeyHex,
            'guest_recovery_enrolled_at' => now(),
        ]);
        $store = Store::factory()->create(['user_id' => $guest->id]);

        $challengeResponse = $this->postJson('/api/auth/guest/recovery/challenge');
        $challengeResponse->assertStatus(200);
        $challengeId = $challengeResponse->json('data.challenge_id');
        $nonce = $challengeResponse->json('data.nonce');

        $message = "satflux:guest-recovery:v1|{$challengeId}|{$nonce}";
        $signatureHex = bin2hex(sodium_crypto_sign_detached($message, $secretKey));

        $restoreResponse = $this->postJson('/api/auth/guest/recovery', [
            'challenge_id' => $challengeId,
            'recovery_public_key' => $publicKeyHex,
            'signature' => $signatureHex,
        ]);

        $restoreResponse->assertStatus(200);
        $restoreResponse->assertJsonPath('user.id', $guest->id);
        $restoreResponse->assertJsonPath('store_id', $store->id);
    }

    public function test_guest_recovery_restore_rejects_invalid_signature(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('Sodium extension is required for guest recovery tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $publicKeyHex = strtolower(bin2hex(sodium_crypto_sign_publickey($keypair)));

        User::factory()->create([
            'is_guest' => true,
            'guest_recovery_public_key' => $publicKeyHex,
            'guest_recovery_enrolled_at' => now(),
        ]);

        $challengeResponse = $this->postJson('/api/auth/guest/recovery/challenge');
        $challengeResponse->assertStatus(200);
        $challengeId = $challengeResponse->json('data.challenge_id');

        $restoreResponse = $this->postJson('/api/auth/guest/recovery', [
            'challenge_id' => $challengeId,
            'recovery_public_key' => $publicKeyHex,
            'signature' => str_repeat('ab', 64),
        ]);

        $restoreResponse->assertStatus(422);
        $restoreResponse->assertJsonPath('message', 'Invalid signature.');
    }
}
