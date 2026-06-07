<?php

namespace Tests\Feature;

use App\Models\SanctionsEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplianceListScreeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'app.env' => 'local',
            'compliance.enabled' => true,
            'compliance.geo_block_enabled' => false,
            'compliance.list_screening_enabled' => true,
            'compliance.fail_closed' => true,
        ]);
    }

    public function test_register_blocked_when_email_matches_sanctions_entry(): void
    {
        SanctionsEntry::create([
            'source' => 'ofac_sdn',
            'external_id' => 'test-1',
            'primary_name' => 'Blocked Person',
            'primary_name_normalized' => 'blocked person',
            'aliases_normalized' => [],
            'countries' => [],
            'synced_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'blocked.person@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'blocked.person@example.com',
            'screening_status' => 'hit',
            'decision' => 'blocked',
            'decision_reason' => 'sanctions_list_match',
        ]);
    }

    public function test_register_blocked_when_name_matches_sanctions_alias(): void
    {
        SanctionsEntry::create([
            'source' => 'eu_consolidated',
            'external_id' => 'eu-test-1',
            'primary_name' => 'Primary Entity',
            'primary_name_normalized' => 'primary entity',
            'aliases_normalized' => ['sanctioned alias'],
            'countries' => ['IR'],
            'synced_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sanctioned Alias',
            'email' => 'unique-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'unique-user@example.com',
            'screening_status' => 'hit',
        ]);
    }

    public function test_register_blocked_when_list_screening_enabled_but_not_synced(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'nosync@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'nosync@example.com',
            'screening_status' => 'error',
            'decision_reason' => 'sanctions_list_not_synced',
        ]);
    }

    public function test_register_allowed_when_list_clear(): void
    {
        SanctionsEntry::create([
            'id' => (string) Str::uuid(),
            'source' => 'ofac_sdn',
            'external_id' => 'test-2',
            'primary_name' => 'Someone Else',
            'primary_name_normalized' => 'someone else',
            'aliases_normalized' => [],
            'countries' => [],
            'synced_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'clear-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('compliance_screenings', [
            'subject_email' => 'clear-user@example.com',
            'screening_status' => 'clear',
            'decision' => 'allowed',
        ]);
    }
}
