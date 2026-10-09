<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\GuestProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSwitchRememberCookieTest extends TestCase
{
    use RefreshDatabase;

    /** Cookie scope metadata retained when a browser's wire cookie values are copied. */
    private array $cookieScopes = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'array']);
    }

    /** Carry encrypted browser cookies between requests without reusing a cached guard. */
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
            $name = $cookie->getName();
            $scope = [$name, $cookie->getPath(), $cookie->getDomain()];
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) {
                $existingValue = $cookies[$name] ?? null;
                if ($existingValue !== null && ($this->cookieScopes[$existingValue] ?? null) === $scope) {
                    unset($cookies[$name]);
                }
            } else {
                $cookies[$name] = $cookie->getValue();
                $this->cookieScopes[$cookie->getValue()] = $scope;
            }
        }

        return $response;
    }

    private function login(User $user, array &$cookies, bool $remember = true): TestResponse
    {
        return $this->request('POST', '/api/auth/login', $cookies, [
            'email' => $user->email, 'password' => 'password', 'remember' => $remember,
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    private function signedRecovery(array &$cookies, string $keypair): array
    {
        $challenge = $this->request('POST', '/api/auth/guest/recovery/challenge', $cookies)->assertOk();
        $id = $challenge->json('data.challenge_id');
        $nonce = $challenge->json('data.nonce');

        return [
            'challenge_id' => $id,
            'recovery_public_key' => bin2hex(sodium_crypto_sign_publickey($keypair)),
            'signature' => bin2hex(sodium_crypto_sign_detached("satflux:guest-recovery:v1|{$id}|{$nonce}", sodium_crypto_sign_secretkey($keypair))),
        ];
    }

    public static function switches(): iterable
    {
        foreach (['password', 'recovery'] as $flow) {
            foreach ([false, true] as $web) {
                yield $flow.($web ? ' / web' : ' / API') => [$flow, $web];
            }
        }
    }

    #[DataProvider('switches')]
    public function test_session_expiry_cannot_restore_the_previous_remembered_account(string $flow, bool $web): void
    {
        $a = User::factory()->create();
        $keypair = sodium_crypto_sign_keypair();
        $b = User::factory()->create(['guest_recovery_public_key' => $flow === 'recovery' ? bin2hex(sodium_crypto_sign_publickey($keypair)) : null]);
        $store = Store::factory()->create(['user_id' => $b->id]);
        $cookies = [];
        $this->login($a, $cookies);
        $recaller = Auth::guard('web')->getRecallerName();
        $this->assertArrayHasKey($recaller, $cookies);
        $rememberTokenA = $a->fresh()->remember_token;
        $sessionA = session()->getId();
        $csrfA = session()->token();
        if ($flow === 'password') {
            $this->login($b, $cookies, false);
        } else {
            $payload = $this->signedRecovery($cookies, $keypair);
            $this->request('POST', '/api/auth/guest/recovery', $cookies, $payload)->assertOk()
                ->assertJsonPath('user.id', $b->id)->assertJsonPath('store_id', $store->id);
        }
        $this->assertNotSame($sessionA, session()->getId());
        $this->assertNotSame($csrfA, session()->token());
        $this->assertSame($rememberTokenA, $a->fresh()->remember_token);
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $b->id);
        // Losing the session cookie models expiry while retaining any persistent cookies.
        unset($cookies[config('session.cookie')]);
        $this->request('GET', $web ? '/stores/'.$store->id.'/apps' : '/api/user', $cookies)->assertUnauthorized();
        $this->assertGuest('web');
        $this->assertArrayNotHasKey($recaller, $cookies);
    }

    public function test_same_account_login_without_remember_also_retires_its_browser_cookie(): void
    {
        $user = User::factory()->create();
        $cookies = [];
        $this->login($user, $cookies);
        $recaller = Auth::guard('web')->getRecallerName();
        $this->login($user, $cookies, false);
        $this->assertArrayNotHasKey($recaller, $cookies);
        unset($cookies[config('session.cookie')]);
        $this->request('GET', '/api/user', $cookies)->assertUnauthorized();
    }

    public function test_remembered_switch_replaces_the_cookie_with_the_new_account_and_preserves_other_devices(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $cookies = [];
        $this->login($a, $cookies);
        $recaller = Auth::guard('web')->getRecallerName();
        $anotherDeviceA = [$recaller => $cookies[$recaller]];
        $this->login($b, $cookies, true);
        $this->assertNotSame($anotherDeviceA[$recaller], $cookies[$recaller]);
        unset($cookies[config('session.cookie')]);
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $b->id);
        $this->assertTrue(Auth::guard('web')->viaRemember());
        $this->request('GET', '/api/user', $anotherDeviceA)->assertOk()->assertJsonPath('id', $a->id);
    }

    public function test_invalid_password_does_not_remove_the_current_accounts_remember_cookie(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $cookies = [];
        $this->login($a, $cookies);
        $recaller = Auth::guard('web')->getRecallerName();
        $original = $cookies[$recaller];
        $this->request('POST', '/api/auth/login', $cookies, ['email' => $b->email, 'password' => 'incorrect'])
            ->assertUnprocessable();
        $this->assertSame($original, $cookies[$recaller]);
        unset($cookies[config('session.cookie')]);
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $a->id);
    }

    public static function invalidRecovery(): iterable
    {
        yield 'invalid signature' => [false];
        yield 'unknown challenge' => [true];
    }

    #[DataProvider('invalidRecovery')]
    public function test_invalid_recovery_does_not_change_the_current_accounts_remember_cookie(bool $unknownChallenge): void
    {
        $a = User::factory()->create();
        $cookies = [];
        $this->login($a, $cookies);
        $recaller = Auth::guard('web')->getRecallerName();
        $original = $cookies[$recaller];
        $payload = $this->signedRecovery($cookies, sodium_crypto_sign_keypair());
        if ($unknownChallenge) {
            $payload['challenge_id'] = '00000000-0000-4000-8000-000000000000';
        } else {
            $payload['signature'] = str_repeat('00', 64);
        }
        $this->request('POST', '/api/auth/guest/recovery', $cookies, $payload)->assertUnprocessable();
        $this->assertSame($original, $cookies[$recaller]);
        unset($cookies[config('session.cookie')]);
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $a->id);
    }

    public function test_new_guest_login_clears_an_orphaned_remember_cookie(): void
    {
        $old = User::factory()->create();
        $cookies = [];
        $this->login($old, $cookies);
        unset($cookies[config('session.cookie')]);
        // An invalidated persistent cookie can remain in an otherwise anonymous browser.
        $old->forceFill(['remember_token' => 'retired-token'])->save();
        $guest = User::factory()->guest()->create(['guest_recovery_public_key' => str_repeat('a', 64)]);
        $store = Store::factory()->create(['user_id' => $guest->id]);
        $this->mock(GuestProvisioningService::class, function ($mock) use ($guest, $store): void {
            $mock->shouldReceive('generateGuestEmail')->once()->andReturn($guest->email);
            $mock->shouldReceive('provisionGuest')->with(str_repeat('a', 64), $guest->email)->once()->andReturn([$guest, $store]);
        });
        $this->request('POST', '/api/auth/guest', $cookies, ['recovery_public_key' => str_repeat('a', 64)])
            ->assertCreated()->assertJsonPath('user.id', $guest->id);
        $this->assertArrayNotHasKey(Auth::guard('web')->getRecallerName(), $cookies);
        $this->request('GET', '/api/user', $cookies)->assertOk()->assertJsonPath('id', $guest->id);
    }

    public function test_cookie_deletion_matches_the_configured_domain_and_path(): void
    {
        config(['session.domain' => 'localhost', 'session.path' => '/panel', 'session.secure' => true]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $cookies = [];
        $initial = $this->login($a, $cookies);
        $recaller = Auth::guard('web')->getRecallerName();
        $rememberCookie = collect($initial->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $recaller);
        $response = $this->login($b, $cookies, false);
        $deletion = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $recaller);
        $this->assertNotNull($rememberCookie);
        $this->assertNotNull($deletion);
        $this->assertSame('localhost', $rememberCookie->getDomain());
        $this->assertSame('/panel', $rememberCookie->getPath());
        $this->assertSame('localhost', $deletion->getDomain());
        $this->assertSame('/panel', $deletion->getPath());
        $this->assertLessThan(time(), $deletion->getExpiresTime());
    }
}
