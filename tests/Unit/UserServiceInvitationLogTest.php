<?php

namespace Tests\Unit;

use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserServiceInvitationLogTest extends TestCase
{
    #[Test]
    public function it_does_not_log_invitation_approval_codes_when_falling_back_to_direct_url(): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.example.com']);

        $client = Mockery::mock(BtcPayClient::class);
        $client->shouldReceive('post')
            ->twice()
            ->andThrow(new BtcPayException('404 Not Found', 404));

        Http::fake([
            'https://btcpay.example.com/invite/invite-id/live-approval-code' => Http::response('', 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'BTCPay invitation accepted via direct URL call'
                    && ($context['invite_id'] ?? null) === 'invite-id'
                    && ! array_key_exists('url', $context)
                    && ! str_contains(json_encode($context), 'live-approval-code');
            });

        $service = new UserService($client);

        $this->assertTrue($service->acceptInvitation(
            'https://btcpay.example.com/invite/invite-id/live-approval-code',
        ));
    }
}
