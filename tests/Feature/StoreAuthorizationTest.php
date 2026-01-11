<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_other_users_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $store = Store::factory()->create([
            'user_id' => $user1->id,
        ]);

        $response = $this->actingAs($user2)->getJson("/api/stores/{$store->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_access_own_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/stores/{$store->id}");

        $response->assertStatus(200);
    }
}

