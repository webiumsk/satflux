<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates the three subscription plans: FREE, PRO, ENTERPRISE
     */
    public function run(): void
    {
        // FREE Plan
        SubscriptionPlan::updateOrCreate(
            ['code' => 'free'],
            [
                'name' => 'free',
                'display_name' => 'Free',
                'price_eur' => 0.00,
                'billing_period' => 'year',
                'max_stores' => 1,
                'max_api_keys' => 1,
                'max_ln_addresses' => 2,
                'max_events' => 1,
                'features' => [
                    'manual_csv_exports',
                    'basic_payment_overview',
                ],
                'is_active' => true,
            ]
        );

        // PRO Plan (€99/year, paid yearly)
        SubscriptionPlan::updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'pro',
                'display_name' => 'Pro',
                'price_eur' => 99.00,
                'billing_period' => 'year',
                'max_stores' => 3,
                'max_api_keys' => 3,
                'max_ln_addresses' => null, // unlimited
                'max_events' => 3,
                'features' => [
                    'manual_csv_exports',
                    'automatic_csv_exports',
                    'advanced_statistics',
                    'basic_payment_overview',
                    'offline_payment_methods',
                    'priority_support',
                    'stripe',
                ],
                'is_active' => true,
            ]
        );

        // ENTERPRISE Plan (internal only, not on pricing page)
        SubscriptionPlan::updateOrCreate(
            ['code' => 'enterprise'],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'price_eur' => 299.00,
                'billing_period' => 'year',
                'max_stores' => null,
                'max_api_keys' => null,
                'max_ln_addresses' => null,
                'max_events' => null, // unlimited
                'features' => [
                    'manual_csv_exports',
                    'automatic_csv_exports',
                    'advanced_statistics',
                    'basic_payment_overview',
                    'offline_payment_methods',
                    'per_store_user_management',
                    'priority_support',
                    'stripe',
                ],
                'is_active' => true,
            ]
        );
    }
}

