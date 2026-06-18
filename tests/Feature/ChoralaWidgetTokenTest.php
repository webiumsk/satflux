<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChoralaWidgetTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_token_requires_authentication(): void
    {
        $this->getJson('/api/chorala/widget-token')->assertStatus(401);
    }

    public function test_widget_token_returns_404_when_chorala_not_configured(): void
    {
        config([
            'services.chorala.project_key' => null,
            'services.chorala.end_user_jwt_secret' => null,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/chorala/widget-token')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Chorala widget SSO is not configured.');
    }

    public function test_widget_token_returns_signed_jwt_with_user_claims(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.end_user_jwt_secret' => 'test-end-user-secret-with-32-bytes-min',
        ]);

        $user = User::factory()->create([
            'name' => 'Alice Merchant',
            'email' => 'alice@example.com',
            'is_guest' => false,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/chorala/widget-token');

        $response->assertOk();
        $jwt = $response->json('jwt');
        $this->assertIsString($jwt);
        $this->assertNotSame('', $jwt);

        $decoded = (array) JWT::decode($jwt, new Key('test-end-user-secret-with-32-bytes-min', 'HS256'));
        $this->assertSame((string) $user->id, $decoded['id']);
        $this->assertSame('alice@example.com', $decoded['email']);
        $this->assertSame('Alice Merchant', $decoded['name']);
        $segment = (array) $decoded['segment'];
        $this->assertFalse($segment['is_guest']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertGreaterThan(time(), $decoded['exp']);
    }

    public function test_widget_token_omits_null_name_for_chorala_jwt_schema(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.end_user_jwt_secret' => 'test-end-user-secret-with-32-bytes-min',
        ]);

        $user = User::factory()->create([
            'name' => null,
            'email' => 'noname@example.com',
        ]);
        Sanctum::actingAs($user);

        $jwt = $this->getJson('/api/chorala/widget-token')->json('jwt');
        $decoded = (array) JWT::decode($jwt, new Key('test-end-user-secret-with-32-bytes-min', 'HS256'));

        $this->assertArrayNotHasKey('name', $decoded);
        $this->assertSame('noname@example.com', $decoded['email']);
    }
}
