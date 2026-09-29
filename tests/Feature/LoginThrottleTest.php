<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_guessing_on_one_account_is_limited_across_ips(): void
    {
        User::factory()->create(['email' => 'victim@example.com', 'password' => bcrypt('correct-horse')]);

        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => "guess-{$i}"])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/api/auth/login', ['email' => 'Victim@Example.com', 'password' => 'guess-11'])
            ->assertStatus(429);

        // Other accounts from a fresh address are unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.100'])
            ->postJson('/api/auth/login', ['email' => 'someone@example.com', 'password' => 'x'])
            ->assertStatus(422);
    }

    public function test_non_string_email_is_a_validation_error_not_a_server_error(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['a@example.com'], 'password' => 'x'])
            ->assertStatus(422);
    }
}
