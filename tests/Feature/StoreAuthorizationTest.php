<?php

namespace Tests\Feature;

use App\Models\PosTerminal;
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

        $response->assertStatus(403);
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

    public function test_user_cannot_list_other_users_pos_terminals(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        PosTerminal::create([
            'store_id' => $store->id,
            'name' => 'Counter',
            'settings_json' => ['enabled_payment_methods' => ['lightning']],
        ]);

        $this->actingAs($intruder)
            ->getJson("/api/stores/{$store->id}/pos-terminals")
            ->assertStatus(403);
    }

    public function test_user_cannot_access_other_users_wallet_connection(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson("/api/stores/{$store->id}/wallet-connection")
            ->assertStatus(403);
    }

    public function test_user_cannot_list_other_users_ticket_events(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson("/api/stores/{$store->id}/tickets/events")
            ->assertStatus(403);
    }

    public function test_admin_can_access_another_users_store(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)
            ->getJson("/api/stores/{$store->id}")
            ->assertStatus(200);
    }

    public function test_admin_can_list_another_users_pos_terminals(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        PosTerminal::create([
            'store_id' => $store->id,
            'name' => 'Counter',
            'settings_json' => ['enabled_payment_methods' => ['lightning']],
        ]);

        $this->actingAs($admin)
            ->getJson("/api/stores/{$store->id}/pos-terminals")
            ->assertStatus(200);
    }
}
