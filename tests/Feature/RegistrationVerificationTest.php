<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function apiVerificationUrl(User $user): string
    {
        return str_replace('/auth/verify-email/', '/api/auth/verify-email/', app(EmailVerificationService::class)->signedVerificationUrlForUser($user));
    }

    public function test_an_old_link_cannot_activate_credentials_from_a_later_registration(): void
    {
        Notification::fake();
        $registration = app(RegistrationService::class);
        $user = $registration->register('registration@example.com', 'original-password');
        $oldUrl = $this->apiVerificationUrl($user);
        $user = $registration->register($user->email, 'replacement-password');
        // Avoid unrelated remote provisioning in this credential-binding regression.
        $user->update(['btcpay_user_id' => 'merchant-id', 'btcpay_api_key' => 'merchant-key']);

        $this->getJson($oldUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));

        $this->getJson($this->apiVerificationUrl($user->fresh()))->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
    }

    public function test_resending_for_the_same_credentials_preserves_valid_links(): void
    {
        $user = User::factory()->unverified()->create();
        $firstUrl = $this->apiVerificationUrl($user);
        $secondUrl = $this->apiVerificationUrl($user->fresh());
        $this->getJson($firstUrl)->assertOk();
        $this->getJson($secondUrl)->assertOk()->assertJsonPath('verified', true);
    }

    public function test_legacy_links_without_credential_binding_cannot_verify_an_unverified_account(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], false);

        $this->getJson($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }
}
