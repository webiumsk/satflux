<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBtcPayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public WebhookEvent $webhookEvent
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->webhookEvent->payload;
        $eventType = $this->webhookEvent->event_type;
        $storeId = $payload['storeId'] ?? null;

        // Check if this is a subscription store
        $subscriptionStoreId = config('services.btcpay.subscription_store_id');
        if ($storeId !== $subscriptionStoreId) {
            // Not a subscription-related webhook, skip
            $this->webhookEvent->markAsProcessed();
            return;
        }

        // Handle invoice.paid event for subscription invoices
        if ($eventType === 'InvoiceReceivedPayment' || $eventType === 'InvoiceSettled' || $eventType === 'invoice.paid') {
            $this->handleSubscriptionInvoicePaid($payload);
        }

        $this->webhookEvent->markAsProcessed();
    }

    /**
     * Handle subscription invoice payment.
     */
    protected function handleSubscriptionInvoicePaid(array $payload): void
    {
        try {
            $invoiceData = $payload['invoiceData'] ?? $payload['invoice'] ?? $payload;
            $invoiceId = $invoiceData['id'] ?? $invoiceData['invoiceId'] ?? null;

            if (!$invoiceId) {
                Log::warning('Subscription invoice payment webhook missing invoice ID', [
                    'payload_keys' => array_keys($payload),
                ]);
                return;
            }

            // Get customer email from invoice
            $customerEmail = $invoiceData['metadata']['customerEmail'] 
                ?? $invoiceData['metadata']['buyerEmail']
                ?? $invoiceData['buyerEmail'] 
                ?? $invoiceData['customerEmail']
                ?? null;

            if (!$customerEmail) {
                Log::warning('Subscription invoice payment webhook missing customer email', [
                    'invoice_id' => $invoiceId,
                ]);
                return;
            }

            // Find user by email
            $user = User::where('email', $customerEmail)->first();

            if (!$user) {
                Log::warning('Subscription invoice payment webhook - user not found', [
                    'invoice_id' => $invoiceId,
                    'customer_email' => $customerEmail,
                ]);
                return;
            }

            // Determine which plan was paid for by checking invoice metadata
            // Try multiple possible locations for plan information
            $planId = $invoiceData['metadata']['planId'] 
                ?? $invoiceData['metadata']['plan_id']
                ?? $invoiceData['metadata']['subscriptionPlanId']
                ?? $invoiceData['subscriptionPlanId']
                ?? null;

            // Map plan ID to role
            $subscriptionPlans = config('services.btcpay.subscription_plans', []);
            $planRole = null;

            if ($planId && $planId === ($subscriptionPlans['pro'] ?? null)) {
                $planRole = 'pro';
            } elseif ($planId && $planId === ($subscriptionPlans['enterprise'] ?? null)) {
                $planRole = 'enterprise';
            } else {
                // If plan ID not found, check if invoice is from subscription store
                // and try to fetch subscription details or determine from other metadata
                Log::info('Subscription invoice payment - plan ID not found in metadata', [
                    'invoice_id' => $invoiceId,
                    'customer_email' => $customerEmail,
                    'plan_id_from_metadata' => $planId,
                    'metadata' => $invoiceData['metadata'] ?? [],
                ]);

                // Try to get subscription info from invoice metadata
                $subscriptionId = $invoiceData['metadata']['subscriptionId'] 
                    ?? $invoiceData['subscriptionId']
                    ?? null;

                if ($subscriptionId) {
                    // We could fetch subscription details from BTCPay API here
                    // For now, log and require manual intervention
                    Log::warning('Subscription invoice paid but plan cannot be determined - manual review needed', [
                        'invoice_id' => $invoiceId,
                        'subscription_id' => $subscriptionId,
                        'customer_email' => $customerEmail,
                    ]);
                }

                // Skip if we can't determine the plan
                return;
            }

            if (!$planRole) {
                Log::warning('Subscription invoice payment - could not determine plan role', [
                    'invoice_id' => $invoiceId,
                    'customer_email' => $customerEmail,
                    'plan_id' => $planId,
                ]);
                return;
            }

            // Update user role
            $oldRole = $user->role;
            $user->role = $planRole;
            $user->save();

            Log::info('User role updated after subscription payment', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $planRole,
                'invoice_id' => $invoiceId,
                'plan_id' => $planId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing subscription invoice payment webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
        }
    }
}







