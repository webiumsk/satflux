<?php

namespace Tests\Unit;

use App\Services\LnAddressLud21Prober;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LnAddressProbeDiagnosticsTest extends TestCase
{
    public static function failures(): array
    {
        $cases = [];
        foreach (['descriptor', 'invoice', 'verify'] as $stage) {
            foreach (['dns_rejected', 'request_failed', 'http_error', 'invalid_json'] as $category) {
                $cases[$stage.' '.$category] = [$stage, $category, 503, 'malformed'];
            }
            $cases[$stage.' redirect'] = [$stage, 'http_error', 302, 'malformed'];
            $cases[$stage.' JSON null'] = [$stage, 'invalid_json', 200, 'null'];
            $cases[$stage.' JSON scalar'] = [$stage, 'invalid_json', 200, '"private scalar lnbc-private"'];
        }

        return $cases;
    }

    #[DataProvider('failures')]
    public function test_failure_logs_only_stage_host_category_and_available_status(string $stage, string $category, int $status, string $body): void
    {
        Cache::flush();
        $logs = [];
        Log::shouldReceive('info')->once()->andReturnUsing(function (string $message, array $context) use (&$logs): void {
            $logs[] = [$message, $context];
        });
        $host = $stage.'.wallet.example';
        $prober = $this->prober($category === 'dns_rejected' ? $host : null);
        Http::fake(function ($request) use ($host, $category, $status, $body) {
            if (parse_url($request->url(), PHP_URL_HOST) === $host) {
                return match ($category) {
                    'request_failed' => throw new ConnectionException(
                        'Failed https://alice:secret@'.$host.'/private/alice?invoice=lnbc-private&token=secret 8.8.8.8',
                    ),
                    'http_error' => Http::response('private body alice@wallet.example lnbc-private', $status, [
                        'Location' => 'https://private.wallet.example/private?token=secret',
                    ]),
                    'invalid_json' => Http::response($body, 200),
                    default => throw new \LogicException('DNS rejection must prevent HTTP'),
                };
            }

            return Http::response($this->payload(parse_url($request->url(), PHP_URL_HOST)), 200);
        });

        $this->assertSame([
            'lud21' => false,
            'reason' => $stage === 'invoice' ? 'invalid_lnurlp' : 'unreachable',
        ], $prober->probe('private-user@descriptor.wallet.example', true));

        $context = ['stage' => $stage, 'url_host' => $host, 'category' => $category];
        if (in_array($category, ['http_error', 'invalid_json'], true)) {
            $context['http_status'] = $category === 'http_error' ? $status : 200;
        }
        // Exact equality rejects extra URLs, paths, addresses, bodies, IPs,
        // invoices, exception objects and exception messages in either field.
        $this->assertSame([['LUD-21 probe failed', $context]], $logs);
        Http::assertSentCount(array_search($stage, ['descriptor', 'invoice', 'verify'], true)
            + (in_array($category, ['dns_rejected', 'request_failed'], true) ? 0 : 1));
    }

    public function test_success_is_silent_cached_and_restores_stage_on_reused_instance(): void
    {
        Cache::flush();
        $logs = [];
        Log::shouldReceive('info')->once()->andReturnUsing(function (string $message, array $context) use (&$logs): void {
            $logs[] = [$message, $context];
        });
        $prober = $this->prober();
        $fail = false;
        Http::fake(function ($request) use (&$fail) {
            return $fail
                ? Http::response('', 502)
                : Http::response($this->payload(parse_url($request->url(), PHP_URL_HOST)), 200);
        });

        $expected = ['lud21' => true, 'reason' => 'ok'];
        $this->assertSame($expected, $prober->probe('private-user@descriptor.wallet.example', true));
        $this->assertSame($expected, $prober->probe('private-user@descriptor.wallet.example'));
        Http::assertSentCount(3);
        $this->assertSame([], $logs);

        $fail = true;
        $this->assertSame(['lud21' => false, 'reason' => 'unreachable'],
            $prober->probe('private-user@descriptor.wallet.example', true));
        $this->assertSame([['LUD-21 probe failed', [
            'stage' => 'descriptor', 'url_host' => 'descriptor.wallet.example',
            'category' => 'http_error', 'http_status' => 502,
        ]]], $logs);
    }

    private function prober(?string $rejectedHost = null): LnAddressLud21Prober
    {
        return new class($rejectedHost) extends LnAddressLud21Prober
        {
            public function __construct(private ?string $rejectedHost) {}

            protected function resolvePublicIps(string $host): ?array
            {
                return $host === $this->rejectedHost ? null : ['8.8.8.8'];
            }
        };
    }

    private function payload(string $host): array
    {
        return match ($host) {
            'descriptor.wallet.example' => [
                'callback' => 'https://invoice.wallet.example/private/alice?token=secret',
                'minSendable' => 1000, 'maxSendable' => 2000,
            ],
            'invoice.wallet.example' => [
                'pr' => 'lnbc-private',
                'verify' => 'https://verify.wallet.example/private/lnbc-private?token=secret',
            ],
            'verify.wallet.example' => ['status' => 'OK', 'settled' => false, 'pr' => 'lnbc-private'],
        };
    }
}
