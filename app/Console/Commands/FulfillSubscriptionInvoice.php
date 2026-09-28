<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BtcPay\SubscriptionService;
use App\Services\Invoicing\SubscriptionBillingInvoiceService;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FulfillSubscriptionInvoice extends Command
{
    protected $signature = 'subscriptions:fulfill-invoice
                            {invoiceId : BTCPay invoice id on the subscription store}
                            {--user-id= : Local user id (optional if email is on the invoice)}
                            {--email= : Subscriber email override}
                            {--plan= : Plan role override (pro|enterprise)}';

    protected $description = 'Manually activate a subscription and issue billing invoice for a settled BTCPay invoice';

    public function handle(
        SubscriptionService $btcpaySubscriptionService,
        SubscriptionEntitlementService $subscriptionService,
        SubscriptionBillingInvoiceService $billingInvoiceService,
    ): int {
        $invoiceId = $this->argument('invoiceId');
        $storeId = config('services.btcpay.subscription_store_id');

        if (! $storeId) {
            $this->error('SUBSCRIPTION_STORE_ID is not configured.');

            return Command::FAILURE;
        }

        // Fresh read, Settled only: applying an invoice records who it paid for,
        // so a premature run would lock the eventual payer out of it.
        $invoice = $btcpaySubscriptionService->fetchSettledInvoice($storeId, (string) $invoiceId);
        if ($invoice === null) {
            $this->error("Invoice {$invoiceId} could not be fetched or is not Settled; nothing was applied.");

            return Command::FAILURE;
        }

        $metadata = $invoice['metadata'] ?? [];
        $customerEmail = $this->option('email')
            ?? $metadata['customerEmail']
            ?? $metadata['buyerEmail']
            ?? $invoice['buyerEmail']
            ?? $invoice['customerEmail']
            ?? null;

        $user = null;
        if ($this->option('user-id')) {
            $user = User::query()->find($this->option('user-id'));
        }
        if (! $user && $customerEmail) {
            $user = User::where('email', $customerEmail)->first();
        }

        if (! $user) {
            $this->error('User not found. Pass --user-id or --email, or ensure invoice metadata has customerEmail.');

            return Command::FAILURE;
        }

        $planRole = $this->option('plan');
        if (! $planRole) {
            $planId = $metadata['planId']
                ?? $metadata['plan_id']
                ?? $metadata['subscriptionPlanId']
                ?? null;

            $subscriptionPlans = config('services.btcpay.subscription_plans', []);
            if ($planId === ($subscriptionPlans['pro'] ?? null)) {
                $planRole = 'pro';
            } elseif ($planId === ($subscriptionPlans['enterprise'] ?? null)) {
                $planRole = 'enterprise';
            }
        }

        if (! in_array($planRole, ['pro', 'enterprise'], true)) {
            $this->error('Could not determine plan. Pass --plan=pro or --plan=enterprise.');

            return Command::FAILURE;
        }

        $subscriptionId = $metadata['subscriptionId']
            ?? $invoice['subscriptionId']
            ?? ($invoice['subscription']['id'] ?? null);

        $createdTime = $invoice['createdTime'] ?? null;
        try {
            $subscription = $subscriptionService->activateSubscriptionForInvoice(
                $user,
                $planRole,
                (string) $invoiceId,
                $subscriptionId,
                is_numeric($createdTime) ? Carbon::createFromTimestamp((int) $createdTime) : null,
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $oldRole = $user->role;
        $user->role = $planRole;
        if ($subscriptionId) {
            $user->btcpay_subscription_id = $subscriptionId;
        }
        $user->save();

        $billingDoc = null;
        try {
            $billingDoc = $billingInvoiceService->fulfillPaidInvoice(
                $user,
                $planRole,
                $invoiceId,
                $invoice,
            );
        } catch (\Throwable $e) {
            $this->warn("Billing invoice failed: {$e->getMessage()}");
            Log::error('subscriptions:fulfill-invoice billing failed', [
                'user_id' => $user->id,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }

        $this->info("Activated {$user->email}: {$oldRole} -> {$planRole}");
        $this->line("Subscription row: {$subscription->id}, expires {$subscription->expires_at}");
        if ($billingDoc) {
            $this->line("Billing document: {$billingDoc->id} ({$billingDoc->document_number})");
        } else {
            $this->warn('No billing document created (check SUBSCRIPTION_BILLING_COMPANY_ID and logs).');
        }

        return Command::SUCCESS;
    }
}
