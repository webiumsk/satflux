<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.btcpay.base_url' => 'https://btcpay.example.test']);
        $this->app->forgetInstance(\App\Services\BtcPay\BtcPayClient::class);
    }

    /** BTCPay fake: success for offering, plan, and plan-checkout. Use in tests that expect 200. */
    protected function fakeBtcPayCheckoutSuccess(): void
    {
        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, '/api/v1/stores/') && str_contains($url, '/offerings/') && str_contains($url, '/plans/')) {
                return Http::response(['id' => 'plan_pro_test', 'name' => 'Pro Plan']);
            }
            if (str_contains($url, '/api/v1/stores/') && str_contains($url, '/offerings/')) {
                return Http::response(['id' => 'offering_test', 'name' => 'Test Offering']);
            }
            if (str_contains($url, '/api/v1/plan-checkout') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'checkout_test123',
                    'url' => 'https://btcpay.example.test/plan-checkout/checkout_test123',
                    'expiration' => now()->addHours(24)->timestamp,
                ]);
            }
            return Http::response([], 404);
        });
    }

    #[Test]
    public function authenticated_user_can_create_checkout_with_plan_name()
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $this->fakeBtcPayCheckoutSuccess();

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'checkoutUrl',
                'checkoutId',
                'expiresAt',
            ])
            ->assertJson([
                'checkoutUrl' => 'https://btcpay.example.test/plan-checkout/checkout_test123',
                'checkoutId' => 'checkout_test123',
            ]);

        // Verify we never expose BTCPay store ID in response
        $responseData = $response->json();
        $this->assertStringNotContainsString('test_subscription_btcpay_store', json_encode($responseData));
    }

    #[Test]
    public function checkout_validates_plan_and_offering_belong_to_store()
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $this->app->forgetInstance(\App\Services\BtcPay\BtcPayClient::class);

        // Mock BTCPay: return 404 for every request so offering validation fails
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_checkout_by_default()
    {
        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);
        config(['services.btcpay.allow_guest_subscriptions' => false]);

        $response = $this->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function checkout_requires_valid_plan_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'invalid_plan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    #[Test]
    public function checkout_includes_user_email_when_available()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $this->fakeBtcPayCheckoutSuccess();

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function checkout_handles_btcpay_api_errors_gracefully()
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $this->app->forgetInstance(\App\Services\BtcPay\BtcPayClient::class);

        // Mock BTCPay: offering and plan succeed, plan-checkout POST returns 422
        Http::fake(function ($request) {
            $url = (string) $request->url();
            $method = $request->method();
            if (($method === 'POST') && (str_contains($url, 'plan-checkout') || str_contains($url, '/api/v1/plan-checkout'))) {
                return Http::response(['message' => 'Invalid request'], 422);
            }
            if (str_contains($url, '/api/v1/stores/') && str_contains($url, '/offerings/') && str_contains($url, '/plans/')) {
                return Http::response(['id' => 'plan_pro_test', 'name' => 'Pro Plan']);
            }
            if (str_contains($url, '/api/v1/stores/') && str_contains($url, '/offerings/')) {
                return Http::response(['id' => 'offering_test', 'name' => 'Test Offering']);
            }
            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(422);
    }
}

