<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BtcPayConfigBotSupportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_support_user_with_btcpay_config_bot_token_can_list_wallet_connections(): void
    {
        $user = User::factory()->support()->unverified()->create();
        $token = $user->createToken('btcpay-config-bot');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/support/wallet-connections?status=pending');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_unverified_support_user_with_other_token_gets_403(): void
    {
        $user = User::factory()->support()->unverified()->create();
        $token = $user->createToken('other-client');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/support/wallet-connections?status=pending');

        $response->assertForbidden();
    }

    public function test_unverified_merchant_with_btcpay_config_bot_token_gets_403_from_support_middleware(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'free']);
        $token = $user->createToken('btcpay-config-bot');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/support/wallet-connections?status=pending');

        $response->assertForbidden();
    }
}
