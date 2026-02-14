<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing subscriptions and feature flags.
 * 
 * IMPORTANT: This is a non-custodial system. Feature flags only affect
 * UX/management features, never payment acceptance or existing infrastructure.
 */
class SubscriptionService
{
    /**
     * Get or create a FREE subscription for a user.
     */
    public function ensureFreeSubscription(User $user): Subscription
    {
        // Check if user already has an active subscription
        $existingSubscription = $user->currentSubscription();
        if ($existingSubscription && $existingSubscription->isActive()) {
            return $existingSubscription;
        }

        // Get FREE plan
        $freePlan = SubscriptionPlan::where('code', 'free')->orWhere('name', 'free')->first();
        if (!$freePlan) {
            throw new \Exception('FREE subscription plan not found. Please run the seeder.');
        }

        // Create FREE subscription (never expires)
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYears(100), // Effectively never expires
        ]);

        Log::info('Created FREE subscription for user', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        return $subscription;
    }

    /**
     * Activate or extend subscription for a user.
     * Called when payment is successful.
     * Uses a transaction and pessimistic lock to prevent race conditions when
     * multiple webhooks or callbacks arrive simultaneously.
     */
    public function activateSubscription(User $user, string $planName, ?string $btcpaySubscriptionId = null): Subscription
    {
        $plan = SubscriptionPlan::where('code', $planName)->orWhere('name', $planName)->first();
        if (!$plan) {
            throw new \Exception("Subscription plan '{$planName}' not found.");
        }

        return DB::transaction(function () use ($user, $plan, $planName, $btcpaySubscriptionId) {
            // Lock user row to serialize concurrent webhooks for the same user
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                throw new \Exception("User not found.");
            }

            // Get existing active subscription under lock (same user, so lock is held)
            $existingSubscription = Subscription::where('user_id', $lockedUser->id)
                ->whereIn('status', ['active', 'grace'])
                ->orderBy('expires_at', 'desc')
                ->lockForUpdate()
                ->first();

            if ($existingSubscription && $existingSubscription->plan->name === $planName) {
                $existingSubscription->extendOneYear();
                if ($btcpaySubscriptionId) {
                    $existingSubscription->btcpay_subscription_id = $btcpaySubscriptionId;
                    $existingSubscription->save();
                }

                Log::info('Extended subscription', [
                    'user_id' => $lockedUser->id,
                    'plan' => $planName,
                    'expires_at' => $existingSubscription->expires_at,
                ]);

                return $existingSubscription;
            }

            // Create new subscription
            $startsAt = now();
            $expiresAt = $startsAt->copy()->addYear();
            $graceEndsAt = $expiresAt->copy()->addDays(14);

            $subscription = Subscription::create([
                'user_id' => $lockedUser->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'grace_ends_at' => $graceEndsAt,
                'btcpay_subscription_id' => $btcpaySubscriptionId,
            ]);

            Log::info('Created new subscription', [
                'user_id' => $lockedUser->id,
                'plan' => $planName,
                'expires_at' => $expiresAt,
            ]);

            return $subscription;
        });
    }

    /**
     * Check if user can use XLSX export (Pro+ or admin/support).
     */
    public function canUseXlsxExport(User $user): bool
    {
        if ($user->hasUnlimitedAccess()) {
            return true;
        }
        $plan = $user->currentSubscriptionPlan();
        return in_array($plan?->code ?? '', ['pro', 'enterprise'], true);
    }

    /**
     * Check if user can access exports section (history, automatic exports).
     * Admin and support always have access.
     */
    public function canAccessExports(User $user): bool
    {
        if ($user->hasUnlimitedAccess()) {
            return true;
        }
        $plan = $user->currentSubscriptionPlan();
        return $plan?->hasFeature('automatic_csv_exports') ?? false;
    }

    /**
     * Check if user can use automatic (monthly) exports.
     */
    public function canUseAutomaticExports(User $user): bool
    {
        $plan = $user->currentSubscriptionPlan();
        return $plan?->hasFeature('automatic_csv_exports') ?? false;
    }

    /**
     * Check if user can enable cash/card (offline) payment methods in PoS.
     */
    public function canUseOfflinePaymentMethods(User $user): bool
    {
        $plan = $user->currentSubscriptionPlan();
        return $plan?->hasFeature('offline_payment_methods') ?? false;
    }

    /**
     * Check if user can view advanced statistics.
     */
    public function canViewAdvancedStats(User $user): bool
    {
        $plan = $user->currentSubscriptionPlan();
        return $plan?->hasFeature('advanced_statistics') ?? false;
    }

    /**
     * Check if user can manage store users (per-store user management).
     */
    public function canManageStoreUsers(User $user): bool
    {
        $plan = $user->currentSubscriptionPlan();
        return $plan?->hasFeature('per_store_user_management') ?? false;
    }

    /**
     * Update subscription statuses for all users.
     * This should be called periodically (e.g., via a scheduled task).
     */
    public function updateAllSubscriptionStatuses(): void
    {
        $subscriptions = Subscription::whereIn('status', ['active', 'grace'])
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->updateStatus();
        }

        Log::info('Updated subscription statuses', [
            'count' => $subscriptions->count(),
        ]);
    }
}

