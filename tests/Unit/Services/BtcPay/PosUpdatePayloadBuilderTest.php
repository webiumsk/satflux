<?php

namespace Tests\Unit\Services\BtcPay;

use App\Services\BtcPay\Apps\PosUpdatePayloadBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosUpdatePayloadBuilderTest extends TestCase
{
    #[Test]
    public function it_maps_btcpay_24_pos_tax_fields(): void
    {
        $builder = new PosUpdatePayloadBuilder;

        $payload = $builder->build([
            'appName' => 'Cafe PoS',
            'taxIncludedInPrice' => true,
            'tipTaxRate' => '10',
            'defaultTaxRate' => '20',
        ]);

        $this->assertTrue($payload['taxIncludedInPrice']);
        $this->assertSame('10', $payload['tipTaxRate']);
        $this->assertSame('20', $payload['defaultTaxRate']);
    }

    #[Test]
    public function it_sends_null_tip_tax_rate_to_clear_tip_tax(): void
    {
        $builder = new PosUpdatePayloadBuilder;

        $payload = $builder->build([
            'tipTaxRate' => null,
        ]);

        $this->assertArrayHasKey('tipTaxRate', $payload);
        $this->assertNull($payload['tipTaxRate']);
    }
}
