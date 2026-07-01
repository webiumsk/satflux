<?php

namespace Tests\Unit;

use App\Services\Invoicing\SmtpHostGuard;
use Tests\TestCase;

class SmtpHostGuardTest extends TestCase
{
    protected function guard(): SmtpHostGuard
    {
        return new SmtpHostGuard;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['invoicing.smtp_allow_private_hosts' => false]);
    }

    public function test_rejects_loopback_ip(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->guard()->assertAllowed('127.0.0.1');
    }

    public function test_rejects_private_ranges(): void
    {
        foreach (['10.0.0.5', '172.16.1.1', '192.168.1.10', '169.254.1.1'] as $ip) {
            try {
                $this->guard()->assertAllowed($ip);
                $this->fail("Expected {$ip} to be rejected");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rejects_ipv6_loopback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->guard()->assertAllowed('[::1]');
    }

    public function test_rejects_empty_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->guard()->assertAllowed('');
    }

    public function test_allows_public_ip(): void
    {
        $this->guard()->assertAllowed('8.8.8.8');
        $this->addToAssertionCount(1);
    }

    public function test_allows_anything_when_private_hosts_enabled(): void
    {
        config(['invoicing.smtp_allow_private_hosts' => true]);
        $this->guard()->assertAllowed('127.0.0.1');
        $this->guard()->assertAllowed('mailpit');
        $this->addToAssertionCount(1);
    }
}
