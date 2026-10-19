<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailVerificationChallenge;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\EmailCodeChallengeService;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\Concerns\ReadsEmailCodes;
use Tests\TestCase;

class CashuChangeRecoveryTest extends TestCase
{
    use ReadsEmailCodes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
    }

    private function store(): Store
    {
        $user = User::factory()->create([
            'btcpay_api_key' => 'change-owner-key', 'guest_recovery_public_key' => str_repeat('a', 64),
            'guest_recovery_enrolled_at' => now(),
        ]);

        return Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'private-provider-store', 'wallet_type' => 'cashu']);
    }

    private function path(Store $store, string $suffix = ''): string
    {
        return "/api/stores/{$store->id}/cashu/change".$suffix;
    }

    private function issue(Store $store, string $action = 'export'): string
    {
        $this->actingAs($store->user)->postJson($this->path($store, '/confirmation/request'), ['action' => $action])
            ->assertOk()->assertJsonPath('data.challenge.purpose', 'cashu_change_recovery');

        return $this->lastEmailCode($store->user->email);
    }

    private function summary(): array
    {
        return [
            'version' => 1, 'pendingSat' => '7', 'sweptSat' => '9223372036854775807', 'minimumSweepSat' => '100',
            'unresolvedRecords' => 2, 'availableRecords' => 1, 'exportedRecords' => 1, 'sweepingRecords' => 0,
            'manualReviewRecords' => 0, 'unsupportedUnitRecords' => 0, 'otherUnresolvedRecords' => 0,
        ];
    }

    public function test_summary_preserves_exact_amount_strings_and_strips_provider_private_fields(): void
    {
        $store = $this->store();
        Http::fake(['*' => Http::response([...$this->summary(), 'tokens' => ['synthetic-private-token'], 'storeId' => 'private-provider-store'])]);
        $response = $this->actingAs($store->user)->getJson($this->path($store));
        $response->assertOk()->assertJsonPath('data.status', 'available')->assertJsonPath('data.can_recover', true)
            ->assertJsonPath('data.swept_sat', '9223372036854775807')->assertJsonPath('data.pending_sat', '7');
        $this->assertCount(13, $response->json('data'));
        $this->assertStringNotContainsString('synthetic-private-token', $response->getContent());
        $this->assertStringNotContainsString('private-provider-store', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_ends_with($request->url(), '/change')
            && $request->hasHeader('Authorization', 'Bearer change-owner-key'));
    }

    public static function malformedSummaries(): array
    {
        return [
            ['version', '1'], ['pendingSat', 7], ['pendingSat', '07'], ['pendingSat', '-1'],
            ['pendingSat', '9223372036854775808'], ['sweptSat', null], ['minimumSweepSat', '1.0'],
            ['unresolvedRecords', true], ['availableRecords', '1'], ['exportedRecords', -1],
            ['otherUnresolvedRecords', 2147483648], ['manualReviewRecords', 1.5],
        ];
    }

    #[DataProvider('malformedSummaries')]
    public function test_malformed_summary_never_fabricates_zero_or_enables_recovery(string $field, mixed $value): void
    {
        $store = $this->store();
        Http::fake(['*' => Http::response([...$this->summary(), $field => $value])]);
        $this->actingAs($store->user)->getJson($this->path($store))
            ->assertOk()->assertJsonPath('data.status', 'unavailable')->assertJsonPath('data.can_recover', false)
            ->assertJsonPath('data.pending_sat', null)->assertJsonPath('data.available_records', null);
    }

    public function test_older_provider_is_explicitly_unsupported_without_erasing_other_routes(): void
    {
        $store = $this->store();
        Http::fake(['*' => Http::response(['error' => 'synthetic-private-error'], 404)]);
        $response = $this->actingAs($store->user)->getJson($this->path($store));
        $response->assertOk()->assertJsonPath('data.status', 'unsupported')->assertJsonPath('data.pending_sat', null);
        $this->assertStringNotContainsString('synthetic-private-error', $response->getContent());
    }

    public function test_admin_can_read_summary_with_owner_key_but_cannot_release_owner_assets(): void
    {
        $store = $this->store();
        $admin = User::factory()->admin()->create();
        Http::fake(['*' => Http::response($this->summary())]);
        $this->actingAs($admin)->getJson($this->path($store))->assertOk()->assertJsonPath('data.can_recover', false);
        $this->postJson($this->path($store, '/confirmation/request'), ['action' => 'export'])->assertForbidden();
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => '123456'])->assertForbidden();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer change-owner-key'));
        $this->assertSame(0, EmailVerificationChallenge::count());
    }

    public static function deniedActors(): array
    {
        return [['anonymous', 401], ['other-owner', 403], ['guest', 403], ['unverified', 403], ['support', 403]];
    }

    #[DataProvider('deniedActors')]
    public function test_every_asset_authorization_route_requires_the_actual_registered_verified_owner(string $kind, int $status): void
    {
        $store = $this->store();
        if ($kind !== 'anonymous') {
            $user = match ($kind) {
                'other-owner' => User::factory()->create(),
                'support' => User::factory()->create(['role' => 'support']),
                default => $store->user,
            };
            if ($kind === 'guest') {
                $user->update(['is_guest' => true]);
            }
            if ($kind === 'unverified') {
                $user->forceFill(['email_verified_at' => null])->save();
            }
            $this->actingAs($user);
        }
        Http::fake();
        foreach (['/confirmation/request', '/confirmation/resend', '/export', '/verify'] as $suffix) {
            $this->postJson($this->path($store, $suffix), ['action' => 'export', 'code' => '123456', 'confirmed' => true])->assertStatus($status);
        }
        Http::assertNothingSent();
        $this->assertSame(0, EmailVerificationChallenge::count());
    }

    public function test_issue_requires_existing_sensitive_action_reauthentication_for_password_accounts(): void
    {
        $store = $this->store();
        $store->user->forceFill(['guest_recovery_public_key' => null, 'guest_recovery_enrolled_at' => null])->save();
        $this->actingAs($store->user)->postJson($this->path($store, '/confirmation/request'), ['action' => 'export'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->assertSame(0, EmailVerificationChallenge::count());
        $this->postJson($this->path($store, '/confirmation/request'), ['action' => 'export', 'password' => 'password'])->assertOk();
        $this->assertSame(['store_id' => $store->id, 'action' => 'export'], EmailVerificationChallenge::sole()->payload);
    }

    public function test_code_cannot_cross_store_or_action_even_after_verification(): void
    {
        $store = $this->store();
        $other = Store::factory()->create(['user_id' => $store->user_id, 'wallet_type' => 'cashu']);
        $code = $this->issue($store);
        app(EmailCodeChallengeService::class)->verify($store->user, EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY, $code);
        Http::fake();
        $this->postJson($this->path($other, '/export'), ['confirmed' => true, 'code' => $code])->assertStatus(410);
        $this->postJson($this->path($store, '/verify'), ['confirmed' => true, 'code' => $code])->assertStatus(410);
        $this->postJson($this->path($other, '/confirmation/resend'), ['action' => 'export'])->assertStatus(410);
        $this->assertNull(EmailVerificationChallenge::sole()->consumed_at);
        Http::assertNothingSent();
    }

    public function test_export_consumes_exact_code_before_one_dispatch_and_returns_only_no_store_attachment(): void
    {
        $store = $this->store();
        $code = $this->issue($store);
        $before = $store->fresh()->getRawOriginal();
        Http::fake(function ($request) {
            $this->assertNotNull(EmailVerificationChallenge::sole()->consumed_at);
            $this->assertNull(EmailVerificationChallenge::sole()->payload);
            $this->assertSame(1, AuditLog::where('action', 'cashu.change_recovery_authorized')->count());
            $this->assertSame(['confirmed' => true], $request->data());
            $this->assertSame('{"confirmed":true}', $request->body());
            $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
            $this->assertTrue($request->hasHeader('Accept', 'application/json'));

            return Http::response(['tokens' => ['cashuAsynthetic-private-token'], 'private' => 'synthetic-private-extra']);
        });
        $response = $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code]);
        $response->assertOk()->assertExactJson(['cashuAsynthetic-private-token'])
            ->assertHeader('Content-Disposition', 'attachment; filename="cashumelt-change.json"')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $store->fresh()->getRawOriginal());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/change/export')
            && $request->hasHeader('Authorization', 'Bearer change-owner-key'));
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code])->assertStatus(410);
        Http::assertSentCount(1);
    }

    public function test_verification_is_action_bound_and_does_not_claim_wallet_safety(): void
    {
        $store = $this->store();
        $code = $this->issue($store, 'verify');
        Http::fake(function ($request) {
            $this->assertSame('{"confirmed":true}', $request->body());
            $this->assertSame(['confirmed' => true], $request->data());
            $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
            $this->assertTrue($request->hasHeader('Accept', 'application/json'));
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer change-owner-key'));
            $this->assertSame('POST', $request->method());
            $this->assertStringEndsWith('/change/verify', $request->url());

            return Http::response(['verified' => true, 'tokens' => ['synthetic-private-token'], 'safeToChangeWallet' => false]);
        });
        $this->postJson($this->path($store, '/verify'), ['confirmed' => true, 'code' => $code])
            ->assertOk()->assertExactJson(['data' => ['verified' => true]]);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/safety'));
    }

    public static function nonliteralConfirmations(): array
    {
        return [[false], [1], ['true'], ['1'], [null]];
    }

    #[DataProvider('nonliteralConfirmations')]
    public function test_nonliteral_confirmation_cannot_consume_authorization_or_dispatch(mixed $confirmation): void
    {
        $store = $this->store();
        $code = $this->issue($store);
        Http::fake();
        $this->postJson($this->path($store, '/export'), ['confirmed' => $confirmation, 'code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirmed']);
        $this->assertNull(EmailVerificationChallenge::sole()->consumed_at);
        Http::assertNothingSent();
    }

    public function test_wrong_attempts_are_committed_and_expired_codes_do_not_dispatch(): void
    {
        $store = $this->store();
        $code = $this->issue($store);
        Http::fake();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $this->wrongEmailCode($code)])
                ->assertStatus($attempt === 5 ? 423 : 422);
        }
        $this->assertSame(5, EmailVerificationChallenge::sole()->attempts);
        $this->assertNotNull(EmailVerificationChallenge::sole()->consumed_at);
        $code = $this->issue($store);
        $this->travel(11)->minutes();
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code])
            ->assertStatus(410)->assertJsonPath('code', 'challenge_expired');
        Http::assertNothingSent();
    }

    public function test_resend_keeps_binding_and_rotates_the_code_after_cooldown(): void
    {
        $store = $this->store();
        $old = $this->issue($store);
        $this->postJson($this->path($store, '/confirmation/resend'), ['action' => 'verify'])->assertStatus(410);
        $this->postJson($this->path($store, '/confirmation/resend'), ['action' => 'export'])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->postJson($this->path($store, '/confirmation/resend'), ['action' => 'export'])->assertOk();
        $code = $this->lastEmailCode();
        Http::fake(['*' => Http::response(['tokens' => ['cashuAsynthetic-token']])]);
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $old])->assertUnprocessable();
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code])->assertOk();
        Http::assertSentCount(1);
    }

    public static function failedDispatches(): array
    {
        return [['transport', 502, 'cashu_change_unavailable'], ['429', 422, 'cashu_change_recovery_refused'],
            ['500', 502, 'cashu_change_unavailable'], ['422', 422, 'cashu_change_recovery_refused'],
            ['404', 409, 'cashu_change_unsupported'], ['empty', 409, 'cashu_change_empty'],
            ['malformed', 502, 'cashu_change_unavailable']];
    }

    #[DataProvider('failedDispatches')]
    public function test_unknown_or_refused_outcomes_consume_code_without_retry_or_sensitive_logs(string $failure, int $status, string $expected): void
    {
        $store = $this->store();
        $code = $this->issue($store);
        $logs = [];
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$logs) {
            $logs[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('btcpay')->andReturn($logger);
        Log::shouldReceive('error')->never();
        $dispatches = 0;
        Http::fake(function () use ($failure, &$dispatches) {
            $dispatches++;
            if ($failure === 'transport') {
                throw new ConnectionException('synthetic-private-token private-provider-store');
            }

            return match ($failure) {
                'empty' => Http::response(['tokens' => []]),
                'malformed' => Http::response(['tokens' => 'synthetic-private-token']),
                default => Http::response(['error' => 'synthetic-private-token', 'errors' => ['private' => 'synthetic-private-token']], (int) $failure),
            };
        });
        $response = $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code]);
        $response->assertStatus($status)->assertJsonPath('code', $expected);
        $this->assertNotNull(EmailVerificationChallenge::sole()->consumed_at);
        $this->assertSame(1, $dispatches);
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code])->assertStatus(410);
        $this->assertSame(1, $dispatches);
        foreach (['synthetic-private-token', 'private-provider-store', $code] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
            $this->assertStringNotContainsString($private, json_encode($logs));
        }
        foreach ($logs as [$message, $context]) {
            $this->assertSame(['method', 'endpoint', 'status'], array_keys($context));
        }
    }

    public function test_stateful_browser_mutation_rejects_missing_csrf_before_issuing_a_code(): void
    {
        $store = $this->store();
        config(['sanctum.stateful' => ['localhost']]);
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        Http::fake();
        $this->actingAs($store->user)->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/'])
            ->postJson($this->path($store, '/confirmation/request'), ['action' => 'export'])->assertStatus(419);
        $this->assertSame(0, EmailVerificationChallenge::count());
        Http::assertNothingSent();
    }

    public function test_a_challenge_superseded_after_code_verification_cannot_dispatch_or_consume_its_replacement(): void
    {
        $store = $this->store();
        $code = $this->issue($store);
        $real = app(EmailCodeChallengeService::class);
        $mock = \Mockery::mock(EmailCodeChallengeService::class)->makePartial();
        $mock->shouldReceive('verify')->once()->andReturnUsing(function ($user, $purpose, $value) use ($real, $store) {
            $verified = $real->verify($user, $purpose, $value);
            $real->issue($user, $purpose, $user->email, ['store_id' => $store->id, 'action' => 'verify']);

            return $verified;
        });
        $this->app->instance(EmailCodeChallengeService::class, $mock);
        Http::fake();
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code])->assertStatus(410);
        $replacement = EmailVerificationChallenge::query()->live()->sole();
        $this->assertSame('verify', $replacement->payload['action']);
        $this->assertNull($replacement->consumed_at);
        Http::assertNothingSent();
    }

    public static function eligibilityRaces(): array
    {
        return [['email', 410], ['guest', 403], ['unverified', 403], ['store-owner', 403]];
    }

    #[DataProvider('eligibilityRaces')]
    public function test_current_owner_and_inbox_are_rechecked_atomically_after_code_verification(string $change, int $status): void
    {
        $store = $this->store();
        $code = $this->issue($store);
        $real = app(EmailCodeChallengeService::class);
        $mock = \Mockery::mock(EmailCodeChallengeService::class)->makePartial();
        $mock->shouldReceive('verify')->once()->andReturnUsing(function ($user, $purpose, $value) use ($real, $store, $change) {
            $verified = $real->verify($user, $purpose, $value);
            match ($change) {
                'email' => $user->forceFill(['email' => 'changed-owner@example.com'])->save(),
                'guest' => $user->update(['is_guest' => true]),
                'unverified' => $user->forceFill(['email_verified_at' => null])->save(),
                'store-owner' => $store->update(['user_id' => User::factory()->create()->id]),
            };

            return $verified;
        });
        $this->app->instance(EmailCodeChallengeService::class, $mock);
        Http::fake();
        $this->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => $code])->assertStatus($status);
        $this->assertNull(EmailVerificationChallenge::sole()->consumed_at);
        $this->assertSame(0, AuditLog::where('action', 'cashu.change_recovery_authorized')->count());
        Http::assertNothingSent();
    }

    public function test_a_wallet_change_grant_cannot_authorize_asset_recovery_without_its_own_code(): void
    {
        $store = $this->store();
        $this->grantWalletChange($store->user, $store);
        Http::fake();
        $this->actingAs($store->user)->postJson($this->path($store, '/export'), ['confirmed' => true, 'code' => '123456'])
            ->assertStatus(410)->assertJsonPath('code', 'challenge_missing');
        $this->assertNull(EmailVerificationChallenge::sole()->consumed_at);
        Http::assertNothingSent();
    }

    public static function mutationPaths(): array
    {
        return [['export'], ['verify']];
    }

    #[DataProvider('mutationPaths')]
    public function test_source_client_never_automatically_retries_recovery_posts_even_with_default_retry_flag(string $action): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('synthetic-private-token');
        });
        try {
            (new BtcPayClient('change-owner-key'))->post('/api/v1/stores/private-provider-store/plugins/cashumelt/change/'.$action, ['confirmed' => true]);
            $this->fail('Unknown remote outcome must be returned without automatic replay.');
        } catch (BtcPayException $e) {
            $this->assertSame(1, $calls);
            $this->assertStringNotContainsString('synthetic-private-token', $e->getMessage());
        }
    }
}
