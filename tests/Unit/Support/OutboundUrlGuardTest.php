<?php

namespace Tests\Unit\Support;

use App\Support\Http\OutboundUrlGuard;
use Tests\TestCase;

class OutboundUrlGuardTest extends TestCase
{
    /**
     * @param  array<string, list<string>>  $dns
     */
    public static function guardWithDns(array $dns): OutboundUrlGuard
    {
        return new class($dns) extends OutboundUrlGuard
        {
            /** @param array<string, list<string>> $dns */
            public function __construct(private array $dns) {}

            protected function lookup(string $host): array
            {
                return $this->dns[$host] ?? [];
            }
        };
    }

    public function test_public_https_url_is_pinned_without_redirects(): void
    {
        $guard = self::guardWithDns(['shop.example.com' => ['93.184.216.34', '2606:2800:220:1::1']]);

        $options = $guard->pinnedOptions('https://shop.example.com/callback?x=1');

        $this->assertSame(false, $options['allow_redirects'] ?? null);
        $this->assertSame(['shop.example.com:443:93.184.216.34,2606:2800:220:1::1'], $options['curl'][CURLOPT_RESOLVE] ?? null);
    }

    public function test_unsafe_urls_are_rejected(): void
    {
        $guard = self::guardWithDns([
            'shop.example.com' => ['93.184.216.34'],
            'private.example.com' => ['93.184.216.34', '10.0.0.5'],
            'cgnat.example.com' => ['100.64.1.1'],
            'mapped.example.com' => ['::ffff:127.0.0.1'],
            'nat64.example.com' => ['64:ff9b::a9fe:a9fe'],
            'metadata.example.com' => ['169.254.169.254'],
        ]);

        foreach ([
            'http://shop.example.com/cb',
            'https://user:pass@shop.example.com/cb',
            'https://shop.example.com:8443/cb',
            'https://93.184.216.34/cb',
            'https://[::1]/cb',
            'https://localhost/cb',
            'https://unresolvable.example.com/cb',
            'https://private.example.com/cb',
            'https://cgnat.example.com/cb',
            'https://mapped.example.com/cb',
            'https://nat64.example.com/cb',
            'https://metadata.example.com/cb',
        ] as $url) {
            $this->assertNull($guard->pinnedOptions($url), $url);
        }
    }
}
