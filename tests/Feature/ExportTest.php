<?php

namespace Tests\Feature;

use App\Jobs\GenerateCsvExport;
use App\Models\Export;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

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
        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_user_can_list_exports_for_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        Export::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'source' => Export::SOURCE_AUTOMATIC,
            'format' => 'standard',
            'status' => 'pending',
            'filters' => [],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/exports");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'store_id', 'user_id', 'format', 'status']]]);
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_list_exports_for_other_users_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/stores/{$store->id}/exports");

        $response->assertStatus(403);
    }

    public function test_free_user_cannot_create_export(): void
    {
        $freeUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $freeUser->id]);

        Sanctum::actingAs($freeUser);

        $response = $this->postJson("/api/stores/{$store->id}/exports", [
            'format' => 'standard',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'status' => 'Settled',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Pro') || str_contains($m, 'upgrade'));
    }

    public function test_user_can_create_export(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);

        Sanctum::actingAs($this->proUser);

        $response = $this->postJson("/api/stores/{$store->id}/exports", [
            'format' => 'standard',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'status' => 'Settled',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Export job queued']);
        $response->assertJsonStructure(['data' => ['id', 'store_id', 'format', 'filters']]);

        $this->assertDatabaseHas('exports', [
            'store_id' => $store->id,
            'user_id' => $this->proUser->id,
            'format' => 'standard',
        ]);

        Queue::assertPushed(GenerateCsvExport::class);
    }

    public function test_export_creation_validates_format(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);

        Sanctum::actingAs($this->proUser);

        $response = $this->postJson("/api/stores/{$store->id}/exports", [
            'format' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['format']);
    }

    public function test_download_returns_403_for_other_users_export(): void
    {
        $user1 = $this->proUser;
        $user2 = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user1->id]);
        Subscription::create([
            'user_id' => $user2->id,
            'plan_id' => SubscriptionPlan::where('code', 'pro')->first()->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $user1->id,
            'format' => 'standard',
            'status' => 'finished',
            'filters' => [],
        ]);

        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/exports/{$export->id}/download");

        $response->assertStatus(403);
    }

    public function test_download_returns_403_for_free_user(): void
    {
        $freeUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $this->proUser->id,
            'format' => 'standard',
            'status' => 'finished',
            'file_path' => 'exports/test.csv',
            'filters' => [],
        ]);

        Sanctum::actingAs($freeUser);

        $response = $this->getJson("/api/exports/{$export->id}/download");

        $response->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Pro') || str_contains($m, 'upgrade'));
    }

    public function test_download_returns_202_when_export_not_ready(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $this->proUser->id,
            'format' => 'standard',
            'status' => 'pending',
            'filters' => [],
        ]);

        Sanctum::actingAs($this->proUser);

        $response = $this->getJson("/api/exports/{$export->id}/download");

        $response->assertStatus(202);
        $response->assertJson(['message' => 'Export is not ready yet']);
    }

    public function test_retry_returns_403_for_other_users_export(): void
    {
        $user1 = $this->proUser;
        $user2 = User::factory()->create();
        Subscription::create([
            'user_id' => $user2->id,
            'plan_id' => SubscriptionPlan::where('code', 'pro')->first()->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $store = Store::factory()->create(['user_id' => $user1->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $user1->id,
            'format' => 'standard',
            'status' => 'failed',
            'error_message' => 'Test error',
            'filters' => [],
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson("/api/exports/{$export->id}/retry");

        $response->assertStatus(403);
    }

    public function test_retry_returns_400_when_export_finished(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $this->proUser->id,
            'format' => 'standard',
            'status' => 'finished',
            'file_path' => 'exports/test.csv',
            'filters' => [],
        ]);

        Sanctum::actingAs($this->proUser);

        $response = $this->postJson("/api/exports/{$export->id}/retry");

        $response->assertStatus(400);
        $response->assertJsonPath('message', fn ($m) => str_contains($m, 'retried') || str_contains($m, 'can only'));
    }

    public function test_retry_requeues_failed_export(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $this->proUser->id,
            'format' => 'standard',
            'status' => 'failed',
            'error_message' => 'Test error',
            'filters' => [],
        ]);

        Sanctum::actingAs($this->proUser);

        $response = $this->postJson("/api/exports/{$export->id}/retry");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Export job requeued']);

        $export->refresh();
        $this->assertSame('pending', $export->status);
        $this->assertNull($export->error_message);
        Queue::assertPushed(GenerateCsvExport::class);
    }

    public function test_pro_user_can_delete_export(): void
    {
        $store = Store::factory()->create(['user_id' => $this->proUser->id]);
        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $this->proUser->id,
            'source' => Export::SOURCE_AUTOMATIC,
            'format' => 'standard',
            'status' => 'finished',
            'filters' => [],
        ]);

        Sanctum::actingAs($this->proUser);

        $response = $this->deleteJson("/api/exports/{$export->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Export deleted']);
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
    }
}
