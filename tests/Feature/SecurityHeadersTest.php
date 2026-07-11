<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function csp_header_is_absent_when_disabled(): void
    {
        config(['security.csp.enabled' => false]);

        $response = $this->get('/');

        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    #[Test]
    public function csp_report_only_header_is_sent_during_rollout(): void
    {
        config(['security.csp.enabled' => true, 'security.csp.report_only' => true]);

        $response = $this->get('/');

        $response->assertHeaderMissing('Content-Security-Policy');
        $policy = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
    }

    #[Test]
    public function csp_is_enforced_when_report_only_is_off(): void
    {
        config(['security.csp.enabled' => true, 'security.csp.report_only' => false]);

        $response = $this->get('/');

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
        // YouTube embeds (landing video, documentation) must stay allowed
        $this->assertStringContainsString('https://www.youtube-nocookie.com', $policy);
    }

    #[Test]
    public function connect_src_is_an_explicit_allowlist_not_bare_wildcards(): void
    {
        config([
            'security.csp.enabled' => true,
            'security.csp.report_only' => false,
            'security.csp.evolu_relay_url' => 'wss://relay.example.com',
            'services.btcpay.public_url' => 'https://pay.example.com',
        ]);

        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        // The connect-src directive must not fall back to bare https:/wss:.
        preg_match('/connect-src ([^;]+)/', (string) $policy, $m);
        $connect = $m[1] ?? '';
        $this->assertStringContainsString("'self'", $connect);
        $this->assertStringContainsString('https://pay.example.com', $connect);
        $this->assertStringContainsString('wss://relay.example.com', $connect);
        $this->assertStringNotContainsString(' https: ', " {$connect} ");
        $this->assertStringNotContainsString(' wss: ', " {$connect} ");
    }

    #[Test]
    public function csp_disabled_in_production_fails_closed(): void
    {
        config(['security.csp.enabled' => false]);
        app()->detectEnvironment(fn () => 'production');

        $this->get('/')->assertStatus(500);
    }

    #[Test]
    public function matomo_origin_is_added_to_script_src_when_configured(): void
    {
        config([
            'security.csp.enabled' => true,
            'security.csp.report_only' => false,
            'services.matomo.url' => 'https://analytics.example.com/matomo',
        ]);

        $response = $this->get('/');

        $this->assertStringContainsString(
            "script-src 'self' https://analytics.example.com",
            $response->headers->get('Content-Security-Policy'),
        );
    }
}
