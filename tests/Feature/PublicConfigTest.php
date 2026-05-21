<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicConfigTest extends TestCase
{
    public function test_public_config_returns_trimmed_btcpay_base_url(): void
    {
        config([
            'services.btcpay.base_url' => 'http://btcpay-internal:49392/',
            'services.btcpay.public_url' => 'https://btcpay.example.com/',
            'services.btcpay.lightning_address_domain' => 'btcpay.example.com',
        ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertExactJson([
                'btcpay_base_url' => 'https://btcpay.example.com',
                'btcpay_lightning_address_domain' => 'btcpay.example.com',
            ]);
    }

    public function test_public_config_falls_back_to_base_url_when_public_url_unset(): void
    {
        config([
            'services.btcpay.base_url' => 'https://pay.example.com',
            'services.btcpay.public_url' => '',
            'services.btcpay.lightning_address_domain' => '',
        ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('btcpay_base_url', 'https://pay.example.com');
    }
}
