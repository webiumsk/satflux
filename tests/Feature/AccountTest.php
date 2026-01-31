<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'user@example.com']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => 'user@example.com', 'name' => 'Test User']);
        $response->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => __('messages.profile_updated')]);
        $response->assertJsonPath('user.name', 'New Name');
        $response->assertJsonPath('user.email', 'new@example.com');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_profile_update_validates_email(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user', [
            'name' => 'Me',
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('old-password'),
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/password', [
            'current_password' => 'old-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => __('messages.password_updated')]);

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secure-password', $user->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }
}
