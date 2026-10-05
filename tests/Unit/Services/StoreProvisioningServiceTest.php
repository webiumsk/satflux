<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\BtcPay\CashuService;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\LightningService;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\BtcPay\WebhookService;
use App\Services\StoreProvisioningService;
use App\Services\WalletConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StoreProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(StoreService $stores, UserService $users): StoreProvisioningService
    {
        config(['services.btcpay.api_key' => 'server-key']);
        $webhooks = $this->createMock(WebhookService::class);
        $webhooks->method('replacePanelWebhookForStore')->willReturn(['id' => 'webhook-id', 'secret' => 'test-secret']);
        $this->app->instance(WebhookService::class, $webhooks);

        return new StoreProvisioningService($stores, $users,
            $this->createMock(LightningService::class), $this->createMock(CashuService::class),
            $this->createMock(WalletConnectionService::class));
    }

    private function data(): array
    {
        return ['name' => 'First Store', 'default_currency' => 'EUR', 'timezone' => 'Europe/Vienna'];
    }

    public function test_failed_merchant_assignment_deletes_remote_store_and_does_not_consume_quota(): void
    {
        $user = User::factory()->create(['btcpay_user_id' => 'merchant-id']);
        $stores = $this->createMock(StoreService::class);
        $stores->expects($this->once())->method('createStore')->willReturn(['id' => 'remote-store']);
        $stores->expects($this->once())->method('addUserToStore')->with('remote-store', 'merchant-id', 'Owner')
            ->willThrowException(new BtcPayException('Invitation permission denied', 403));
        $stores->expects($this->once())->method('deleteStore')->with('remote-store', null);
        $users = $this->createMock(UserService::class);
        $users->expects($this->never())->method('getAdminBtcPayUserId');

        try {
            $this->service($stores, $users)->create($user, $this->data());
            $this->fail('Merchant ownership failure must abort store creation.');
        } catch (BtcPayException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('stores', 0);
    }

    public function test_missing_payment_identity_is_rejected_before_creating_remote_resources(): void
    {
        $stores = $this->createMock(StoreService::class);
        $stores->expects($this->never())->method('createStore');
        $service = $this->service($stores, $this->createMock(UserService::class));
        foreach (['btcpay_user_id', 'btcpay_api_key'] as $missing) {
            $user = User::factory()->create([$missing => null]);
            try {
                $service->create($user, $this->data());
                $this->fail('An incomplete payment account must not create a store.');
            } catch (HttpException $e) {
                $this->assertSame(503, $e->getStatusCode());
            }
        }
        $this->assertDatabaseCount('stores', 0);
    }

    public function test_support_assignment_failure_preserves_a_store_owned_by_the_merchant(): void
    {
        $user = User::factory()->create(['btcpay_user_id' => 'merchant-id']);
        $stores = $this->createMock(StoreService::class);
        $stores->method('createStore')->willReturn(['id' => 'remote-store']);
        $stores->expects($this->once())->method('addUserToStore')->with('remote-store', 'merchant-id', 'Owner')->willReturn([]);
        $stores->expects($this->never())->method('deleteStore');
        $users = $this->createMock(UserService::class);
        $users->method('getAdminBtcPayUserId')->willThrowException(new BtcPayException('Support unavailable', 503));

        $store = $this->service($stores, $users)->create($user, $this->data());

        $this->assertSame($user->id, $store->user_id);
        $this->assertDatabaseCount('stores', 1);
    }
}
