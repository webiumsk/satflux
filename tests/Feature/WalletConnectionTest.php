<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WalletConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class WalletConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected const VALID_BLINK_SECRET = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_test123;wallet-id=wallet456';
    protected const VALID_AQUA_DESCRIPTOR = 'ct(slip77(xpub6D4BDPcP2GT577Vvch3Reb8P8CH),elsh(wpkh(xpub6E8...)))';

    /** @test */
    public function user_can_get_wallet_connection_for_own_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $connection = WalletConnection::create([
            'store_id' => $store->id,
            'type' => 'blink',
            'encrypted_secret' => Crypt::encryptString(self::VALID_BLINK_SECRET),
            'status' => 'needs_support',
            'submitted_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $connection->id)
            ->assertJsonPath('data.type', 'blink')
            ->assertJsonPath('data.status', 'needs_support');
        $response->assertJsonMissingPath('data.secret');
    }

    /** @test */
    public function user_gets_null_when_store_has_no_wallet_connection(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    /** @test */
    public function user_cannot_get_wallet_connection_for_other_users_store(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->getJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_create_wallet_connection_with_valid_blink_secret(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/wallet-connection", [
            'type' => 'blink',
            'secret' => self::VALID_BLINK_SECRET,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'blink')
            ->assertJsonPath('message', 'Wallet connection saved successfully');
        $response->assertJsonStructure(['data' => ['id', 'type', 'status', 'masked_secret']]);
        $this->assertDatabaseHas('wallet_connections', [
            'store_id' => $store->id,
            'type' => 'blink',
            'status' => 'needs_support',
        ]);
    }

    /** @test */
    public function user_can_create_wallet_connection_with_valid_aqua_descriptor(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/wallet-connection", [
            'type' => 'aqua_descriptor',
            'secret' => self::VALID_AQUA_DESCRIPTOR,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'aqua_descriptor');
        $this->assertDatabaseHas('wallet_connections', [
            'store_id' => $store->id,
            'type' => 'aqua_descriptor',
        ]);
    }

    /** @test */
    public function wallet_connection_store_validates_type_and_secret(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        // Valid type but invalid secret (too short, wrong format for blink)
        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/wallet-connection", [
            'type' => 'blink',
            'secret' => 'short',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['secret']);
    }

    /** @test */
    public function user_cannot_create_wallet_connection_for_other_users_store(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson("/api/stores/{$store->id}/wallet-connection", [
            'type' => 'blink',
            'secret' => self::VALID_BLINK_SECRET,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function check_duplicate_returns_false_when_descriptor_not_used(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/wallet-connection/check-duplicate", [
            'descriptor' => self::VALID_AQUA_DESCRIPTOR,
            'type' => 'aqua_descriptor',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('message', null);
    }

    /** @test */
    public function check_duplicate_returns_true_when_descriptor_used_by_another_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $store1 = Store::factory()->create(['user_id' => $user1->id]);
        $store2 = Store::factory()->create(['user_id' => $user2->id]);
        WalletConnection::create([
            'store_id' => $store1->id,
            'type' => 'aqua_descriptor',
            'encrypted_secret' => Crypt::encryptString(self::VALID_AQUA_DESCRIPTOR),
            'status' => 'needs_support',
            'submitted_by_user_id' => $user1->id,
        ]);

        $response = $this->actingAs($user2)->postJson("/api/stores/{$store2->id}/wallet-connection/check-duplicate", [
            'descriptor' => self::VALID_AQUA_DESCRIPTOR,
            'type' => 'aqua_descriptor',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('existing_store_id', $store1->id)
            ->assertJsonPath('existing_store_name', $store1->name);
    }

    /** @test */
    public function check_duplicate_validates_descriptor_and_type(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/stores/{$store->id}/wallet-connection/check-duplicate", [
            'descriptor' => '',
            'type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['descriptor', 'type']);
    }

    /** @test */
    public function check_duplicate_new_returns_duplicate_when_descriptor_used(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        WalletConnection::create([
            'store_id' => $store->id,
            'type' => 'aqua_descriptor',
            'encrypted_secret' => Crypt::encryptString(self::VALID_AQUA_DESCRIPTOR),
            'status' => 'needs_support',
            'submitted_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson('/api/wallet-connection/check-duplicate', [
            'descriptor' => self::VALID_AQUA_DESCRIPTOR,
            'type' => 'aqua_descriptor',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('duplicate', true);
    }

    /** @test */
    public function user_can_delete_wallet_connection_when_status_is_pending(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $connection = WalletConnection::create([
            'store_id' => $store->id,
            'type' => 'blink',
            'encrypted_secret' => Crypt::encryptString(self::VALID_BLINK_SECRET),
            'status' => 'pending',
            'submitted_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Wallet connection deleted successfully']);
        $this->assertDatabaseMissing('wallet_connections', ['id' => $connection->id]);
    }

    /** @test */
    public function user_cannot_delete_wallet_connection_when_status_is_not_pending(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        WalletConnection::create([
            'store_id' => $store->id,
            'type' => 'blink',
            'encrypted_secret' => Crypt::encryptString(self::VALID_BLINK_SECRET),
            'status' => 'needs_support',
            'submitted_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete wallet connection. Only pending connections can be deleted.',
            ]);
    }

    /** @test */
    public function delete_returns_404_when_no_wallet_connection(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Wallet connection not found']);
    }

    /** @test */
    public function user_cannot_delete_other_stores_wallet_connection(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        WalletConnection::create([
            'store_id' => $store->id,
            'type' => 'blink',
            'encrypted_secret' => Crypt::encryptString(self::VALID_BLINK_SECRET),
            'status' => 'pending',
            'submitted_by_user_id' => $owner->id,
        ]);

        $response = $this->actingAs($other)->deleteJson("/api/stores/{$store->id}/wallet-connection");

        $response->assertStatus(403);
    }

    /** @test */
    public function store_returns_422_when_aqua_descriptor_duplicate(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $store1 = Store::factory()->create(['user_id' => $user1->id]);
        $store2 = Store::factory()->create(['user_id' => $user2->id]);
        WalletConnection::create([
            'store_id' => $store1->id,
            'type' => 'aqua_descriptor',
            'encrypted_secret' => Crypt::encryptString(self::VALID_AQUA_DESCRIPTOR),
            'status' => 'needs_support',
            'submitted_by_user_id' => $user1->id,
        ]);

        $response = $this->actingAs($user2)->postJson("/api/stores/{$store2->id}/wallet-connection", [
            'type' => 'aqua_descriptor',
            'secret' => self::VALID_AQUA_DESCRIPTOR,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['secret']);
    }
}
