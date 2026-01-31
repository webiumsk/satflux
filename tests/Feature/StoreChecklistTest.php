<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreChecklist;
use App\Models\User;
use App\Services\StoreChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_checklist_for_own_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id, 'wallet_type' => 'blink']);
        StoreChecklistService::initializeChecklist($store->id, 'blink');

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/stores/{$store->id}/checklist");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['key', 'description', 'link', 'completed_at', 'is_completed', 'optional']]]);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $first = collect($data)->firstWhere('key', 'connect_wallet');
        $this->assertNotNull($first);
        $this->assertFalse($first['is_completed']);
    }

    public function test_user_cannot_get_checklist_for_other_users_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user1->id]);
        StoreChecklistService::initializeChecklist($store->id, 'blink');

        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/stores/{$store->id}/checklist");

        $response->assertStatus(403);
    }

    public function test_user_can_mark_checklist_item_completed(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id, 'wallet_type' => 'blink']);
        StoreChecklistService::initializeChecklist($store->id, 'blink');
        $item = StoreChecklist::where('store_id', $store->id)->where('item_key', 'connect_wallet')->first();
        $this->assertNotNull($item, 'Checklist item should exist after initializeChecklist');

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/stores/{$store->getRouteKey()}/checklist/connect_wallet", [
            'completed' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_completed', true);
        $response->assertJsonPath('data.key', 'connect_wallet');
        $item->refresh();
        $this->assertNotNull($item->completed_at);
    }

    public function test_user_can_mark_checklist_item_incomplete(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id, 'wallet_type' => 'blink']);
        StoreChecklistService::initializeChecklist($store->id, 'blink');
        $item = StoreChecklist::where('store_id', $store->id)->where('item_key', 'connect_wallet')->first();
        $this->assertNotNull($item);
        $item->markAsCompleted();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/stores/{$store->getRouteKey()}/checklist/connect_wallet", [
            'completed' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_completed', false);
        $item->refresh();
        $this->assertNull($item->completed_at);
    }

    public function test_checklist_update_validates_completed_boolean(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        StoreChecklistService::initializeChecklist($store->id, 'blink');
        $itemKey = 'connect_wallet';

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/stores/{$store->id}/checklist/{$itemKey}", [
            'completed' => 'not-a-boolean',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['completed']);
    }
}
