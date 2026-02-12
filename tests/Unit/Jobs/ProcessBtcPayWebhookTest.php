<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProcessBtcPayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed subscription plans needed by SubscriptionService::activateSubscription()
        $this->seed(SubscriptionPlanSeeder::class);
    }

    /** @test */
    public function handle_marks_event_as_processed_when_store_is_not_subscription_store(): void
    {
        config(['services.btcpay.subscription_store_id' => 'subscription-store-123']);
        $event = WebhookEvent::create([
            'event_type' => 'InvoiceSettled',
            'payload' => [
                'storeId' => 'other-store-456',
                'invoiceData' => ['id' => 'inv-1'],
            ],
            'verified' => true,
        ]);

        $job = new ProcessBtcPayWebhook($event);
        $job->handle();

        $event->refresh();
        $this->assertNotNull($event->processed_at);
    }

    /** @test */
    public function handle_processes_subscription_invoice_paid_event_and_marks_complete(): void
    {
        Config::set('services.btcpay.subscription_store_id', 'sub-store-123');
        Config::set('services.btcpay.subscription_plans.pro', 'plan-pro-123');
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'role' => 'free',
        ]);
        $event = WebhookEvent::create([
            'event_type' => 'InvoiceSettled',
            'payload' => [
                'storeId' => 'sub-store-123',
                'invoiceData' => [
                    'id' => 'inv-1',
                    'metadata' => [
                        'customerEmail' => 'customer@example.com',
                        'planId' => 'plan-pro-123',
                    ],
                ],
            ],
            'verified' => true,
        ]);

        $job = new ProcessBtcPayWebhook($event);
        $job->handle();

        $event->refresh();
        $this->assertNotNull($event->processed_at);

        // Verify user role was updated via SubscriptionService
        $user->refresh();
        $this->assertSame('pro', $user->role);

        // Verify a subscription record was created
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function handle_does_not_update_user_when_customer_email_not_found(): void
    {
        config(['services.btcpay.subscription_store_id' => 'sub-store-123']);
        config(['services.btcpay.subscription_plans.pro' => 'plan-pro-123']);
        $event = WebhookEvent::create([
            'event_type' => 'InvoiceSettled',
            'payload' => [
                'storeId' => 'sub-store-123',
                'invoiceData' => [
                    'id' => 'inv-1',
                    'metadata' => [
                        'customerEmail' => 'unknown@example.com',
                        'planId' => 'plan-pro-123',
                    ],
                ],
            ],
            'verified' => true,
        ]);

        $job = new ProcessBtcPayWebhook($event);
        $job->handle();

        $event->refresh();
        $this->assertNotNull($event->processed_at);
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
    }

    /** @test */
    public function handle_is_idempotent_with_same_subscription_id(): void
    {
        Config::set('services.btcpay.subscription_store_id', 'sub-store-123');
        Config::set('services.btcpay.subscription_plans.pro', 'plan-pro-123');
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'role' => 'free',
        ]);

        $payload = [
            'storeId' => 'sub-store-123',
            'invoiceData' => [
                'id' => 'inv-1',
                'metadata' => [
                    'customerEmail' => 'customer@example.com',
                    'planId' => 'plan-pro-123',
                    'subscriptionId' => 'sub-unique-123',
                ],
                'subscriptionId' => 'sub-unique-123',
            ],
        ];

        // Process the same webhook event twice
        $event1 = WebhookEvent::create([
            'event_type' => 'InvoiceSettled',
            'payload' => $payload,
            'verified' => true,
        ]);
        (new ProcessBtcPayWebhook($event1))->handle();

        $event2 = WebhookEvent::create([
            'event_type' => 'InvoiceSettled',
            'payload' => $payload,
            'verified' => true,
        ]);
        (new ProcessBtcPayWebhook($event2))->handle();

        // Should have exactly 1 subscription, not 2
        $this->assertSame(1, Subscription::where('user_id', $user->id)
            ->where('btcpay_subscription_id', 'sub-unique-123')
            ->count());
    }
}
