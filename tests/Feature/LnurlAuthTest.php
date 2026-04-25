<?php

namespace Tests\Feature;

use App\Models\LnurlAuthChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LnurlAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.lnurl_auth.enabled', true);
        Config::set('services.lnurl_auth.domain', 'https://panel.example.com');
    }

    #[Test]
    public function link_challenge_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/lnurl-auth/link-challenge');

        $response->assertStatus(401);
    }

    #[Test]
    public function link_challenge_returns_403_when_lnurl_auth_disabled(): void
    {
        Config::set('services.lnurl_auth.enabled', false);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/lnurl-auth/link-challenge');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'LNURL-auth is not enabled');
    }

    #[Test]
    public function link_challenge_returns_200_with_k1_and_lnurl_when_authenticated(): void
    {
        $user = User::factory()->create(['lightning_public_key' => null]);

        $response = $this->actingAs($user)->postJson('/api/lnurl-auth/link-challenge');

        $response->assertStatus(200)
            ->assertJsonStructure(['k1', 'lnurl', 'qr'])
            ->assertJsonFragment(['qr' => $response->json('lnurl')]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('k1'));
        $this->assertStringContainsString($response->json('k1'), $response->json('lnurl'));
        $this->assertDatabaseHas('lnurl_auth_challenges', [
            'k1' => $response->json('k1'),
            'link_user_id' => $user->id,
            'purpose' => 'link',
        ]);
    }

    #[Test]
    public function link_challenge_returns_400_when_user_already_has_lightning_key(): void
    {
        $user = User::factory()->create(['lightning_public_key' => '02abc123']);

        $response = $this->actingAs($user)->postJson('/api/lnurl-auth/link-challenge');

        $response->assertStatus(400)
            ->assertJsonPath('error', __('auth.lightning_key_already_registered'));
    }

    #[Test]
    public function reveal_confirm_challenge_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/lnurl-auth/reveal-confirm-challenge');

        $response->assertStatus(401);
    }

    #[Test]
    public function reveal_confirm_challenge_returns_403_when_lnurl_auth_disabled(): void
    {
        Config::set('services.lnurl_auth.enabled', false);
        $user = User::factory()->create(['lightning_public_key' => '02key']);

        $response = $this->actingAs($user)->postJson('/api/lnurl-auth/reveal-confirm-challenge');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'LNURL-auth is not enabled');
    }

    #[Test]
    public function reveal_confirm_challenge_returns_400_when_user_has_no_lightning_key(): void
    {
        $user = User::factory()->create(['lightning_public_key' => null]);

        $response = $this->actingAs($user)->postJson('/api/lnurl-auth/reveal-confirm-challenge');

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Lightning login required to confirm via wallet.');
    }

    #[Test]
    public function reveal_confirm_challenge_returns_200_with_k1_and_lnurl_when_user_has_lightning_key(): void
    {
        $user = User::factory()->create(['lightning_public_key' => '02key']);

        $response = $this->actingAs($user)->postJson('/api/lnurl-auth/reveal-confirm-challenge');

        $response->assertStatus(200)
            ->assertJsonStructure(['k1', 'lnurl', 'qr'])
            ->assertJsonFragment(['qr' => $response->json('lnurl')]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('k1'));
        $this->assertDatabaseHas('lnurl_auth_challenges', [
            'k1' => $response->json('k1'),
            'link_user_id' => $user->id,
            'purpose' => 'reveal',
        ]);
    }

    #[Test]
    public function challenge_status_returns_404_for_unknown_k1(): void
    {
        $response = $this->getJson('/api/lnurl-auth/challenge-status/' . str_repeat('a', 64));

        $response->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Challenge not found');
    }

    #[Test]
    public function challenge_status_returns_pending_when_not_consumed(): void
    {
        $challenge = LnurlAuthChallenge::create([
            'k1' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/lnurl-auth/challenge-status/' . $challenge->k1);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'pending');
    }

    #[Test]
    public function challenge_status_returns_expired_when_expired(): void
    {
        $challenge = LnurlAuthChallenge::create([
            'k1' => bin2hex(random_bytes(32)),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/lnurl-auth/challenge-status/' . $challenge->k1);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'expired');
    }

    #[Test]
    public function challenge_status_returns_linked_when_link_challenge_consumed(): void
    {
        $user = User::factory()->create();
        $challenge = LnurlAuthChallenge::create([
            'k1' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes(5),
            'link_user_id' => $user->id,
            'purpose' => 'link',
            'consumed_at' => now(),
        ]);

        $response = $this->getJson('/api/lnurl-auth/challenge-status/' . $challenge->k1);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'linked')
            ->assertJsonPath('user.id', $user->id);
    }

    #[Test]
    public function challenge_status_returns_reveal_confirmed_when_reveal_challenge_consumed(): void
    {
        $user = User::factory()->create();
        $challenge = LnurlAuthChallenge::create([
            'k1' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes(5),
            'link_user_id' => $user->id,
            'purpose' => 'reveal',
            'consumed_at' => now(),
        ]);

        $response = $this->getJson('/api/lnurl-auth/challenge-status/' . $challenge->k1);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'reveal_confirmed');
    }
}
