<?php

namespace Tests\Feature;

use App\Http\Controllers\PasskeyEnvelopeController;
use App\Models\User;
use App\Models\UserPasskeyEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasskeyEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    /** Base64url, 16+ chars - the shape a real WebAuthn credential id has. */
    protected const CREDENTIAL_ID = 'dGVzdC1jcmVkZW50aWFsLWlkLTAx';

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Test laptop',
            'payload' => json_encode(['v' => 1, 'iv' => str_repeat('a', 16), 'ct' => str_repeat('b', 128)]),
            'envelope_version' => 1,
            'transports' => ['internal', 'hybrid'],
        ], $overrides);
    }

    protected function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    #[Test]
    public function the_owner_can_upsert_list_and_delete_an_envelope(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID, $this->payload())
            ->assertOk()
            ->assertJsonPath('data.credential_id', self::CREDENTIAL_ID);

        // Upsert with a new label replaces in place - still one row.
        $this->actingAs($user)
            ->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID, $this->payload(['label' => 'Renamed']))
            ->assertOk();
        $this->assertSame(1, UserPasskeyEnvelope::query()->count());

        $list = $this->actingAs($user)
            ->getJson('/api/account/passkey-envelopes')
            ->assertOk();
        $list->assertJsonPath('data.0.label', 'Renamed');
        // The list must never leak ciphertext.
        $this->assertArrayNotHasKey('payload', $list->json('data.0'));

        $this->actingAs($user)
            ->deleteJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID)
            ->assertOk();
        $this->assertSame(0, UserPasskeyEnvelope::query()->count());
    }

    #[Test]
    public function another_account_cannot_overwrite_or_delete_a_foreign_envelope(): void
    {
        $owner = $this->verifiedUser();
        $attacker = $this->verifiedUser();

        $this->actingAs($owner)
            ->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID, $this->payload())
            ->assertOk();

        $this->actingAs($attacker)
            ->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID, $this->payload(['label' => 'Stolen']))
            ->assertStatus(422);

        $this->actingAs($attacker)
            ->deleteJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID)
            ->assertOk();
        // Delete is scoped to the caller - the owner's envelope survives.
        $this->assertSame(1, UserPasskeyEnvelope::query()->where('user_id', $owner->id)->count());
    }

    #[Test]
    public function the_per_account_envelope_limit_is_enforced(): void
    {
        $user = $this->verifiedUser();

        for ($i = 0; $i < PasskeyEnvelopeController::MAX_ENVELOPES_PER_USER; $i++) {
            $this->actingAs($user)
                ->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID.$i, $this->payload())
                ->assertOk();
        }

        $this->actingAs($user)
            ->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID.'overflow', $this->payload())
            ->assertStatus(422);
    }

    #[Test]
    public function invalid_credential_ids_and_oversized_payloads_are_rejected(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->putJson('/api/account/passkey-envelopes/not%20valid!', $this->payload())
            ->assertStatus(422);

        $this->actingAs($user)
            ->putJson(
                '/api/account/passkey-envelopes/'.self::CREDENTIAL_ID,
                $this->payload(['payload' => str_repeat('x', PasskeyEnvelopeController::MAX_PAYLOAD_BYTES + 1)]),
            )
            ->assertStatus(422);
    }

    #[Test]
    public function crud_requires_authentication(): void
    {
        $this->getJson('/api/account/passkey-envelopes')->assertUnauthorized();
        $this->putJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID, $this->payload())->assertUnauthorized();
        $this->deleteJson('/api/account/passkey-envelopes/'.self::CREDENTIAL_ID)->assertUnauthorized();
    }

    #[Test]
    public function the_login_fetch_returns_ciphertext_only_and_stamps_last_used(): void
    {
        $user = $this->verifiedUser();
        UserPasskeyEnvelope::create([
            'user_id' => $user->id,
            'credential_id' => self::CREDENTIAL_ID,
            'label' => 'Test laptop',
            'payload' => $this->payload()['payload'],
            'envelope_version' => 1,
        ]);

        $response = $this->postJson('/api/auth/passkey/envelope', [
            'credential_id' => self::CREDENTIAL_ID,
        ])->assertOk();

        $this->assertSame($this->payload()['payload'], $response->json('data.payload'));
        // No account information may leak alongside the ciphertext.
        $this->assertSame(['payload', 'envelope_version'], array_keys($response->json('data')));

        $this->assertNotNull(UserPasskeyEnvelope::query()->first()->last_used_at);
    }

    #[Test]
    public function the_login_fetch_is_generic_for_unknown_credentials(): void
    {
        $this->postJson('/api/auth/passkey/envelope', [
            'credential_id' => 'dW5rbm93bi1jcmVkZW50aWFs',
        ])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    }

    #[Test]
    public function the_login_fetch_is_rate_limited(): void
    {
        // Reset limiter hits accumulated by earlier tests (array cache
        // persists for the process).
        $this->app['cache']->flush();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/passkey/envelope', [
                'credential_id' => 'dW5rbm93bi1jcmVkZW50aWFs',
            ])->assertNotFound();
        }

        $this->postJson('/api/auth/passkey/envelope', [
            'credential_id' => 'dW5rbm93bi1jcmVkZW50aWFs',
        ])->assertStatus(429);
    }
}
