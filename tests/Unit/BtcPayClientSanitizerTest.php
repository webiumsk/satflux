<?php

namespace Tests\Unit;

use App\Services\BtcPay\BtcPayClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BtcPayClientSanitizerTest extends TestCase
{
    #[Test]
    public function it_redacts_invitation_credentials_from_log_payloads(): void
    {
        config([
            'services.btcpay.base_url' => 'https://btcpay.example.com',
            'services.btcpay.api_key' => 'server-key',
        ]);

        $client = new BtcPayClientSanitizerProbe('server-key');

        $sanitized = $client->sanitizeForTest([
            'approvalCode' => 'live-approval-code',
            'invitationUrl' => 'https://btcpay.example.com/invite/invite-id/live-approval-code',
            'nested' => [
                'approval_code' => 'nested-approval-code',
                'invitation_url' => 'https://btcpay.example.com/invite/invite-id/nested-approval-code',
                'token' => 'api-token',
            ],
        ]);

        $encoded = json_encode($sanitized);

        $this->assertSame('***REDACTED***', $sanitized['approvalCode']);
        $this->assertSame('***REDACTED***', $sanitized['invitationUrl']);
        $this->assertSame('***REDACTED***', $sanitized['nested']['approval_code']);
        $this->assertSame('***REDACTED***', $sanitized['nested']['invitation_url']);
        $this->assertStringNotContainsString('live-approval-code', $encoded);
        $this->assertStringNotContainsString('nested-approval-code', $encoded);
    }

    #[Test]
    public function it_redacts_lightning_credentials_and_wallet_config(): void
    {
        config([
            'services.btcpay.base_url' => 'https://btcpay.example.com',
            'services.btcpay.api_key' => 'server-key',
        ]);

        $client = new BtcPayClientSanitizerProbe('server-key');

        $sanitized = $client->sanitizeForTest([
            // GET /payment-methods response shape
            [
                'paymentMethodId' => 'BTC-LN',
                'enabled' => true,
                'config' => ['connectionString' => 'type=boltz;server=https://boltz.example;macaroon=0201abcdefLIVEMAC'],
            ],
            [
                'paymentMethodId' => 'BTC-CHAIN',
                'config' => ['accountDerivation' => 'xpub6LIVEXPUB', 'label' => 'Hot wallet'],
            ],
            // PUT body shapes
            'connectionString' => 'type=lnd-rest;server=https://node;macaroon=0201PUTMAC',
            'currentPassword' => 'old-pass',
            'newPassword' => 'new-pass',
            'note' => 'forwarded: type=clightning;server=x;macaroon=0201STRAYMAC;allowinsecure=true',
        ]);

        $encoded = (string) json_encode($sanitized);

        foreach (['LIVEMAC', 'LIVEXPUB', 'PUTMAC', 'STRAYMAC', 'old-pass', 'new-pass'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertSame('BTC-LN', $sanitized[0]['paymentMethodId']);
        $this->assertTrue($sanitized[0]['enabled']);
        $this->assertStringContainsString('allowinsecure=true', $sanitized['note']);
    }
}

class BtcPayClientSanitizerProbe extends BtcPayClient
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeForTest(array $data): array
    {
        return $this->sanitizeData($data);
    }
}
