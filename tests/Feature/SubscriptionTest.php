<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock BTCPay API responses
        Http::fake([
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*' => Http::response([
                'id' => 'offering_GpWCnNRm6W9qqmgwdC',
                'name' => 'Test Offering',
            ]),
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*/plans/*' => Http::response([
                'id' => 'plan_9UQMqk4vbAFyQinRpL',
                'name' => 'Pro Plan',
            ]),
            'pay.dvadsatjeden.org/api/v1/plan-checkout' => Http::response([
                'checkoutId' => 'checkout_test123',
                'checkoutUrl' => 'https://pay.dvadsatjeden.org/plan-checkout/checkout_test123',
                'expiresAt' => now()->addHours(24)->toIso8601String(),
            ]),
        ]);
    }

    /** @test */
    public function authenticated_user_can_create_checkout_with_plan_name()
    {
        $user = User::factory()->create();

        // Set subscription store ID directly in config (no local Store record needed)
        config(['services.btcpay.subscription_store_id' => 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY']);
        config(['services.btcpay.subscription_offering_id' => 'offering_GpWCnNRm6W9qqmgwdC']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_9UQMqk4vbAFyQinRpL']);

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
                'checkoutUrl' => 'https://pay.dvadsatjeden.org/plan-checkout/checkout_test123',
                'checkoutId' => 'checkout_test123',
            ]);

        // Verify we never expose BTCPay store ID in response
        $responseData = $response->json();
        $this->assertStringNotContainsString('GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY', json_encode($responseData));
    }

    /** @test */
    public function checkout_validates_plan_and_offering_belong_to_store()
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY']);
        config(['services.btcpay.subscription_offering_id' => 'offering_GpWCnNRm6W9qqmgwdC']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_9UQMqk4vbAFyQinRpL']);

        // Mock BTCPay to return 404 for invalid plan
        Http::fake([
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*' => Http::response([], 404),
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_checkout_by_default()
    {
        config(['services.btcpay.subscription_store_id' => 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_9UQMqk4vbAFyQinRpL']);
        config(['services.btcpay.allow_guest_subscriptions' => false]);

        $response = $this->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function checkout_requires_valid_plan_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'invalid_plan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    /** @test */
    public function checkout_includes_user_email_when_available()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        config(['services.btcpay.subscription_store_id' => 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY']);
        config(['services.btcpay.subscription_offering_id' => 'offering_GpWCnNRm6W9qqmgwdC']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_9UQMqk4vbAFyQinRpL']);

        Http::fake([
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*' => Http::response(['id' => 'offering_GpWCnNRm6W9qqmgwdC']),
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*/plans/*' => Http::response(['id' => 'plan_9UQMqk4vbAFyQinRpL']),
            'pay.dvadsatjeden.org/api/v1/plan-checkout' => Http::response([
                'checkoutId' => 'checkout_test123',
                'checkoutUrl' => 'https://pay.dvadsatjeden.org/plan-checkout/checkout_test123',
            ]),
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function checkout_handles_btcpay_api_errors_gracefully()
    {
        $user = User::factory()->create();

        config(['services.btcpay.subscription_store_id' => 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY']);
        config(['services.btcpay.subscription_offering_id' => 'offering_GpWCnNRm6W9qqmgwdC']);
        config(['services.btcpay.subscription_plans.pro' => 'plan_9UQMqk4vbAFyQinRpL']);

        // Mock BTCPay API error
        Http::fake([
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*' => Http::response(['id' => 'offering_GpWCnNRm6W9qqmgwdC']),
            'pay.dvadsatjeden.org/api/v1/stores/*/offerings/*/plans/*' => Http::response(['id' => 'plan_9UQMqk4vbAFyQinRpL']),
            'pay.dvadsatjeden.org/api/v1/plan-checkout' => Http::response([
                'message' => 'Invalid request',
            ], 422),
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscriptions/checkout', [
            'plan' => 'pro',
        ]);

        $response->assertStatus(422);
    }
}

