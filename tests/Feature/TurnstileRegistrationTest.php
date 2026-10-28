<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\GuestProvisioningService;
use App\Services\Turnstile\RegistrationTurnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TurnstileRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        config([
            'turnstile.enabled' => true,
            'turnstile.site_key' => 'synthetic-site-key',
            'turnstile.secret_key' => 'synthetic-server-only-secret',
            'turnstile.hostnames' => ['satflux.test'],
        ]);
    }

    private function verified(): array
    {
        return ['success' => true, 'hostname' => 'satflux.test', 'action' => 'register'];
    }

    private function registration(): array
    {
        return [
            'email' => 'new-account@example.test',
            'password' => 'synthetic-password',
            'password_confirmation' => 'synthetic-password',
            'privacy_consent' => true,
            'terms_accepted' => true,
            'turnstile_token' => 'synthetic-token',
        ];
    }

    private function preventGuestProvisioning(): void
    {
        $this->mock(GuestProvisioningService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('generateGuestEmail');
            $mock->shouldNotReceive('provisionGuest');
        });
    }

    private function assertNoAccountCreation(): void
    {
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('stores', 0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    /** @return array<string, array{0: mixed}> */
    public static function invalidTokens(): array
    {
        return ['missing' => [null], 'empty' => [''], 'whitespace' => ['   '],
            'array' => [['synthetic-token']], 'number' => [123], 'oversized' => [str_repeat('x', 2049)]];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_tokens_are_rejected_before_http_or_guest_provisioning(mixed $token): void
    {
        $this->preventGuestProvisioning();
        $this->postJson('/api/auth/guest', ['turnstile_token' => $token])
            ->assertUnprocessable()->assertJsonPath('code', 'turnstile_failed')
            ->assertJsonValidationErrors('turnstile_token');
        Http::assertNothingSent();
        $this->assertNoAccountCreation();
    }

    /** @return array<string, array{0: array<string, mixed>, 1: int, 2: string}> */
    public static function verifierResponses(): array
    {
        return [
            'invalid' => [['success' => false, 'error-codes' => ['invalid-input-response']], 422, 'turnstile_failed'],
            'expired or duplicate' => [['success' => false, 'error-codes' => ['timeout-or-duplicate']], 422, 'turnstile_failed'],
            'wrong action' => [['success' => true, 'action' => 'login', 'hostname' => 'satflux.test'], 422, 'turnstile_failed'],
            'wrong hostname' => [['success' => true, 'action' => 'register', 'hostname' => 'foreign.test'], 422, 'turnstile_failed'],
            'hostname suffix' => [['success' => true, 'action' => 'register', 'hostname' => 'satflux.test.foreign.test'], 422, 'turnstile_failed'],
            'invalid secret' => [['success' => false, 'error-codes' => ['invalid-input-secret']], 503, 'turnstile_unavailable'],
            'internal error' => [['success' => false, 'error-codes' => ['internal-error']], 503, 'turnstile_unavailable'],
            'missing success' => [[], 503, 'turnstile_unavailable'],
            'truthy success' => [['success' => 'true', 'action' => 'register', 'hostname' => 'satflux.test'], 503, 'turnstile_unavailable'],
            'missing action' => [['success' => true, 'hostname' => 'satflux.test'], 503, 'turnstile_unavailable'],
            'missing hostname' => [['success' => true, 'action' => 'register'], 503, 'turnstile_unavailable'],
            'malformed errors' => [['success' => false, 'error-codes' => [['synthetic-provider-diagnostic']]], 503, 'turnstile_unavailable'],
        ];
    }

    #[DataProvider('verifierResponses')]
    public function test_verifier_failures_do_not_create_accounts_or_leak_diagnostics(array $body, int $status, string $code): void
    {
        $this->preventGuestProvisioning();
        Http::fake([self::ENDPOINT => Http::response($body)]);
        $response = $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus($status)->assertJsonPath('code', $code);
        $serialized = $response->getContent();
        $this->assertStringNotContainsString('synthetic-token', $serialized);
        $this->assertStringNotContainsString('synthetic-server-only-secret', $serialized);
        $this->assertStringNotContainsString('synthetic-provider-diagnostic', $serialized);
        Http::assertSentCount(1);
        $this->assertNoAccountCreation();
    }

    /** @return array<string, array{0: int}> */
    public static function unavailableStatuses(): array
    {
        return ['redirect' => [302], 'unauthorized' => [403], 'rate limited' => [429], 'server failure' => [503]];
    }

    #[DataProvider('unavailableStatuses')]
    public function test_http_failures_are_unavailable_without_following_redirects_or_retrying(int $status): void
    {
        $this->preventGuestProvisioning();
        Http::fake([self::ENDPOINT => Http::response('synthetic-sensitive-provider-error', $status, ['Location' => 'https://foreign.test/'])]);
        $response = $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus(503)->assertJsonPath('code', 'turnstile_unavailable');
        $this->assertStringNotContainsString('synthetic-sensitive-provider-error', $response->getContent());
        Http::assertSentCount(1);
        $this->assertNoAccountCreation();
    }

    public function test_malformed_json_is_unavailable_without_provisioning(): void
    {
        $this->preventGuestProvisioning();
        Http::fake([self::ENDPOINT => Http::response('not json', 200)]);
        $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus(503)->assertJsonPath('code', 'turnstile_unavailable');
        $this->assertNoAccountCreation();
    }

    public function test_transport_failure_is_unavailable_and_does_not_leak_exception_text(): void
    {
        $this->preventGuestProvisioning();
        Http::fake(fn () => throw new ConnectionException('synthetic-sensitive-transport-error'));
        $response = $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus(503)->assertJsonPath('code', 'turnstile_unavailable');
        $this->assertStringNotContainsString('synthetic-sensitive-transport-error', $response->getContent());
        $this->assertNoAccountCreation();
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function incompleteConfiguration(): array
    {
        return ['missing site key' => ['site_key', ''], 'missing secret' => ['secret_key', ''],
            'missing hostname' => ['hostnames', []], 'wildcard hostname' => ['hostnames', ['*.satflux.test']],
            'hostname with port' => ['hostnames', ['satflux.test:443']], 'untyped hostname' => ['hostnames', [true]]];
    }

    #[DataProvider('incompleteConfiguration')]
    public function test_enabled_but_incomplete_configuration_blocks_creation_without_downgrading_public_flag(string $setting, mixed $value): void
    {
        $this->preventGuestProvisioning();
        config(['turnstile.'.$setting => $value]);
        $public = app(RegistrationTurnstile::class)->publicConfiguration();
        $this->assertTrue($public['enabled']);
        $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus(503)->assertJsonPath('code', 'turnstile_unavailable');
        Http::assertNothingSent();
        $this->assertNoAccountCreation();
    }

    public function test_production_rejects_official_dummy_site_and_secret_keys(): void
    {
        $this->preventGuestProvisioning();
        $this->app['env'] = 'production';
        config(['turnstile.site_key' => '1x00000000000000000000AA']);
        $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus(503)->assertJsonPath('code', 'turnstile_unavailable');
        config(['turnstile.site_key' => 'synthetic-site-key', 'turnstile.secret_key' => '1x0000000000000000000000000000000AA']);
        $this->postJson('/api/auth/guest', ['turnstile_token' => 'synthetic-token'])
            ->assertStatus(503)->assertJsonPath('code', 'turnstile_unavailable');
        Http::assertNothingSent();
        $this->assertNoAccountCreation();
    }

    public function test_verified_guest_provisions_only_after_verification_and_forwards_no_account_or_ip_data(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->verified())]);
        $this->mock(GuestProvisioningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateGuestEmail')->once()->andReturn('guest+synthetic@example.test');
            $mock->shouldReceive('provisionGuest')->once()->with(str_repeat('a', 64), 'guest+synthetic@example.test')
                ->andReturnUsing(function (): array {
                    Http::assertSentCount(1);
                    $user = User::factory()->guest()->create();

                    return [$user, Store::factory()->create(['user_id' => $user->id])];
                });
        });
        config(['turnstile.hostnames' => ['SATFLUX.TEST.']]);
        $this->withHeader('X-Forwarded-For', '192.0.2.99')->postJson('/api/auth/guest', [
            'recovery_public_key' => str_repeat('a', 64), 'turnstile_token' => 'synthetic-token',
        ])->assertCreated();
        Http::assertSent(fn (ClientRequest $request) => $request->url() === self::ENDPOINT
            && $request->data() === ['secret' => 'synthetic-server-only-secret', 'response' => 'synthetic-token']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('stores', 1);
        $this->assertAuthenticated();
    }

    public function test_valid_legacy_registration_still_creates_an_unverified_local_account_after_challenge(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->verified())]);
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'new-account@example.test', 'email_verified_at' => null]);
        $this->assertDatabaseCount('stores', 0);
        Http::assertSentCount(1);
        $this->assertGuest();
    }

    public function test_legacy_registration_requires_the_challenge_before_account_creation(): void
    {
        $payload = $this->registration();
        unset($payload['turnstile_token']);
        $this->postJson('/api/auth/register', $payload)->assertUnprocessable()->assertJsonPath('code', 'turnstile_failed');
        Http::assertNothingSent();
        $this->assertNoAccountCreation();
    }

    public function test_provider_duplicate_rejection_cannot_create_a_second_identity(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()->push($this->verified())
            ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        Notification::fake();
        $this->postJson('/api/auth/register', array_replace($this->registration(), ['email' => 'second-account@example.test']))
            ->assertUnprocessable()->assertJsonPath('code', 'turnstile_failed');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'second-account@example.test']);
        Notification::assertNothingSent();
        Http::assertSentCount(2);
    }

    public function test_invalid_legacy_challenge_does_not_write_existing_account_or_send_verification(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'new-account@example.test']);
        $password = $user->getAuthPassword();
        Http::fake([self::ENDPOINT => Http::response(['success' => false])]);
        $this->postJson('/api/auth/register', $this->registration())->assertUnprocessable()->assertJsonPath('code', 'turnstile_failed');
        $this->assertSame($password, $user->fresh()->getAuthPassword());
        $this->assertDatabaseCount('users', 1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_seed_first_rejects_legacy_creation_without_calling_the_verifier(): void
    {
        config(['guest.seed_first_registration' => true]);
        $this->postJson('/api/auth/register', $this->registration())->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertNoAccountCreation();
    }

    public function test_disabled_integration_retains_legacy_registration_without_any_provider_request(): void
    {
        config(['turnstile.enabled' => false]);
        $payload = $this->registration();
        unset($payload['turnstile_token']);
        $this->postJson('/api/auth/register', $payload)->assertCreated();
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_authenticated_guest_retry_does_not_need_a_new_challenge_or_mutate_identity(): void
    {
        $user = User::factory()->guest()->create(['guest_recovery_public_key' => str_repeat('a', 64)]);
        $store = Store::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->postJson('/api/auth/guest', ['recovery_public_key' => str_repeat('A', 64)])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('store_id', $store->id);
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('stores', 1);
        $this->assertSame(str_repeat('a', 64), $user->fresh()->guest_recovery_public_key);
        $this->postJson('/api/auth/guest', ['recovery_public_key' => str_repeat('b', 64)])
            ->assertStatus(409)->assertJsonPath('code', 'already_authenticated');
        Http::assertNothingSent();
        $this->assertSame(str_repeat('a', 64), $user->fresh()->guest_recovery_public_key);
    }

    public function test_password_login_does_not_require_turnstile(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        Http::assertNothingSent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_signed_recovery_does_not_require_turnstile(): void
    {
        $pair = sodium_crypto_sign_keypair();
        $public = bin2hex(sodium_crypto_sign_publickey($pair));
        $user = User::factory()->guest()->create(['guest_recovery_public_key' => $public]);
        $challenge = $this->postJson('/api/auth/guest/recovery/challenge')->assertOk()->json('data');
        $message = 'satflux:guest-recovery:v1|'.$challenge['challenge_id'].'|'.$challenge['nonce'];
        $this->postJson('/api/auth/guest/recovery', [
            'challenge_id' => $challenge['challenge_id'], 'recovery_public_key' => $public,
            'signature' => bin2hex(sodium_crypto_sign_detached($message, sodium_crypto_sign_secretkey($pair))),
        ])->assertOk()->assertJsonPath('user.id', $user->id);
        Http::assertNothingSent();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_public_config_never_exposes_secret_or_hostname_list(): void
    {
        $response = $this->getJson('/api/config')->assertOk()
            ->assertJsonPath('turnstile.enabled', true)
            ->assertJsonPath('turnstile.site_key', 'synthetic-site-key')
            ->assertJsonPath('turnstile.action', 'register');
        $this->assertSame(['enabled', 'site_key', 'action'], array_keys($response->json('turnstile')));
        $this->assertStringNotContainsString('synthetic-server-only-secret', $response->getContent());
        Http::assertNothingSent();
    }
}
