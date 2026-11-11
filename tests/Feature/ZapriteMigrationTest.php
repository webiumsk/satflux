<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\ZapriteMigrationService;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ZapriteMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/invoicing/ephemeral/migration/zaprite';

    private const KEY = 'synthetic-zaprite-read-only-test-key';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.free_pro_enabled' => false]);
        Storage::fake('local');
        Storage::fake('public');
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3,
            'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
    }

    #[Test]
    public function authentication_verification_and_entitlement_are_required_before_a_provider_call(): void
    {
        $this->postJson(self::URL, ['api_key' => self::KEY])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(self::URL, ['api_key' => self::KEY])->assertForbidden();
        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        Http::assertNothingSent();
    }

    #[Test]
    public function one_page_is_read_with_a_transient_token_on_the_fixed_host_and_never_stored(): void
    {
        $page = $this->page();
        Log::spy();
        Http::fake(['https://api.zaprite.com/v1/orders*' => Http::response($page)]);
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertOk()->assertExactJson(['data' => $page])
            ->assertHeader('Cache-Control', 'no-store, private');
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.zaprite.com/v1/orders?includePending=true&page=1'
                && $request->hasHeader('Authorization', 'Bearer '.self::KEY)
                && $request->body() === '';
        });
        Http::assertSentCount(1);
        $this->assertDatabaseCount('business_documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    #[Test]
    public function the_requested_page_and_security_transport_options_are_fixed(): void
    {
        $page = $this->page();
        $page['meta'] = ['itemsCount' => 101, 'pagesCount' => 2, 'page' => 2, 'perPage' => 100];
        Http::fake(function (Request $request, array $options) use ($page) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['stream']);
            $this->assertTrue($options['verify']);
            $this->assertSame(5, $options['connect_timeout']);
            $this->assertSame(20, $options['timeout']);
            $this->assertSame(1, $options['read_timeout']);

            return Http::response($page);
        });
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY, 'page' => 2])
            ->assertOk()->assertExactJson(['data' => $page]);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'page=2'));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidInputs(): array
    {
        return [
            'missing key' => [[]],
            'short key' => [['api_key' => 'tiny']],
            'long key' => [['api_key' => str_repeat('x', 513)]],
            'header injection' => [['api_key' => "synthetic\r\nAuthorization: leaked"]],
            'non-ascii key' => [['api_key' => 'synthetic-key-č']],
            'array key' => [['api_key' => ['synthetic']]],
            'array page' => [['api_key' => self::KEY, 'page' => [1]]],
            'string page' => [['api_key' => self::KEY, 'page' => '1']],
            'zero page' => [['api_key' => self::KEY, 'page' => 0]],
            'large page' => [['api_key' => self::KEY, 'page' => 21]],
            'destination override' => [['api_key' => self::KEY, 'url' => 'https://attacker.invalid']],
            'company impersonation' => [['api_key' => self::KEY, 'company_id' => 'synthetic-foreign-company']],
            'user impersonation' => [['api_key' => self::KEY, 'user_id' => 123]],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function invalid_inputs_never_call_a_provider_or_reflect_credentials(array $input): void
    {
        $this->actingAs($this->user)->postJson(self::URL, $input)
            ->assertUnprocessable()->assertExactJson(['code' => 'zaprite_invalid_input'])
            ->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
    }

    #[Test]
    public function html_accept_does_not_turn_validation_into_a_credential_flashing_redirect(): void
    {
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY, 'page' => 0], ['Accept' => 'text/html'])
            ->assertUnprocessable()->assertExactJson(['code' => 'zaprite_invalid_input'])
            ->assertSessionMissing('_old_input.api_key');
        Http::assertNothingSent();
    }

    #[Test]
    public function form_and_query_credentials_are_not_accepted(): void
    {
        $this->actingAs($this->user)->post(self::URL, ['api_key' => self::KEY])
            ->assertUnprocessable()->assertExactJson(['code' => 'zaprite_invalid_input']);
        $this->postJson(self::URL.'?api_key='.self::KEY, [])
            ->assertUnprocessable()->assertExactJson(['code' => 'zaprite_invalid_input']);
        Http::assertNothingSent();
    }

    /** @return array<string, array{int, int, string}> */
    public static function providerErrors(): array
    {
        return [
            'unauthorized' => [401, 422, 'zaprite_auth_failed'],
            'forbidden' => [403, 422, 'zaprite_auth_failed'],
            'rate limited' => [429, 429, 'zaprite_rate_limited'],
            'server error' => [500, 502, 'zaprite_unavailable'],
            'redirect' => [302, 502, 'zaprite_unavailable'],
        ];
    }

    #[Test]
    #[DataProvider('providerErrors')]
    public function provider_errors_are_generic_and_redirects_are_never_followed(int $upstream, int $status, string $code): void
    {
        Http::fake(['https://api.zaprite.com/*' => Http::response(
            ['message' => 'Private upstream detail '.self::KEY], $upstream, ['Location' => 'https://attacker.invalid'],
        )]);
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertStatus($status)->assertExactJson(['code' => $code]);
        Http::assertSentCount(1);
    }

    #[Test]
    public function provider_exceptions_cannot_escape_into_logs_or_echo_tokens(): void
    {
        Log::spy();
        Http::fake(fn () => throw new RuntimeException('Authorization: Bearer '.self::KEY));
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertStatus(502)->assertExactJson(['code' => 'zaprite_unavailable']);
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function provider_secret_reflection_and_malformed_json_are_rejected(): void
    {
        $page = $this->page();
        $page['items'][0]['metadata'] = ['private' => self::KEY];
        Http::fakeSequence()->push($page)->push('{invalid-json');
        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
                ->assertStatus(502)->assertExactJson(['code' => 'zaprite_response_invalid']);
        }
    }

    #[Test]
    public function malformed_and_partial_pages_are_rejected_instead_of_becoming_a_complete_export(): void
    {
        $cases = [];
        $page = $this->page();
        $page['meta']['itemsCount'] = 2;
        $cases[] = $page;
        $page = $this->page();
        $page['meta']['page'] = 2;
        $cases[] = $page;
        $page = $this->page();
        $page['meta']['perPage'] = 101;
        $cases[] = $page;
        $page = $this->page();
        $page['items'][0]['id'] = null;
        $cases[] = $page;
        $page = $this->page();
        $page['meta']['itemsCount'] = '1';
        $cases[] = $page;
        $page = $this->page();
        $page['meta']['pagesCount'] = 2;
        $cases[] = $page;

        $sequence = Http::fakeSequence();
        foreach ($cases as $case) {
            $sequence->push($case);
        }
        foreach ($cases as $case) {
            $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
                ->assertStatus(502)->assertExactJson(['code' => 'zaprite_response_invalid']);
        }
    }

    #[Test]
    public function zero_results_are_explicitly_valid(): void
    {
        $page = ['items' => [], 'meta' => ['itemsCount' => 0, 'pagesCount' => 0, 'page' => 1, 'perPage' => 100]];
        Http::fake(['https://api.zaprite.com/*' => Http::response($page)]);
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])->assertOk()->assertExactJson(['data' => $page]);
    }

    #[Test]
    public function oversized_pages_are_bounded_even_without_a_content_length_header(): void
    {
        Http::fake(['https://api.zaprite.com/*' => Http::response(str_repeat(' ', ZapriteMigrationService::MAX_RESPONSE_BYTES + 1))]);
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertUnprocessable()->assertExactJson(['code' => 'zaprite_limit_exceeded']);
    }

    #[Test]
    public function a_complete_valid_body_cannot_be_accepted_after_the_total_stream_deadline(): void
    {
        $service = new class extends ZapriteMigrationService
        {
            public int $time = 0;

            protected function nowNanoseconds(): int
            {
                return $this->time;
            }
        };
        $underlying = Utils::streamFor(json_encode($this->page(), JSON_THROW_ON_ERROR));
        $stream = FnStream::decorate($underlying, [
            'read' => function (int $length) use ($underlying, $service): string {
                $body = $underlying->read($length);
                $service->time = 20_000_000_001;

                return $body;
            },
        ]);
        Http::fake(fn () => Create::promiseFor(new Response(200, [], $stream)));
        $this->app->instance(ZapriteMigrationService::class, $service);
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertStatus(502)->assertExactJson(['code' => 'zaprite_unavailable']);
    }

    #[Test]
    public function an_empty_non_eof_stream_read_cannot_be_accepted_as_a_complete_json_response(): void
    {
        $underlying = Utils::streamFor(json_encode($this->page(), JSON_THROW_ON_ERROR));
        $stream = FnStream::decorate($underlying, [
            'eof' => fn (): bool => false,
            'read' => fn (): string => '',
        ]);
        Http::fake(fn () => Create::promiseFor(new Response(200, [], $stream)));
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertStatus(502)->assertExactJson(['code' => 'zaprite_unavailable']);
    }

    #[Test]
    public function exports_exceeding_the_complete_snapshot_limit_are_rejected(): void
    {
        $page = $this->page();
        $page['items'] = array_fill(0, 100, $page['items'][0]);
        $page['meta'] = ['itemsCount' => 2001, 'pagesCount' => 21, 'page' => 1, 'perPage' => 100];
        Http::fake(['https://api.zaprite.com/*' => Http::response($page)]);
        $this->actingAs($this->user)->postJson(self::URL, ['api_key' => self::KEY])
            ->assertUnprocessable()->assertExactJson(['code' => 'zaprite_limit_exceeded']);
    }

    /** @return array<string, mixed> */
    private function page(): array
    {
        return [
            'items' => [[
                'id' => 'synthetic-order-1', 'orgId' => 'synthetic-org', 'orderType' => 'invoice',
                'currency' => 'EUR', 'totalAmount' => 29900, 'status' => 'UNDERPAID',
                'invoice' => ['number' => '2', 'status' => 'UNDERPAID', 'lineItems' => []],
                'transactions' => [['status' => 'CONFIRMED', 'amountInOrderCurrency' => 5000]],
            ]],
            'meta' => ['itemsCount' => 1, 'pagesCount' => 1, 'page' => 1, 'perPage' => 100],
        ];
    }
}
