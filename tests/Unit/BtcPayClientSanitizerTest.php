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
