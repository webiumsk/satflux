<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    protected function fakeBtcPayCheckoutSuccess(int $trialDays = 30): void
    {
        Http::fake(function ($request) use ($trialDays) {
            $url = (string) $request->url();
            if (str_contains($url, '/api/v1/stores/') && str_contains($url, '/offerings/') && str_contains($url, '/plans/')) {
                return Http::response([
                    'id' => 'plan_pro_test',
                    'name' => 'Pro Plan',
                    'trialDays' => $trialDays,
                ]);
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
    public function guest_user_cannot_create_subscription_checkout(): void
    {
        Http::fake();

        $guest = User::factory()->guest()->create();

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $response = $this->actingAs($guest)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'guest_subscription_blocked');

        Http::assertNothingSent();
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
    public function checkout_requests_btcpay_trial_when_plan_has_trial_days(): void
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $this->fakeBtcPayCheckoutSuccess(trialDays: 30);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains((string) $request->url(), '/api/v1/plan-checkout')) {
                return false;
            }

            $body = $request->data();

            return ($body['isTrial'] ?? false) === true;
        });
    }

    #[Test]
    public function checkout_does_not_request_btcpay_trial_when_plan_has_no_trial_days(): void
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        $this->fakeBtcPayCheckoutSuccess(trialDays: 0);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains((string) $request->url(), '/api/v1/plan-checkout')) {
                return false;
            }

            $body = $request->data();

            return ! array_key_exists('isTrial', $body);
        });
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

    #[Test]
    public function authenticated_user_can_create_credit_purchase_checkout(): void
    {
        $user = User::factory()->create([
            'email' => 'subscriber@example.com',
        ]);

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_pro_test']);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, '/subscribers/subscriber%40example.com') && $request->method() === 'GET') {
                return Http::response([
                    'plan' => ['id' => 'plan_pro_test', 'name' => 'Pro Plan'],
                    'isActive' => true,
                ]);
            }
            if (str_contains($url, '/api/v1/plan-checkout') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'checkout_credit123',
                    'url' => 'https://btcpay.example.test/plan-checkout/checkout_credit123',
                    'invoiceId' => 'inv_credit123',
                    'expiration' => now()->addHours(24)->timestamp,
                ]);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->postJson('/api/subscriptions/credits', [
            'amount' => 5000,
            'currency' => 'SATS',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'checkoutUrl' => 'https://btcpay.example.test/plan-checkout/checkout_credit123',
                'checkoutId' => 'checkout_credit123',
                'invoiceId' => 'inv_credit123',
                'invoiceUrl' => 'https://btcpay.example.test/i/inv_credit123',
            ]);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains((string) $request->url(), '/api/v1/plan-checkout')) {
                return false;
            }

            $body = $request->data();

            return ($body['creditPurchase'] ?? null) === '5000'
                && ($body['customerSelector'] ?? null) === 'subscriber@example.com'
                && ($body['planId'] ?? null) === 'plan_pro_test'
                && ($body['isTrial'] ?? null) === false;
        });
    }

    #[Test]
    public function credit_purchase_requires_active_subscription(): void
    {
        $user = User::factory()->create([
            'email' => 'nosub@example.com',
        ]);

        config(['services.btcpay.subscription_store_id' => 'test_subscription_btcpay_store']);
        config(['services.btcpay.subscription_offering_id' => 'offering_test']);

        Http::fake([
            '*' => Http::response([], 404),
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/credits', [
            'amount' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Active subscription required before purchasing credits.');

        Http::assertNotSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains((string) $request->url(), '/api/v1/plan-checkout');
        });
    }
}
