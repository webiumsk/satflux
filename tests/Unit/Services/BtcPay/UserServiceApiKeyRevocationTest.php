<?php

namespace Tests\Unit\Services\BtcPay;

use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\UserService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * BTCPay 2.4.4 (btcpayserver#7561): DELETE /users/{id}/api-keys/{x} takes the
 * "akid_" key ID, derived from the secret, instead of the secret itself.
 */
class UserServiceApiKeyRevocationTest extends TestCase
{
    #[Test]
    public function api_key_id_is_derived_from_the_secret_like_btcpay_does(): void
    {
        // akid_ + hex(sha256(sha256(utf8(secret))))[0:16] (APIKeyRepository.Selector.ByApiKey.GetId)
        $this->assertSame('akid_057e4f9209414306', UserService::apiKeyIdFromSecret('test-key'));
        $this->assertSame('akid_4f8b42c22dd3729b', UserService::apiKeyIdFromSecret('abc'));
    }

    #[Test]
    public function an_existing_api_key_id_is_passed_through(): void
    {
        $this->assertSame('akid_4743fdafe0c5807a', UserService::apiKeyIdFromSecret('akid_4743fdafe0c5807a'));
        $this->assertSame('AKID_4743fdafe0c5807a', UserService::apiKeyIdFromSecret('AKID_4743fdafe0c5807a'));
    }

    #[Test]
    public function revocation_sends_the_derived_id_not_the_secret(): void
    {
        Http::fake(fn () => Http::response([], 200));

        $this->service()->deleteUserApiKey('btcpay-user-1', 'test-key');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/v1/users/btcpay-user-1/api-keys/akid_057e4f9209414306'));
    }

    #[Test]
    public function revocation_falls_back_to_the_secret_on_a_pre_244_host(): void
    {
        $urls = [];
        Http::fake(function ($request) use (&$urls) {
            $urls[] = (string) $request->url();

            return str_contains((string) $request->url(), '/api-keys/akid_')
                ? Http::response(['code' => 'apikey-not-found', 'message' => 'This apikey does not exists'], 400)
                : Http::response([], 200);
        });

        $this->service()->deleteUserApiKey('btcpay-user-1', 'test-key');

        $this->assertCount(2, $urls);
        $this->assertStringEndsWith('/api-keys/akid_057e4f9209414306', $urls[0]);
        $this->assertStringEndsWith('/api-keys/test-key', $urls[1]);
    }

    #[Test]
    public function other_revocation_errors_are_not_retried_with_the_secret(): void
    {
        Http::fake(fn () => Http::response(['message' => 'Forbidden'], 403));

        try {
            $this->service()->deleteUserApiKey('btcpay-user-1', 'test-key');
            $this->fail('Expected BtcPayException');
        } catch (BtcPayException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_missing_key_on_a_244_host_is_reported_once(): void
    {
        Http::fake(fn () => Http::response(['code' => 'apikey-not-found', 'message' => 'This apikey does not exists'], 400));

        $this->expectException(BtcPayException::class);

        try {
            $this->service()->deleteUserApiKey('btcpay-user-1', 'test-key');
        } finally {
            // ID first, then the transitional secret retry - nothing beyond that.
            Http::assertSentCount(2);
        }
    }

    #[Test]
    public function an_unrelated_404_is_not_retried_with_the_secret(): void
    {
        Http::fake(fn () => Http::response(['code' => 'user-not-found', 'message' => 'The user was not found'], 404));

        try {
            $this->service()->deleteUserApiKey('btcpay-user-1', 'test-key');
            $this->fail('Expected BtcPayException');
        } catch (BtcPayException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('user-not-found', $e->getErrorCode());
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api-keys/test-key'));
    }

    #[Test]
    public function logged_endpoints_never_contain_the_api_key_secret(): void
    {
        $this->assertSame(
            '/api/v1/users/btcpay-user-1/api-keys/***REDACTED***',
            BtcPayClient::redactEndpoint('/api/v1/users/btcpay-user-1/api-keys/test-key')
        );
        $this->assertSame(
            '/api/v1/users/btcpay-user-1/api-keys/akid_057e4f9209414306',
            BtcPayClient::redactEndpoint('/api/v1/users/btcpay-user-1/api-keys/akid_057e4f9209414306')
        );
        $this->assertSame('/api/v1/api-keys/current', BtcPayClient::redactEndpoint('/api/v1/api-keys/current'));
        $this->assertSame('/api/v1/api-keys', BtcPayClient::redactEndpoint('/api/v1/api-keys'));
        $this->assertSame('/api/v1/stores/s-1/invoices', BtcPayClient::redactEndpoint('/api/v1/stores/s-1/invoices'));
    }

    #[Test]
    public function the_secret_fallback_request_is_logged_redacted_but_sent_unredacted(): void
    {
        Http::fake(fn ($request) => str_contains((string) $request->url(), '/api-keys/akid_')
            ? Http::response(['code' => 'apikey-not-found', 'message' => 'This apikey does not exists'], 400)
            : Http::response([], 200));

        $endpoints = [];
        $channel = \Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('info')->andReturnUsing(function ($message, array $context = []) use (&$endpoints) {
            $endpoints[] = $context['endpoint'] ?? null;
        });
        Log::partialMock()->shouldReceive('channel')->with('btcpay')->andReturn($channel);

        $this->service()->deleteUserApiKey('btcpay-user-1', 'test-key');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api-keys/test-key'));
        $this->assertNotEmpty($endpoints);
        foreach ($endpoints as $endpoint) {
            $this->assertStringNotContainsString('test-key', (string) $endpoint);
        }
        $this->assertContains('/api/v1/users/btcpay-user-1/api-keys/***REDACTED***', $endpoints);
    }

    #[Test]
    public function a_transport_failure_during_the_secret_fallback_leaks_no_secret_into_logs_or_the_exception(): void
    {
        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '/api-keys/akid_')) {
                return Http::response(['code' => 'apikey-not-found', 'message' => 'This apikey does not exists'], 400);
            }

            // Guzzle/cURL style message: quotes the full URL, secret included.
            throw new ConnectionException("cURL error 7: Failed to connect to host for {$request->url()}");
        });

        $notes = [];
        $channel = \Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('info')->andReturnUsing(function ($message, array $context = []) use (&$notes) {
            $notes[] = ($context['endpoint'] ?? '').' '.($context['note'] ?? '');
        });
        $log = Log::partialMock();
        $log->shouldReceive('channel')->with('btcpay')->andReturn($channel);
        // The failed revocation is also reported on the default channel; the
        // partial mock has no container, so stub those levels out.
        $log->shouldReceive('error', 'warning', 'info', 'debug')->andReturnNull();

        $client = new BtcPayClient('server-key');
        $retries = new \ReflectionProperty($client, 'maxRetries');
        $retries->setValue($client, 0);

        try {
            (new UserService($client))->deleteUserApiKey('btcpay-user-1', 'test-key');
            $this->fail('Expected BtcPayException');
        } catch (BtcPayException $e) {
            $this->assertStringNotContainsString('test-key', $e->getMessage());
            $this->assertStringContainsString('/api-keys/***REDACTED***', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }

        $this->assertNotEmpty($notes);
        foreach ($notes as $note) {
            $this->assertStringNotContainsString('test-key', $note);
        }
    }

    private function service(): UserService
    {
        return new UserService(new BtcPayClient('server-key'));
    }
}
