<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Services\GuestProvisioningService;
use Illuminate\Auth\Events\Validated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecoveryEnrollmentRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'array']);
    }

    /** Send actual encrypted browser cookies with a fresh authentication guard per request. */
    private function request(string $method, string $uri, array &$cookies, array $body = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        session()->flush();
        foreach (app('cookie')->getQueuedCookies() as $cookie) {
            app('cookie')->unqueue($cookie->getName(), $cookie->getPath());
        }
        $response = $this->call($method, 'http://localhost'.$uri, [], $cookies, [], [
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body, JSON_THROW_ON_ERROR));
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) {
                unset($cookies[$cookie->getName()]);
            } else {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    private function login(User $user): array
    {
        $cookies = [];
        $this->request('POST', '/api/auth/login', $cookies, [
            'email' => $user->email, 'password' => 'password', 'remember' => true,
        ])->assertOk();
        $this->assertArrayHasKey(Auth::guard('web')->getRecallerName(), $cookies);

        return $cookies;
    }

    private function enroll(User $user, array &$cookies, string $key): void
    {
        $this->request('POST', '/api/account/recovery-key', $cookies, [
            'expected_user_id' => $user->id, 'recovery_public_key' => $key,
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public static function protectedPaths(): iterable
    {
        yield 'stateful API' => [false];
        yield 'authenticated web route' => [true];
    }

    #[DataProvider('protectedPaths')]
    public function test_enrollment_revokes_other_password_sessions_and_remember_cookies(bool $web): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $enroller = $this->login($user);
        $otherBrowser = $this->login($user);
        $recaller = Auth::guard('web')->getRecallerName();
        $oldRememberOnly = [$recaller => $otherBrowser[$recaller]];
        $oldSessionOnly = [config('session.cookie') => $otherBrowser[config('session.cookie')]];
        $this->request('GET', '/api/user', $oldSessionOnly)->assertOk()->assertJsonPath('id', $user->id);
        $oldPassword = $user->fresh()->password;
        $oldRemember = $user->fresh()->remember_token;
        $this->enroll($user, $enroller, str_repeat('a', 64));

        $this->assertNotSame($oldPassword, $user->fresh()->password);
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertFalse(Hash::check('password', $user->fresh()->password));
        $this->request('GET', '/api/user', $enroller)->assertOk()
            ->assertJsonPath('id', $user->id)->assertJsonPath('can_use_password_login', false);
        $path = $web ? '/stores/'.$store->id.'/apps' : '/api/user';
        $this->request('GET', $path, $oldSessionOnly)->assertUnauthorized();
        $this->request('GET', $path, $oldRememberOnly)->assertUnauthorized();
        $this->request('GET', $path, $otherBrowser)->assertUnauthorized();
        $this->request('GET', '/api/user', $enroller)->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_enrollment_retries_preserve_credentials_and_the_enrolling_session(): void
    {
        $user = User::factory()->create();
        $cookies = $this->login($user);
        $sessionId = session()->getId();
        $csrf = session()->token();
        $this->enroll($user, $cookies, str_repeat('a', 64));
        $this->assertSame($sessionId, session()->getId());
        $this->assertSame($csrf, session()->token());
        $password = $user->fresh()->password;
        $rememberToken = $user->fresh()->remember_token;
        $this->enroll($user, $cookies, str_repeat('A', 64));
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($rememberToken, $user->fresh()->remember_token);
        $this->assertSame($sessionId, session()->getId());
        $this->assertSame($csrf, session()->token());
        $this->assertSame(1, AuditLog::where('action', 'account.recovery_key_enrolled')->count());
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $user->id);
    }

    #[DataProvider('protectedPaths')]
    public function test_historical_enrollment_without_credential_rotation_cannot_recall_password_cookie(bool $web): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $cookies = $this->login($user);
        $recaller = Auth::guard('web')->getRecallerName();
        $rememberOnly = [$recaller => $cookies[$recaller]];
        // Simulate enrollment by a deployed version that retained the old credentials.
        $user->update(['guest_recovery_public_key' => str_repeat('a', 64)]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->request('GET', $web ? '/stores/'.$store->id.'/apps' : '/api/user', $rememberOnly)
            ->assertUnauthorized();
    }

    public function test_unenrolled_account_still_supports_password_and_remember_login(): void
    {
        $user = User::factory()->create();
        $cookies = $this->login($user);
        $recaller = Auth::guard('web')->getRecallerName();
        $rememberOnly = [$recaller => $cookies[$recaller]];
        $this->request('GET', '/api/user', $rememberOnly)->assertOk()
            ->assertJsonPath('id', $user->id)->assertJsonPath('can_use_password_login', true);
        $this->assertTrue(Auth::guard('web')->viaRemember());
    }

    public function test_enrollment_does_not_revoke_another_accounts_password_session(): void
    {
        $enroller = User::factory()->create();
        $other = User::factory()->create();
        $enrollerCookies = $this->login($enroller);
        $otherCookies = $this->login($other);
        $this->enroll($enroller, $enrollerCookies, str_repeat('a', 64));
        $this->request('GET', '/api/user', $otherCookies)->assertOk()
            ->assertJsonPath('id', $other->id)->assertJsonPath('can_use_password_login', true);
    }

    public function test_retired_password_is_rejected_but_signed_recovery_restores_the_account(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $keypair = sodium_crypto_sign_keypair();
        $key = bin2hex(sodium_crypto_sign_publickey($keypair));
        $cookies = $this->login($user);
        $this->enroll($user, $cookies, $key);
        $anonymous = [];
        $this->request('POST', '/api/auth/login', $anonymous, ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->request('GET', '/api/user', $anonymous)->assertUnauthorized();
        $challenge = $this->request('POST', '/api/auth/guest/recovery/challenge', $anonymous)->assertOk();
        $id = $challenge->json('data.challenge_id');
        $nonce = $challenge->json('data.nonce');
        $this->request('POST', '/api/auth/guest/recovery', $anonymous, [
            'challenge_id' => $id,
            'recovery_public_key' => $key,
            'signature' => bin2hex(sodium_crypto_sign_detached("satflux:guest-recovery:v1|{$id}|{$nonce}", sodium_crypto_sign_secretkey($keypair))),
        ])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('store_id', $store->id);
        $this->request('GET', '/api/user', $anonymous)->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_enrollment_during_password_validation_cannot_complete_password_login(): void
    {
        $user = User::factory()->create();
        Event::listen(Validated::class, function (Validated $event): void {
            app(GuestProvisioningService::class)->attachRecoveryKey($event->user, str_repeat('a', 64));
        });
        $cookies = [];
        try {
            $this->request('POST', '/api/auth/login', $cookies, [
                'email' => $user->email, 'password' => 'password', 'remember' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        } finally {
            Event::forget(Validated::class);
        }
        $this->assertSame(str_repeat('a', 64), $user->fresh()->guest_recovery_public_key);
        $this->request('GET', '/api/user', $cookies)->assertUnauthorized();
    }

    public function test_failed_audit_rolls_back_credentials_and_preserves_existing_sessions(): void
    {
        $user = User::factory()->create();
        $cookies = $this->login($user);
        $oldPassword = $user->fresh()->password;
        $oldRemember = $user->fresh()->remember_token;
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, function (): void {
            throw new \RuntimeException('Synthetic audit failure.');
        });
        try {
            $this->request('POST', '/api/account/recovery-key', $cookies, [
                'expected_user_id' => $user->id, 'recovery_public_key' => str_repeat('a', 64),
            ])->assertStatus(500);
        } finally {
            Event::forget($event);
        }
        $this->assertNull($user->fresh()->guest_recovery_public_key);
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame($oldRemember, $user->fresh()->remember_token);
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $user->id);
    }
}
