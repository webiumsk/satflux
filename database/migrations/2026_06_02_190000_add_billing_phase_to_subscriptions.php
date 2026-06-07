<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_phase', 20)->default('paid')->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('expires_at');
            $table->index('billing_phase');
            $table->index('trial_ends_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('trial_consumed_at')->nullable()->after('subscription_grace_period_ends_at');
            $table->index('trial_consumed_at');
        });

        $this->backfillBillingPhases();
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_phase']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropColumn(['billing_phase', 'trial_ends_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['trial_consumed_at']);
            $table->dropColumn('trial_consumed_at');
        });
    }

    protected function backfillBillingPhases(): void
    {
        $trialDays = (int) config('pricing.trial_days', 30);
        $proPlanIds = SubscriptionPlan::query()
            ->whereIn('code', ['pro', 'enterprise'])
            ->pluck('id');

        Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->update(['billing_phase' => Subscription::BILLING_TRIAL]);

        if ($proPlanIds->isNotEmpty()) {
            Subscription::query()
                ->whereIn('plan_id', $proPlanIds)
                ->where('status', 'active')
                ->where('starts_at', '>=', now()->subDays($trialDays + 1))
                ->each(function (Subscription $subscription) use ($trialDays) {
                    if ($subscription->expires_at->lte($subscription->starts_at->copy()->addDays($trialDays + 7))) {
                        return;
                    }

                    $trialEnd = $subscription->starts_at->copy()->addDays($trialDays);
                    $subscription->trial_ends_at = $trialEnd;
                    $subscription->expires_at = $trialEnd;
                    $subscription->billing_phase = Subscription::BILLING_TRIAL;
                    $subscription->grace_ends_at = null;
                    $subscription->save();
                });
        }

        Subscription::query()
            ->whereIn('status', ['active', 'grace'])
            ->each(fn (Subscription $subscription) => $subscription->updateStatus());
    }
};
