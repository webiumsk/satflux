<?php

namespace Tests\Unit\Services\BtcPay;

use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\StoreService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BTCPay 2.4.4 (btcpayserver#7519): POST /stores/{id}/users creates a pending
 * invitation unless `requireInvitation` is false. Merchants never see BTCPay's
 * UI, so provisioning must keep adding them directly.
 */
class StoreServiceAddUserTest extends TestCase
{
    #[Test]
    public function adding_a_store_user_opts_out_of_the_invitation_flow(): void
    {
        Http::fake(fn () => Http::response(['userId' => 'u-1', 'role' => 'Owner'], 200));

        $result = $this->service()->addUserToStore('store-1', 'u-1', 'Owner');

        $this->assertSame('u-1', $result['userId']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/stores/store-1/users')
            && $request['userId'] === 'u-1'
            && $request['role'] === 'Owner'
            && $request['requireInvitation'] === false);
    }

    #[Test]
    public function an_already_added_user_is_resolved_from_the_244_compact_shape(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['code' => 'already-store-user', 'message' => 'The user is already added to the store'], 409);
            }

            return Http::response([
                ['id' => 'u-1', 'email' => 'merchant@example.com', 'roleId' => 'Owner'],
            ], 200);
        });

        $result = $this->service()->addUserToStore('store-1', 'u-1', 'Owner');

        $this->assertSame('u-1', $result['id']);
        $this->assertSame('Owner', $result['roleId']);
    }

    #[Test]
    public function a_missing_server_permission_for_direct_adds_is_rethrown(): void
    {
        Http::fake(fn () => Http::response([
            'code' => 'missing-permission',
            'message' => 'You are not allowed to add users without invitation',
        ], 403));

        try {
            $this->service()->addUserToStore('store-1', 'u-1', 'Owner');
            $this->fail('Expected BtcPayException');
        } catch (BtcPayException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function a_pre_244_duplicate_role_conflict_is_resolved_from_the_legacy_shape(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['code' => 'duplicate-store-user-role', 'message' => 'The user is already added to the store'], 409);
            }

            return Http::response([
                ['userId' => 'u-1', 'email' => 'merchant@example.com', 'storeRole' => 'Owner'],
            ], 200);
        });

        $result = $this->service()->addUserToStore('store-1', 'u-1', 'Owner');

        $this->assertSame('u-1', $result['userId']);
    }

    #[Test]
    public function a_409_without_a_membership_code_is_rethrown(): void
    {
        Http::fake(fn () => Http::response(['code' => 'store-user-role-orphaned', 'message' => 'Removing this user would result in the store having no owner.'], 409));

        try {
            $this->service()->addUserToStore('store-1', 'u-1', 'Owner');
            $this->fail('Expected BtcPayException');
        } catch (BtcPayException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('store-user-role-orphaned', $e->getErrorCode());
        }

        Http::assertNotSent(fn ($request) => $request->method() === 'GET');
    }

    #[Test]
    public function a_membership_conflict_for_a_user_missing_from_the_store_is_rethrown(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['code' => 'already-store-user', 'message' => 'The user is already added to the store'], 409);
            }

            return Http::response([
                ['id' => 'someone-else', 'email' => 'other@example.com', 'roleId' => 'Owner'],
            ], 200);
        });

        try {
            $this->service()->addUserToStore('store-1', 'u-1', 'Owner');
            $this->fail('Expected BtcPayException');
        } catch (BtcPayException $e) {
            $this->assertSame('already-store-user', $e->getErrorCode());
        }
    }

    private function service(): StoreService
    {
        return new StoreService(new BtcPayClient('server-key'));
    }
}
