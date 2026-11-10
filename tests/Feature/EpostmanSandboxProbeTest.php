<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpostmanSandboxProbeTest extends TestCase
{
    private const ORIGIN = 'https://testpostman.slovakodata.com';

    private const PARTICIPANT = '0245:1234567890';

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'testing',
            'app.debug' => false,
            'queue.default' => 'sync',
            'database.connections.sqlite.url' => null,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'mail.default' => 'array',
            'logging.default' => 'null',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_link($file) || is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function credentials(?string $body = null): string
    {
        $path = tempnam(sys_get_temp_dir(), '.env.satflux-probe-');
        $this->assertIsString($path);
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $body ?? json_encode([
            'client_id' => 'synthetic-probe-client',
            'client_secret' => 'synthetic-probe-secret',
            'participant_id' => self::PARTICIPANT,
        ], JSON_THROW_ON_ERROR));
        chmod($path, 0600);

        return $path;
    }

    /** @return array{exit: int, data: array<string, mixed>, output: string} */
    private function probe(string $path): array
    {
        $exit = Artisan::call('efaktura:probe-sandbox', ['--credentials' => $path]);
        $output = Artisan::output();
        $this->assertStringNotContainsString('synthetic-probe-client', $output);
        $this->assertStringNotContainsString('synthetic-probe-secret', $output);
        $this->assertStringNotContainsString(self::PARTICIPANT, $output);

        return ['exit' => $exit, 'data' => json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR), 'output' => $output];
    }

    private function metadata(string $id): array
    {
        return ['documentId' => $id, 'receiverParticipantId' => self::PARTICIPANT, 'status' => 'RECEIVED'];
    }

    private function fakeProvider(bool $empty = false): void
    {
        Http::fake(function ($request) use ($empty) {
            $this->assertStringStartsWith(self::ORIGIN.'/', $request->url());
            $this->assertStringNotContainsString('/sapi/v1/', $request->url());
            if ($request->url() === self::ORIGIN.'/sapi/auth/token') {
                $this->assertSame('POST', $request->method());
                $this->assertStringContainsString('application/json', $request->header('Content-Type')[0]);
                $this->assertSame('synthetic-probe-client', $request['client_id']);
                $this->assertSame('synthetic-probe-secret', $request['client_secret']);

                return Http::response(['access_token' => 'synthetic-access-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null]);
            }
            $this->assertSame('GET', $request->method(), 'Probe must not send or acknowledge documents');
            $this->assertSame([self::PARTICIPANT], $request->header('X-Peppol-Participant-Id'));
            if (preg_match('~/receive/(synthetic-document-[1-3])$~', $request->url(), $matches)) {
                return Http::response(['metadata' => $this->metadata($matches[1]), 'payload' => '<Invoice>synthetic-private-original</Invoice>', 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }
            if ($empty) {
                return Http::response(['documents' => [], 'totalCount' => 0]);
            }
            if (str_contains($request->url(), 'pageToken=next')) {
                return Http::response(['documents' => [$this->metadata('synthetic-document-4')], 'totalCount' => 4]);
            }

            return Http::response(['documents' => [$this->metadata('synthetic-document-1'), $this->metadata('synthetic-document-2'), $this->metadata('synthetic-document-3')], 'totalCount' => 4, 'nextPageToken' => 'next']);
        });
    }

    public function test_actual_adapter_uses_fixed_sandbox_and_bounded_gets_without_import_or_acknowledgement(): void
    {
        $this->fakeProvider();
        $result = $this->probe($this->credentials());
        $this->assertSame(0, $result['exit']);
        $this->assertSame('documents_available', $result['data']['status']);
        $this->assertSame(2, $result['data']['pages']);
        $this->assertSame(4, $result['data']['documents_listed']);
        $this->assertSame(3, $result['data']['details_retrieved']);
        $this->assertTrue($result['data']['xml_retrieved']);
        $this->assertFalse($result['data']['receiving_proven']);
        $this->assertStringNotContainsString('synthetic-document', $result['output']);
        $this->assertStringNotContainsString('synthetic-private-original', $result['output']);
        Http::assertSentCount(6);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge') || str_contains($request->url(), '/send'));
        $this->assertSame([], DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"));
    }

    public function test_empty_receiving_list_is_successful_authentication_but_not_full_receiving_evidence(): void
    {
        $this->fakeProvider(empty: true);
        $result = $this->probe($this->credentials());
        $this->assertSame(0, $result['exit']);
        $this->assertSame('empty_inbox', $result['data']['status']);
        $this->assertTrue($result['data']['authenticated']);
        $this->assertFalse($result['data']['xml_retrieved']);
        $this->assertFalse($result['data']['receiving_proven']);
        Http::assertSentCount(2);
    }

    public function test_unsafe_environment_is_rejected_before_even_reading_credentials_or_network(): void
    {
        Http::fake();
        foreach ([['app.env' => 'production'], ['app.debug' => true], ['queue.default' => 'database'], ['database.connections.sqlite.url' => 'sqlite:///tmp/not-a-test.sqlite'], ['database.default' => 'pgsql'], ['database.connections.sqlite.database' => '/tmp/not-a-test-database.sqlite'], ['cache.default' => 'database'], ['session.driver' => 'file'], ['mail.default' => 'smtp'], ['logging.default' => 'stack']] as $override) {
            $previous = [];
            foreach ($override as $key => $value) {
                $previous[$key] = config($key);
            }
            config($override);
            $result = $this->probe('/not/a/credential/file');
            $this->assertSame(1, $result['exit']);
            $this->assertSame('environment', $result['data']['stage']);
            config($previous);
        }
        Http::assertNothingSent();
    }

    public function test_permissions_symlinks_oversized_or_invalid_credentials_fail_before_network(): void
    {
        Http::fake();
        $groupReadable = $this->credentials();
        chmod($groupReadable, 0640);
        $target = $this->credentials();
        $symlink = $target.'.link';
        symlink($target, $symlink);
        $this->temporaryFiles[] = $symlink;
        $invalidParticipant = $this->credentials(json_encode(['client_id' => 'synthetic-probe-client', 'client_secret' => 'synthetic-probe-secret', 'participant_id' => '0245:not-a-dic']));
        $unexpectedOrigin = $this->credentials(json_encode(['client_id' => 'synthetic-probe-client', 'client_secret' => 'synthetic-probe-secret', 'participant_id' => self::PARTICIPANT, 'base_url' => 'https://postman.slovakodata.com']));
        foreach ([$groupReadable, $symlink, $this->credentials(str_repeat('x', 16385)), $this->credentials('{not valid json'), $invalidParticipant, $unexpectedOrigin] as $file) {
            $result = $this->probe($file);
            $this->assertSame(1, $result['exit']);
            $this->assertSame('credentials', $result['data']['stage']);
        }
        Http::assertNothingSent();
    }

    public function test_provider_errors_are_sanitized_and_bad_credentials_are_not_retried(): void
    {
        Http::fake([self::ORIGIN.'/*' => Http::response(['message' => 'synthetic-probe-secret synthetic-private-original '.self::PARTICIPANT], 401)]);
        $result = $this->probe($this->credentials());
        $this->assertSame(1, $result['exit']);
        $this->assertSame('authentication', $result['data']['stage']);
        $this->assertSame(401, $result['data']['http_status']);
        $this->assertStringNotContainsString('synthetic-private-original', $result['output']);
        Http::assertSentCount(1);
    }

    public function test_repeated_cursor_is_a_failure_with_bounded_requests(): void
    {
        Http::fake(fn ($request) => str_ends_with($request->url(), '/auth/token')
            ? Http::response(['access_token' => 'synthetic-access-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null])
            : Http::response(['documents' => [], 'totalCount' => 0, 'nextPageToken' => 'same']));
        $result = $this->probe($this->credentials());
        $this->assertSame(1, $result['exit']);
        $this->assertSame(2, $result['data']['pages']);
        Http::assertSentCount(3);
    }

    public function test_symlinked_parent_directory_is_refused_without_network(): void
    {
        Http::fake();
        $file = $this->credentials();
        $directoryLink = $file.'.directory';
        symlink(dirname($file), $directoryLink);
        $this->temporaryFiles[] = $directoryLink;
        $result = $this->probe($directoryLink.'/'.basename($file));
        $this->assertSame(1, $result['exit']);
        $this->assertSame('credentials', $result['data']['stage']);
        Http::assertNothingSent();
    }

    public function test_cached_configuration_is_refused_before_credentials_or_network(): void
    {
        Http::fake();
        $this->app->instance('config_loaded_from_cache', true);
        $result = $this->probe('/not/a/credential/file');
        $this->assertSame(1, $result['exit']);
        $this->assertSame('environment', $result['data']['stage']);
        Http::assertNothingSent();
    }

    public function test_continuing_pagination_stops_at_five_pages_without_unbounded_requests(): void
    {
        $pages = 0;
        Http::fake(function ($request) use (&$pages) {
            return str_ends_with($request->url(), '/auth/token')
                ? Http::response(['access_token' => 'synthetic-access-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null])
                : Http::response(['documents' => [], 'totalCount' => 0, 'nextPageToken' => 'page-'.++$pages]);
        });
        $result = $this->probe($this->credentials());
        $this->assertSame(1, $result['exit']);
        $this->assertSame(5, $result['data']['pages']);
        Http::assertSentCount(6);
    }

    public function test_malformed_list_item_does_not_trigger_a_detail_request(): void
    {
        Http::fake(fn ($request) => str_ends_with($request->url(), '/auth/token')
            ? Http::response(['access_token' => 'synthetic-access-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null])
            : Http::response(['documents' => [['private-error' => 'synthetic-private-original']], 'totalCount' => 1]));
        $result = $this->probe($this->credentials());
        $this->assertSame(1, $result['exit']);
        $this->assertSame('listing', $result['data']['stage']);
        $this->assertSame(0, $result['data']['details_retrieved']);
        $this->assertStringNotContainsString('synthetic-private-original', $result['output']);
        Http::assertSentCount(2);
    }

    public function test_detail_from_another_receiver_is_rejected_without_exposing_it(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/auth/token')) {
                return Http::response(['access_token' => 'synthetic-access-token', 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_token' => null]);
            }
            if (str_contains($request->url(), '/receive/synthetic-document')) {
                return Http::response(['metadata' => ['documentId' => 'synthetic-document', 'receiverParticipantId' => '0245:9999999999'], 'payload' => '<Invoice>foreign-private-original</Invoice>', 'payloadFormat' => 'XML', 'status' => 'RECEIVED']);
            }

            return Http::response(['documents' => [$this->metadata('synthetic-document')], 'totalCount' => 1]);
        });
        $result = $this->probe($this->credentials());
        $this->assertSame(1, $result['exit']);
        $this->assertSame('detail', $result['data']['stage']);
        $this->assertFalse($result['data']['xml_retrieved']);
        $this->assertStringNotContainsString('9999999999', $result['output']);
        $this->assertStringNotContainsString('foreign-private-original', $result['output']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/acknowledge'));
    }
}
