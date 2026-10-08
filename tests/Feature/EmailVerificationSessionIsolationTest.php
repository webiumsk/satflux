<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailVerificationSessionIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function target(): User
    {
        return User::factory()->unverified()->create([
            'btcpay_user_id' => 'synthetic-merchant',
            'btcpay_api_key' => 'synthetic-merchant-key',
            'last_login_at' => null,
        ]);
    }

    private function verificationUrl(User $user): string
    {
        return str_replace('/auth/verify-email/', '/api/auth/verify-email/', app(EmailVerificationService::class)->signedVerificationUrlForUser($user));
    }

    private function browserSession(?User $user): string
    {
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'array']);
        $this->withHeader('Origin', 'http://localhost')->withCredentials();
        $values = ['verification_sentinel' => 'existing-session', '_token' => 'existing-csrf-token'];
        if ($user) {
            $values[Auth::guard('web')->getName()] = $user->id;
        }
        $this->withSession($values);
        $id = session()->getId();
        $this->withCookie(config('session.cookie'), $id);

        return $id;
    }

    public static function browsers(): iterable
    {
        yield 'anonymous' => [false];
        yield 'signed into another account' => [true];
    }

    #[DataProvider('browsers')]
    public function test_valid_verification_changes_email_state_without_changing_authentication(bool $authenticated): void
    {
        Http::fake();
        $current = $authenticated ? User::factory()->create(['last_login_at' => now()->subDay()]) : null;
        $target = $this->target();
        $beforeLogin = $current?->last_login_at;
        $sessionId = $this->browserSession($current);

        $this->getJson($this->verificationUrl($target))->assertOk()->assertJsonPath('verified', true)->assertJsonMissingPath('user');

        $this->assertTrue($target->fresh()->hasVerifiedEmail());
        $this->assertNull($target->fresh()->last_login_at);
        $this->assertSame($sessionId, session()->getId());
        $this->assertSame('existing-session', session('verification_sentinel'));
        $this->assertSame('existing-csrf-token', session()->token());
        if ($current) {
            $this->assertAuthenticatedAs($current, 'web');
            $this->assertEquals($beforeLogin, $current->fresh()->last_login_at);
            $this->getJson('/api/user')->assertOk()->assertJsonPath('id', $current->id);
        } else {
            $this->assertGuest('web');
            $this->getJson('/api/user')->assertUnauthorized();
        }
        Http::assertNothingSent();
    }

    #[DataProvider('browsers')]
    public function test_repeated_and_already_verified_links_preserve_the_existing_session(bool $authenticated): void
    {
        Http::fake();
        $current = $authenticated ? User::factory()->create() : null;
        $target = $this->target();
        $url = $this->verificationUrl($target);
        $sessionId = $this->browserSession($current);
        $this->getJson($url)->assertOk()->assertJsonMissingPath('user');
        $verifiedAt = $target->fresh()->email_verified_at;
        $this->travel(1)->minutes();
        $this->getJson($url)->assertOk()->assertJsonPath('verified', true)->assertJsonMissingPath('user');
        $this->assertEquals($verifiedAt, $target->fresh()->email_verified_at);
        $this->assertNull($target->fresh()->last_login_at);
        $this->assertSame($sessionId, session()->getId());
        if ($current) {
            $this->assertAuthenticatedAs($current, 'web');
        } else {
            $this->assertGuest('web');
        }
        Http::assertNothingSent();
    }

    public function test_anonymous_verification_requires_a_separate_explicit_login(): void
    {
        Http::fake();
        $target = $this->target();
        $this->browserSession(null);
        $this->getJson($this->verificationUrl($target))->assertOk();
        $this->assertGuest('web');

        $this->postJson('/api/auth/login', ['email' => $target->email, 'password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($target, 'web');
    }

    public function test_verifying_another_account_does_not_verify_the_current_unverified_account(): void
    {
        Http::fake();
        $current = User::factory()->unverified()->create();
        $target = $this->target();
        $this->browserSession($current);
        $this->getJson($this->verificationUrl($target))->assertOk()->assertJsonMissingPath('user');
        $this->assertAuthenticatedAs($current, 'web');
        $this->assertFalse($current->fresh()->hasVerifiedEmail());
        $this->assertTrue($target->fresh()->hasVerifiedEmail());
        $this->getJson('/api/account/passkey-envelopes')->assertForbidden()->assertJsonPath('code', 'email_not_verified');
    }

    public static function invalidLinks(): iterable
    {
        foreach (['signature', 'target-id', 'email-hash', 'registration', 'extra-account', 'expired'] as $variant) {
            foreach ([false, true] as $authenticated) {
                yield $variant.($authenticated ? ' / authenticated' : ' / anonymous') => [$variant, $authenticated];
            }
        }
    }

    #[DataProvider('invalidLinks')]
    public function test_rejected_links_cannot_verify_or_change_session_identity(string $variant, bool $authenticated): void
    {
        Http::fake();
        $target = $this->target();
        $other = User::factory()->unverified()->create();
        $current = $authenticated ? User::factory()->create() : null;
        $url = $this->verificationUrl($target);
        $url = match ($variant) {
            'signature' => preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('0', 64), $url),
            'target-id' => str_replace('/'.$target->id.'/', '/'.$other->id.'/', $url),
            'email-hash' => str_replace(sha1($target->email), sha1($other->email), $url),
            'registration' => preg_replace('/registration=[a-f0-9]+/', 'registration='.str_repeat('0', 64), $url),
            'extra-account' => $url.'&user_id='.$other->id,
            default => $url,
        };
        if ($variant === 'expired') {
            $this->travel(61)->minutes();
        }
        $sessionId = $this->browserSession($current);
        $this->getJson($url)->assertForbidden();
        $this->assertFalse($target->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
        $this->assertSame($sessionId, session()->getId());
        if ($current) {
            $this->assertAuthenticatedAs($current, 'web');
        } else {
            $this->assertGuest('web');
        }
        Http::assertNothingSent();
    }
}
