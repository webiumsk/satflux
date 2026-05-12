<?php

namespace Tests\Unit\Services;

use App\Services\GuestBtcPayDecommissioner;
use App\Services\GuestProvisioningService;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\BtcPay\WebhookService;
use Tests\TestCase;

class GuestProvisioningServiceTest extends TestCase
{
    public function test_provision_guest_aborts_when_btcpay_returns_no_api_key(): void
    {
        $userService = $this->createMock(UserService::class);
        $userService->method('createUser')->willReturn([
            'id' => 'btcpay-user-1',
            'emailConfirmed' => true,
        ]);
        $userService->expects($this->once())->method('createApiKey')->willReturn(['label' => 'ignored']);

        $storeService = $this->createMock(StoreService::class);
        $storeService->expects($this->never())->method('createStore');

        $webhookService = $this->createMock(WebhookService::class);
        $decommissioner = $this->createMock(GuestBtcPayDecommissioner::class);
        $decommissioner->expects($this->once())->method('decommissionPartial')
            ->with(null, 'btcpay-user-1', null);

        $svc = new GuestProvisioningService(
            $userService,
            $storeService,
            $webhookService,
            $decommissioner,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('merchant API key');

        $svc->provisionGuest();
    }
}
