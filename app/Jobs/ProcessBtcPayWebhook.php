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

        // Handle subscription lifecycle events
        if (
            in_array($eventType, [
                'SubscriptionExpired',
                'SubscriptionCancelled',
                'SubscriptionSuspended',
                'SubscriptionResumed',
                'SubscriptionCreated',
                'subscription.expired',
                'subscription.cancelled',
                'subscription.suspended',
                'subscription.resumed',
                'subscription.created',
            ])
        ) {
            $this->handleSubscriptionLifecycleEvent($payload, $eventType);
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

            // Get subscription ID from invoice (might be available even if plan ID is not)
            $subscriptionId = $invoiceData['metadata']['subscriptionId']
                ?? $invoiceData['subscriptionId']
                ?? $invoiceData['subscription']['id']
                ?? null;

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

            // Update user role and subscription tracking
            $oldRole = $user->role;
            $user->role = $planRole;

            // Store subscription ID if available from invoice
            if ($subscriptionId) {
                $user->btcpay_subscription_id = $subscriptionId;
            }

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

    /**
     * Handle subscription lifecycle events (expired, cancelled, etc.).
     */
    protected function handleSubscriptionLifecycleEvent(array $payload, string $eventType): void
    {
        try {
            $subscriptionData = $payload['subscriptionData'] ?? $payload['subscription'] ?? $payload;
            $subscriptionId = $subscriptionData['id'] ?? $subscriptionData['subscriptionId'] ?? null;

            if (!$subscriptionId) {
                Log::warning('Subscription lifecycle event missing subscription ID', [
                    'event_type' => $eventType,
                    'payload_keys' => array_keys($payload),
                ]);
                return;
            }

            // Get customer email from subscription
            $customerEmail = $subscriptionData['customerEmail']
                ?? $subscriptionData['subscriberEmail']
                ?? $subscriptionData['email']
                ?? $subscriptionData['metadata']['customerEmail']
                ?? $subscriptionData['metadata']['buyerEmail']
                ?? null;

            if (!$customerEmail) {
                Log::warning('Subscription lifecycle event missing customer email', [
                    'event_type' => $eventType,
                    'subscription_id' => $subscriptionId,
                ]);
                return;
            }

            // Find user by email
            $user = User::where('email', $customerEmail)->first();

            if (!$user) {
                Log::warning('Subscription lifecycle event - user not found', [
                    'event_type' => $eventType,
                    'subscription_id' => $subscriptionId,
                    'customer_email' => $customerEmail,
                ]);
                return;
            }

            // Get subscription expiration date
            $expiresAt = null;
            if (isset($subscriptionData['expiresAt'])) {
                $expiresAt = is_numeric($subscriptionData['expiresAt'])
                    ? date('Y-m-d H:i:s', $subscriptionData['expiresAt'])
                    : $subscriptionData['expiresAt'];
            } elseif (isset($subscriptionData['endDate'])) {
                $expiresAt = is_numeric($subscriptionData['endDate'])
                    ? date('Y-m-d H:i:s', $subscriptionData['endDate'])
                    : $subscriptionData['endDate'];
            }

            // Handle different event types
            $normalizedEventType = strtolower($eventType);
            if (str_contains($normalizedEventType, 'expired')) {
                $this->handleSubscriptionExpired($user, $subscriptionId, $expiresAt);
            } elseif (str_contains($normalizedEventType, 'cancelled') || str_contains($normalizedEventType, 'canceled')) {
                $this->handleSubscriptionCancelled($user, $subscriptionId, $expiresAt);
            } elseif (str_contains($normalizedEventType, 'suspended')) {
                $this->handleSubscriptionSuspended($user, $subscriptionId);
            } elseif (str_contains($normalizedEventType, 'resumed')) {
                $this->handleSubscriptionResumed($user, $subscriptionId, $expiresAt);
            } elseif (str_contains($normalizedEventType, 'created')) {
                $this->handleSubscriptionCreated($user, $subscriptionId, $expiresAt);
            }

        } catch (\Exception $e) {
            Log::error('Error processing subscription lifecycle event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Handle subscription expired event.
     * BTCPay handles grace period internally - we just sync the status.
     */
    protected function handleSubscriptionExpired(User $user, string $subscriptionId, ?string $expiresAt): void
    {
        // Fetch current subscription status from BTCPay to get accurate status
        // BTCPay manages grace period internally, so we need to check the actual status
        $storeId = config('services.btcpay.subscription_store_id');

        try {
            $subscriptionService = app(\App\Services\BtcPay\SubscriptionService::class);
            $subscription = $subscriptionService->getSubscription($storeId, $subscriptionId);

            $status = $subscription['status'] ?? null;

            // BTCPay status can be: active, expired, cancelled, suspended, etc.
            // If status is still "active" or in grace period, don't downgrade
            // If status is "expired" after grace period, downgrade
            if (!in_array($status, ['active', 'activeRenewing'])) {
                // Subscription is truly expired (after grace period) - downgrade
                $this->downgradeUserRole($user, "Subscription expired (status: {$status})");
            } else {
                // Still in grace period or active - just update expiration date
                $user->update([
                    'subscription_expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
                    'subscription_grace_period_ends_at' => null, // Let BTCPay handle grace period
                ]);
            }

            Log::info('Subscription expired event processed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'subscription_id' => $subscriptionId,
                'btcpay_status' => $status,
                'expires_at' => $expiresAt,
            ]);
        } catch (\Exception $e) {
            // If we can't fetch status, just update expiration and let cron job handle it later
            Log::warning('Could not fetch subscription status from BTCPay', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            $user->update([
                'subscription_expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
            ]);
        }
    }

    /**
     * Handle subscription cancelled event.
     */
    protected function handleSubscriptionCancelled(User $user, string $subscriptionId, ?string $expiresAt): void
    {
        // Cancel immediately - no grace period for cancelled subscriptions
        $this->downgradeUserRole($user, 'Subscription cancelled');

        Log::info('Subscription cancelled - user downgraded immediately', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'subscription_id' => $subscriptionId,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Handle subscription suspended event.
     */
    protected function handleSubscriptionSuspended(User $user, string $subscriptionId): void
    {
        // Suspend access - downgrade immediately
        $this->downgradeUserRole($user, 'Subscription suspended');

        Log::info('Subscription suspended - user downgraded', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Handle subscription resumed event.
     * Fetch subscription details from BTCPay to get current status and expiration.
     */
    protected function handleSubscriptionResumed(User $user, string $subscriptionId, ?string $expiresAt): void
    {
        $storeId = config('services.btcpay.subscription_store_id');

        try {
            $subscriptionService = app(\App\Services\BtcPay\SubscriptionService::class);
            $subscription = $subscriptionService->getSubscription($storeId, $subscriptionId);

            // Get expiration from subscription
            $subscriptionExpiresAt = $subscription['expiresAt']
                ?? $subscription['endDate']
                ?? $expiresAt;

            if ($subscriptionExpiresAt) {
                $subscriptionExpiresAt = is_numeric($subscriptionExpiresAt)
                    ? now()->parse($subscriptionExpiresAt)
                    : now()->parse($subscriptionExpiresAt);
            }

            // Determine plan from subscription
            $planId = $subscription['planId'] ?? null;
            $subscriptionPlans = config('services.btcpay.subscription_plans', []);
            $planRole = null;

            if ($planId === ($subscriptionPlans['pro'] ?? null)) {
                $planRole = 'pro';
            } elseif ($planId === ($subscriptionPlans['enterprise'] ?? null)) {
                $planRole = 'enterprise';
            }

            // Update user role and subscription tracking
            if ($planRole) {
                $user->update([
                    'role' => $planRole,
                    'subscription_expires_at' => $subscriptionExpiresAt,
                    'subscription_grace_period_ends_at' => null, // BTCPay handles grace period
                ]);
            } else {
                $user->update([
                    'subscription_expires_at' => $subscriptionExpiresAt,
                    'subscription_grace_period_ends_at' => null,
                ]);
            }

            Log::info('Subscription resumed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'subscription_id' => $subscriptionId,
                'plan_role' => $planRole,
                'expires_at' => $subscriptionExpiresAt,
            ]);
        } catch (\Exception $e) {
            Log::warning('Could not fetch subscription details on resume', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            // Fallback: just update expiration
            $user->update([
                'subscription_expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
                'subscription_grace_period_ends_at' => null,
            ]);
        }
    }

    /**
     * Handle subscription created event.
     * Fetch subscription details from BTCPay to get accurate expiration and plan.
     */
    protected function handleSubscriptionCreated(User $user, string $subscriptionId, ?string $expiresAt): void
    {
        $storeId = config('services.btcpay.subscription_store_id');

        try {
            $subscriptionService = app(\App\Services\BtcPay\SubscriptionService::class);
            $subscription = $subscriptionService->getSubscription($storeId, $subscriptionId);

            // Get expiration from subscription
            $subscriptionExpiresAt = $subscription['expiresAt']
                ?? $subscription['endDate']
                ?? $expiresAt;

            if ($subscriptionExpiresAt) {
                $subscriptionExpiresAt = is_numeric($subscriptionExpiresAt)
                    ? now()->parse($subscriptionExpiresAt)
                    : now()->parse($subscriptionExpiresAt);
            }

            $user->update([
                'btcpay_subscription_id' => $subscriptionId,
                'subscription_expires_at' => $subscriptionExpiresAt,
                'subscription_grace_period_ends_at' => null, // BTCPay handles grace period
            ]);

            Log::info('Subscription created - tracking started', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'subscription_id' => $subscriptionId,
                'expires_at' => $subscriptionExpiresAt,
            ]);
        } catch (\Exception $e) {
            Log::warning('Could not fetch subscription details on create', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            // Fallback: store what we have
            $user->update([
                'btcpay_subscription_id' => $subscriptionId,
                'subscription_expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
                'subscription_grace_period_ends_at' => null,
            ]);
        }
    }

    /**
     * Downgrade user role from pro/enterprise to free.
     * BTCPay has already handled grace period, so we can downgrade immediately.
     */
    protected function downgradeUserRole(User $user, string $reason): void
    {
        if (!in_array($user->role, ['pro', 'enterprise'])) {
            // User is already on free tier, nothing to downgrade
            return;
        }

        $oldRole = $user->role;
        $user->update([
            'role' => 'free',
            'btcpay_subscription_id' => null,
            'subscription_expires_at' => null,
            'subscription_grace_period_ends_at' => null, // Clear any old grace period tracking
        ]);

        Log::info('User role downgraded after subscription ended', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'old_role' => $oldRole,
            'new_role' => 'free',
            'reason' => $reason,
        ]);
    }
}







