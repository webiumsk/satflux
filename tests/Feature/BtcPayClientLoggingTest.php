<?php

namespace Tests\Feature;

use App\Services\BtcPay\BtcPayClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class BtcPayClientLoggingTest extends TestCase
{
    public function test_routine_btcpay_logs_keep_status_and_route_shape_without_request_or_response_data(): void
    {
        config([
            'services.btcpay.base_url' => 'https://btcpay.example.test',
            'services.btcpay.api_key' => 'synthetic-server-key',
        ]);

        $records = [];
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->andReturnUsing(function ($message, $context) use (&$records): void {
            $records[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('btcpay')->andReturn($logger);
        Http::fake([
            '*' => Http::response([
                'email' => 'synthetic-private@example.test',
                'wallet' => ['connectionString' => 'synthetic-wallet-secret'],
                'preimage' => 'synthetic-preimage',
                'id' => 'synthetic-invoice-id',
            ]),
        ]);

        (new BtcPayClient('synthetic-server-key'))->get(
            '/api/v1/stores/synthetic-store-id/offerings/synthetic-offering/subscribers/private@example.test',
            ['customer' => 'synthetic-query-email@example.test'],
        );

        $this->assertCount(1, $records);
        [$message, $context] = $records[0];
        $this->assertSame('BTCPay API Request', $message);
        $this->assertSame(['method', 'endpoint', 'status'], array_keys($context));
        $this->assertSame('GET', $context['method']);
        $this->assertSame(200, $context['status']);
        $this->assertSame(
            '/api/v1/stores/[redacted]/offerings/[redacted]/subscribers/[redacted]',
            $context['endpoint'],
        );
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        foreach ([
            'synthetic-server-key',
            'synthetic-private@example.test',
            'synthetic-query-email@example.test',
            'synthetic-wallet-secret',
            'synthetic-preimage',
            'synthetic-invoice-id',
            'synthetic-store-id',
        ] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $encoded);
        }
    }
}
