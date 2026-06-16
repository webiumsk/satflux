<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedFirstRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function direct_email_registration_is_blocked_when_seed_first_is_enabled(): void
    {
        config(['guest.seed_first_registration' => true]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
    }
}
