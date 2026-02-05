<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiKeyLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $free = SubscriptionPlan::create([
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
        Subscription::create([
            'user_id' => ($this->user = User::factory()->create())->id,
            'plan_id' => $free->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    /** @test */
    public function user_can_create_first_api_key_on_free_plan(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/user/api-keys', [
            'name' => 'My key',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My key')
            ->assertJsonStructure(['data' => ['plain_token', 'id']]);
    }

    /** @test */
    public function user_cannot_create_second_api_key_on_free_plan(): void
    {
        UserApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'First',
            'key_hash' => hash('sha256', 'd21_abc'),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/user/api-keys', [
            'name' => 'Second',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'maximum number of API keys'))
            ->assertJsonPath('max_allowed', 1);
    }
}
