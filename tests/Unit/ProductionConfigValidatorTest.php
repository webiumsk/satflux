<?php

namespace Tests\Unit;

use App\Support\ProductionConfigValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductionConfigValidatorTest extends TestCase
{
    #[Test]
    public function it_reports_nothing_when_all_required_values_are_present(): void
    {
        $config = [
            'app.key' => 'base64:abc',
            'services.btcpay.base_url' => 'https://btcpay.example.com',
            'services.btcpay.api_key' => 'key',
            'sanctum.stateful' => ['satflux.io'],
        ];

        $this->assertSame([], ProductionConfigValidator::missing(fn (string $key) => $config[$key] ?? null));
    }

    #[Test]
    public function it_reports_env_names_of_missing_or_empty_values(): void
    {
        $config = [
            'app.key' => '',
            'services.btcpay.base_url' => 'https://btcpay.example.com',
            'services.btcpay.api_key' => null,
            'sanctum.stateful' => [],
        ];

        $this->assertSame(
            ['APP_KEY', 'BTCPAY_API_KEY', 'SANCTUM_STATEFUL_DOMAINS'],
            ProductionConfigValidator::missing(fn (string $key) => $config[$key] ?? null),
        );
    }

    #[Test]
    public function it_reports_sanctum_stateful_domains_when_env_produces_empty_string_element(): void
    {
        $config = [
            'app.key' => 'base64:abc',
            'services.btcpay.base_url' => 'https://btcpay.example.com',
            'services.btcpay.api_key' => 'key',
            // SANCTUM_STATEFUL_DOMAINS="" exploded by the config file.
            'sanctum.stateful' => [''],
        ];

        $this->assertSame(
            ['SANCTUM_STATEFUL_DOMAINS'],
            ProductionConfigValidator::missing(fn (string $key) => $config[$key] ?? null),
        );
    }
}
