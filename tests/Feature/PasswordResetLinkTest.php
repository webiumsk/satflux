<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_requests_are_uniform_and_never_send_mail_or_create_tokens(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'owner@example.com']);

        $responses = [];
        foreach (['owner@example.com', 'nobody@example.com', 'not-an-email', null] as $email) {
            $responses[] = $this->postJson('/api/auth/password/reset-link', ['email' => $email])
                ->assertStatus(410)->assertJsonPath('code', 'password_reset_unavailable')->json();
        }

        foreach ($responses as $response) {
            $this->assertSame($responses[0], $response);
        }
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_previously_issued_valid_token_cannot_change_password_or_session(): void
    {
        Notification::fake();
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create(['email' => 'legacy@example.com', 'password' => 'existing-password', 'remember_token' => 'existing-session']);
        $token = Password::broker()->createToken($user);
        $hash = $user->password;

        $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertStatus(410)->assertJsonPath('code', 'password_reset_unavailable');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue(Hash::check('existing-password', $user->fresh()->password));
        $this->assertSame('existing-session', $user->fresh()->remember_token);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        Notification::assertNothingSent();
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_invalid_reset_input_has_same_retirement_response(): void
    {
        $this->postJson('/api/auth/password/reset', [])->assertStatus(410)
            ->assertJsonPath('code', 'password_reset_unavailable');
    }

    public function test_old_email_links_drop_tokens_and_email_and_ignore_untrusted_host(): void
    {
        foreach (['/reset-password/old-token?email=legacy%40example.com', '/password/reset?token=old-token&email=legacy%40example.com'] as $path) {
            $response = $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example'])
                ->get($path);
            $response->assertStatus(302)->assertHeader('Location', '/login');
            $location = $response->headers->get('Location');
            $this->assertStringNotContainsString('old-token', $location);
            $this->assertStringNotContainsString('email=', $location);
            $this->assertStringNotContainsString('evil.example', $location);
            $response->assertDontSee('<script', false);
        }
    }

    public function test_framework_notification_hook_cannot_send_reset_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->sendPasswordResetNotification('unused-token');
        Notification::assertNothingSent();
    }
}
