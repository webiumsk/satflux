<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateCsvExport;
use App\Jobs\ProcessMonthlyExports;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessMonthlyExportsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_pro_role_user_without_subscription_row(): void
    {
        Queue::fake();

        SubscriptionPlan::create([
            'code' => 'free',
            'name' => 'free',
            'display_name' => 'Free',
            'price_eur' => 0,
            'billing_period' => 'year',
            'max_stores' => 1,
            'max_api_keys' => 1,
            'max_ln_addresses' => 1,
            'features' => [],
            'is_active' => true,
        ]);
        SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['automatic_csv_exports'],
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'pro',
        ]);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'auto_report_enabled' => true,
            'auto_report_format' => 'standard',
        ]);

        $job = new ProcessMonthlyExports('2026-03', $store->id);
        $job->handle(app(SubscriptionService::class));

        $this->assertDatabaseHas('exports', [
            'store_id' => $store->id,
            'user_id' => $user->id,
            'source' => 'automatic',
            'format' => 'standard',
            'status' => 'pending',
        ]);
        Queue::assertPushed(GenerateCsvExport::class);
    }
}

