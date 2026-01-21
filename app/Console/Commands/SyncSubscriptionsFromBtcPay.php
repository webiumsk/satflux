<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BtcPay\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionsFromBtcPay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync-from-btcpay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all subscriptions from BTCPay and sync them with users by email';

    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fetching subscriptions from BTCPay...');

        $storeId = config('services.btcpay.subscription_store_id');
        
        try {
            // Fetch all subscriptions from BTCPay
            $subscriptions = $this->subscriptionService->listSubscriptions($storeId);
            
            // BTCPay API might return array of subscriptions or wrapped in a data property
            $subscriptionsList = $subscriptions['data'] ?? $subscriptions;
            
            if (!is_array($subscriptionsList)) {
                $this->error('Unexpected response format from BTCPay API');
                Log::error('Unexpected subscription list format', ['response' => $subscriptions]);
                return Command::FAILURE;
            }

            $this->info("Found " . count($subscriptionsList) . " subscriptions in BTCPay");

            $synced = 0;
            $upgraded = 0;
            $notFound = 0;

            foreach ($subscriptionsList as $subscriptionData) {
                $subscriptionId = $subscriptionData['id'] ?? null;
                
                if (!$subscriptionId) {
                    $this->warn('Subscription without ID found, skipping...');
                    continue;
                }

                // Get customer email from subscription
                $customerEmail = $subscriptionData['customerEmail'] 
                    ?? $subscriptionData['subscriberEmail']
                    ?? $subscriptionData['email']
                    ?? $subscriptionData['metadata']['customerEmail']
                    ?? $subscriptionData['metadata']['buyerEmail']
                    ?? null;

                if (!$customerEmail) {
                    $this->warn("Subscription {$subscriptionId} has no customer email, skipping...");
                    $notFound++;
                    continue;
                }

                // Find user by email
                $user = User::where('email', $customerEmail)->first();

                if (!$user) {
                    $this->warn("User with email {$customerEmail} not found for subscription {$subscriptionId}");
                    $notFound++;
                    continue;
                }

                // Get subscription status and plan
                $status = $subscriptionData['status'] ?? null;
                $planId = $subscriptionData['planId'] ?? null;
                
                // Get expiration date
                $expiresAt = null;
                if (isset($subscriptionData['expiresAt'])) {
                    $expiresAt = is_numeric($subscriptionData['expiresAt']) 
                        ? now()->parse($subscriptionData['expiresAt'])
                        : now()->parse($subscriptionData['expiresAt']);
                } elseif (isset($subscriptionData['endDate'])) {
                    $expiresAt = is_numeric($subscriptionData['endDate']) 
                        ? now()->parse($subscriptionData['endDate'])
                        : now()->parse($subscriptionData['endDate']);
                }

                // Map plan ID to role
                $subscriptionPlans = config('services.btcpay.subscription_plans', []);
                $expectedRole = null;

                if ($planId === ($subscriptionPlans['pro'] ?? null)) {
                    $expectedRole = 'pro';
                } elseif ($planId === ($subscriptionPlans['enterprise'] ?? null)) {
                    $expectedRole = 'enterprise';
                }

                // Update user
                $updateData = [
                    'btcpay_subscription_id' => $subscriptionId,
                    'subscription_expires_at' => $expiresAt,
                    'subscription_grace_period_ends_at' => null, // BTCPay handles grace period
                ];

                $oldRole = $user->role;
                
                // Only update role if subscription is active
                if (in_array($status, ['active', 'activeRenewing']) && $expectedRole) {
                    $updateData['role'] = $expectedRole;
                    
                    if ($oldRole !== $expectedRole) {
                        $upgraded++;
                        $this->line("Upgraded {$user->email} from {$oldRole} to {$expectedRole} (subscription: {$subscriptionId})");
                    }
                } elseif (in_array($status, ['expired', 'cancelled', 'suspended'])) {
                    // Subscription is not active - downgrade if user has paid role
                    if (in_array($oldRole, ['pro', 'enterprise'])) {
                        $updateData['role'] = 'merchant';
                        $updateData['btcpay_subscription_id'] = null; // Clear subscription ID
                        $this->line("Downgraded {$user->email} from {$oldRole} to merchant (subscription expired: {$subscriptionId})");
                    }
                }

                $user->update($updateData);
                $synced++;

                Log::info('Synced subscription from BTCPay', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'subscription_id' => $subscriptionId,
                    'status' => $status,
                    'old_role' => $oldRole,
                    'new_role' => $user->fresh()->role,
                ]);
            }

            $this->info("Synced {$synced} subscriptions");
            if ($upgraded > 0) {
                $this->info("Upgraded {$upgraded} users");
            }
            if ($notFound > 0) {
                $this->warn("Could not match {$notFound} subscriptions to users");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error fetching subscriptions: {$e->getMessage()}");
            Log::error('Error syncing subscriptions from BTCPay', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}

