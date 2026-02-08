# Subscription Implementation Guide

This document provides instructions for completing the subscription system implementation, including migration files that need to be created manually due to file permissions.

## Migration Files

✅ Migration files have been created:
- `database/migrations/2026_01_25_000001_create_subscription_plans_table.php`
- `database/migrations/2026_01_25_000002_create_subscriptions_table.php`

The migration files are ready to run. If you need to recreate them, here are the contents:

### 1. Create `database/migrations/2026_01_25_000001_create_subscription_plans_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates subscription_plans table to store plan definitions.
     * Plans define limits and features for each tier (FREE, PRO, ENTERPRISE).
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'free', 'pro', 'enterprise'
            $table->string('display_name'); // 'Free', 'Pro', 'Enterprise'
            $table->decimal('price_eur', 10, 2)->default(0); // Price in EUR per year
            $table->integer('max_stores')->nullable(); // null = unlimited
            $table->integer('max_api_keys')->nullable(); // null = unlimited
            $table->json('features')->nullable(); // Feature flags as JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
```

### 2. Create `database/migrations/2026_01_25_000002_create_subscriptions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates subscriptions table to track user subscriptions.
     * Status values: 'active', 'grace', 'expired'
     * 
     * IMPORTANT: This is a non-custodial system. Expired subscriptions
     * do NOT affect payment acceptance - only management features are limited.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans')->onDelete('restrict');
            $table->enum('status', ['active', 'grace', 'expired'])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('btcpay_subscription_id')->nullable(); // BTCPay subscription ID
            $table->timestamps();

            $table->index('user_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index('btcpay_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
```

## Setup Steps

1. **Run migrations:**
   ```bash
   docker compose exec php php artisan migrate
   ```

2. **Seed subscription plans:**
   ```bash
   docker compose exec php php artisan db:seed --class=SubscriptionPlanSeeder
   ```

3. **Create FREE subscriptions for existing users:**
   You may want to create a command or one-time script to ensure all existing users have a FREE subscription:
   ```php
   use App\Models\User;
   use App\Services\SubscriptionService;
   
   $subscriptionService = app(SubscriptionService::class);
   User::whereDoesntHave('subscriptions')->each(function ($user) use ($subscriptionService) {
       $subscriptionService->ensureFreeSubscription($user);
   });
   ```

4. **Set up scheduled task for subscription status updates:**
   Add to `app/Console/Kernel.php`:
   ```php
   protected function schedule(Schedule $schedule)
   {
       // Update subscription statuses daily
       $schedule->call(function () {
           app(\App\Services\SubscriptionService::class)->updateAllSubscriptionStatuses();
       })->daily();
   }
   ```

## Implementation Summary

### Backend Components Created

✅ **Models:**
- `SubscriptionPlan` - Plan definitions with limits and features
- `Subscription` - User subscription tracking with status management

✅ **Middleware:**
- `EnsureActiveSubscription` - Blocks writes if subscription expired (beyond grace)
- `EnsureStoreLimit` - Enforces store creation limits
- `EnsureApiKeyLimit` - Enforces API key creation limits

✅ **Services:**
- `SubscriptionService` - Feature flag checks and subscription management

✅ **Updated Controllers:**
- `SubscriptionController` - Handles yearly subscriptions with grace period
- `ExportController` - Manual exports always available (automatic exports via scheduled job)

✅ **Routes:**
- Middleware added to store creation and API key creation routes

✅ **Seeder:**
- `SubscriptionPlanSeeder` - Creates FREE, PRO, ENTERPRISE plans

### Frontend Components (Still Needed)

- Pricing page component
- Account settings showing current plan and limits
- Warning banner for grace/expired subscription states

## Key Safety Rules Implemented

1. ✅ **Payments NEVER blocked** - All middleware and checks exclude payment acceptance
2. ✅ **Existing infrastructure preserved** - Limits only apply to NEW creations
3. ✅ **14-day grace period** - Implemented in Subscription model
4. ✅ **Yearly billing only** - SubscriptionService extends by 1 year
5. ✅ **Manual CSV exports for all** - ExportController allows manual exports for all plans

## Feature Flags

Feature flags are checked via `SubscriptionService`:
- `canUseAutomaticExports()` - PRO and ENTERPRISE only
- `canViewAdvancedStats()` - PRO and ENTERPRISE only
- `canManageStoreUsers()` - ENTERPRISE only

## Next Steps

1. Run migrations and seeder (using `docker compose exec php php artisan migrate`)
3. Implement frontend components (pricing page, account settings, warning banner)
4. Set up scheduled task for subscription status updates
5. Test subscription flow end-to-end

