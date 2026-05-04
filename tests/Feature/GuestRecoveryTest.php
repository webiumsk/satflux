<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRecoveryTest extends TestCase
{
    use RefreshDatabase;

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

