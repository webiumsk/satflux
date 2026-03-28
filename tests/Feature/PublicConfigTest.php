<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicConfigTest extends TestCase
{
    public function test_public_config_returns_trimmed_btcpay_base_url(): void
    {
        config([
            'services.btcpay.base_url' => 'https://btcpay.example.com/',
            'services.btcpay.lightning_address_domain' => 'btcpay.example.com',
        ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertExactJson([
                'btcpay_base_url' => 'https://btcpay.example.com',
                'btcpay_lightning_address_domain' => 'btcpay.example.com',
            ]);
    }
}
