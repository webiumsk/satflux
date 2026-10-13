<?php

namespace Tests\Unit;

use App\Services\LnAddressLud21Prober;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LnAddressVerificationProbeTest extends TestCase
{
    public static function receipts(): array
    {
        return [
            'matching unpaid' => [['status' => 'OK', 'settled' => false, 'pr' => 'invoice'], true, 'ok'],
            'different invoice' => [['status' => 'OK', 'settled' => false, 'pr' => 'other'], false, 'invalid_verify'],
            'already settled probe' => [['status' => 'OK', 'settled' => true, 'pr' => 'invoice'], false, 'invalid_verify'],
            'error response' => [['status' => 'ERROR', 'reason' => 'Not found'], false, 'invalid_verify'],
            'missing status' => [['settled' => false, 'pr' => 'invoice'], false, 'invalid_verify'],
            'string boolean' => [['status' => 'OK', 'settled' => 'false', 'pr' => 'invoice'], false, 'invalid_verify'],
            'unreachable' => [null, false, 'unreachable'],
        ];
    }

    #[DataProvider('receipts')]
    public function test_probe_checks_the_actual_receipt(?array $receipt, bool $supported, string $reason): void
    {
        Cache::flush();
        $prober = new class($receipt) extends LnAddressLud21Prober
        {
            public function __construct(private ?array $receipt) {}

            protected function getJson(string $url): ?array
            {
                if (str_contains($url, '/.well-known/')) {
                    return ['callback' => 'https://wallet.example/pay', 'minSendable' => 1000, 'maxSendable' => 2000];
                }
                if (str_contains($url, '/pay?')) {
                    return ['pr' => 'invoice', 'verify' => 'https://wallet.example/verify'];
                }

                return $this->receipt;
            }
        };
        $this->assertSame(['lud21' => $supported, 'reason' => $reason], $prober->probe('alice@wallet.example'));
    }
}
