<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProcessBtcPayWebhookTest extends TestCase
{
    use RefreshDatabase;

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
        User::factory()->create([
            'email' => 'customer@example.com',
            'role' => 'merchant',
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
}
