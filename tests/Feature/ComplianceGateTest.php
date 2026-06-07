<?php

namespace Tests\Feature;

use App\Models\ComplianceScreening;
use App\Models\User;
use App\Services\Compliance\GeoCountryResolver;
use App\Services\Compliance\Resolvers\CompositeGeoCountryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ComplianceGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'compliance.enabled' => true,
            'compliance.geo_block_enabled' => true,
            'compliance.list_screening_enabled' => false,
            'compliance.fail_closed' => true,
            'compliance.geo_country_override' => null,
        ]);
    }

    public function test_register_blocked_for_sanctioned_country(): void
    {
        config(['compliance.geo_country_override' => 'IR']);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'blocked@example.com',
            'country_code' => 'IR',
            'geo_blocked' => true,
            'decision' => 'blocked',
            'decision_reason' => 'geo_blocked_country',
        ]);
    }

    public function test_register_allowed_for_permitted_country(): void
    {
        config(['compliance.geo_country_override' => 'SK']);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'allowed@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'allowed@example.com']);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'allowed@example.com',
            'country_code' => 'SK',
            'geo_blocked' => false,
            'decision' => 'allowed',
            'screening_status' => 'skipped',
        ]);
    }

    public function test_register_skips_gate_when_compliance_disabled(): void
    {
        config([
            'compliance.enabled' => false,
            'compliance.geo_country_override' => 'IR',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'disabled@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('compliance_screenings', 0);
    }

    public function test_register_blocks_when_geo_unknown_and_fail_closed(): void
    {
        $this->app->instance(GeoCountryResolver::class, new CompositeGeoCountryResolver([]));

        config([
            'app.env' => 'production',
            'compliance.geo_country_override' => null,
            'compliance.fail_closed' => true,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->postJson('/api/auth/register', [
            'email' => 'unknown-geo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'unknown-geo@example.com',
            'decision' => 'blocked',
            'decision_reason' => 'geo_unknown_fail_closed',
        ]);
    }

    public function test_register_allowed_on_loopback_in_local_env_when_geo_unknown(): void
    {
        config([
            'app.env' => 'local',
            'compliance.geo_country_override' => null,
            'compliance.fail_closed' => true,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->postJson('/api/auth/register', [
            'email' => 'localdev@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'localdev@example.com',
            'decision' => 'allowed',
        ]);
    }

    public function test_existing_auth_register_still_works_with_compliance_off(): void
    {
        config(['compliance.enabled' => false]);

        $this->postJson('/api/auth/register', [
            'email' => 'legacy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ])->assertStatus(201);

        $this->assertInstanceOf(User::class, User::where('email', 'legacy@example.com')->first());
        $this->assertSame(0, ComplianceScreening::count());
    }
}
