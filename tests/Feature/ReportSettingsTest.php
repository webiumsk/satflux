<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $proPlan = SubscriptionPlan::create([
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
        $this->proUser = User::factory()->create(['email' => 'pro@example.com']);
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    protected User $proUser;

    public function test_non_owner_cannot_view_report_settings(): void
    {
        $otherUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/stores/{$store->id}/report-settings");

        $response->assertStatus(403);
    }

    public function test_store_owner_can_view_report_settings(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);

        Sanctum::actingAs($this->proUser);

        $response = $this->getJson("/api/stores/{$store->id}/report-settings");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['auto_report_enabled', 'auto_report_email', 'auto_report_format'], 'user_email']);
    }

    public function test_pro_user_can_update_report_settings(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);

        Sanctum::actingAs($this->proUser);

        $response = $this->putJson("/api/stores/{$store->id}/report-settings", [
            'auto_report_enabled' => true,
            'auto_report_email' => 'reports@example.com',
            'auto_report_format' => 'xlsx',
        ]);

        $response->assertStatus(200);
        $store->refresh();
        $this->assertTrue($store->auto_report_enabled);
        $this->assertSame('reports@example.com', $store->auto_report_email);
        $this->assertSame('xlsx', $store->auto_report_format);
    }

    public function test_pro_user_can_use_default_email(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);

        Sanctum::actingAs($this->proUser);

        $response = $this->putJson("/api/stores/{$store->id}/report-settings", [
            'auto_report_enabled' => true,
            'auto_report_email' => '',
            'auto_report_format' => 'standard',
        ]);

        $response->assertStatus(200);
        $store->refresh();
        $this->assertTrue($store->auto_report_enabled);
        $this->assertSame('pro@example.com', $store->auto_report_email);
    }

    public function test_free_user_cannot_update_report_settings(): void
    {
        $freeUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $freeUser->id]);

        Sanctum::actingAs($freeUser);

        $response = $this->putJson("/api/stores/{$store->id}/report-settings", [
            'auto_report_enabled' => true,
            'auto_report_email' => 'reports@example.com',
            'auto_report_format' => 'standard',
        ]);

        $response->assertStatus(403);
    }
}
