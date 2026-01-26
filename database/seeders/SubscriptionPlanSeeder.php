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
            ['name' => 'free'],
            [
                'display_name' => 'Free',
                'price_eur' => 0.00,
                'max_stores' => 1,
                'max_api_keys' => 1,
                'features' => [
                    'manual_csv_exports',
                    'basic_payment_overview',
                ],
                'is_active' => true,
            ]
        );

        // PRO Plan
        SubscriptionPlan::updateOrCreate(
            ['name' => 'pro'],
            [
                'display_name' => 'Pro',
                'price_eur' => 99.00,
                'max_stores' => 3,
                'max_api_keys' => 3,
                'features' => [
                    'manual_csv_exports',
                    'automatic_csv_exports',
                    'advanced_statistics',
                    'basic_payment_overview',
                ],
                'is_active' => true,
            ]
        );

        // ENTERPRISE Plan
        SubscriptionPlan::updateOrCreate(
            ['name' => 'enterprise'],
            [
                'display_name' => 'Enterprise',
                'price_eur' => 299.00,
                'max_stores' => null, // unlimited
                'max_api_keys' => null, // unlimited
                'features' => [
                    'manual_csv_exports',
                    'automatic_csv_exports',
                    'advanced_statistics',
                    'basic_payment_overview',
                    'per_store_user_management',
                    'priority_support',
                ],
                'is_active' => true,
            ]
        );
    }
}

