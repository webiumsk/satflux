<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_uses_app_url_not_the_request_host(): void
    {
        config(['app.url' => 'https://satflux.example']);
        Notification::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example'])
            ->postJson('/api/auth/password/reset-link', ['email' => 'owner@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://satflux.example/password/reset?token=')
                && str_contains($url, 'email=owner%40example.com')
                && ! str_contains($url, 'evil.example');
        });
    }

    public function test_repeated_requests_look_the_same_for_existing_and_unknown_emails(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'owner@example.com']);

        foreach (['owner@example.com', 'nobody@example.com'] as $email) {
            $first = $this->postJson('/api/auth/password/reset-link', ['email' => $email]);
            $second = $this->postJson('/api/auth/password/reset-link', ['email' => $email]);

            $first->assertOk();
            $second->assertOk();
            $this->assertSame($first->json('message'), $second->json('message'), $email);
        }
    }
}
