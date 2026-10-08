<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\CompanyMemberRole;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EfakturaTestConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function skUserWithCompany(array $companyAttributes = []): array
    {
        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create(array_merge([
            'user_id' => $user->id,
            'legal_name' => 'Webium s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'registration_number' => '47615681',
            'tax_id' => '2023980035',
            'country' => 'SK',
            'vat_payer' => true,
            'vat_status' => 'payer',
            'app_settings' => [
                'efaktura_enabled' => true,
                'efaktura_sapi_base_url' => 'https://sapi.test',
                'efaktura_sapi_client_id' => 'client-test',
                'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('secret-test'),
            ],
        ], $companyAttributes));

        return [$user, $company];
    }

    #[Test]
    public function stored_credentials_are_tested_and_success_is_stamped(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        ]);

        [$user, $company] = $this->skUserWithCompany();

        $response = $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [])
            ->assertOk();

        $this->assertTrue($response->json('data.ok'));
        $this->assertNotNull($response->json('data.tested_at'));
        $this->assertNotNull($company->fresh()->app_settings['efaktura_connection_tested_at'] ?? null);
    }

    #[Test]
    public function invalid_credentials_map_to_a_stable_code_without_a_stamp(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        [$user, $company] = $this->skUserWithCompany();

        $response = $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [])
            ->assertOk();

        $this->assertFalse($response->json('data.ok'));
        $this->assertSame('invalid_credentials', $response->json('data.code'));
        $this->assertNull($company->fresh()->app_settings['efaktura_connection_tested_at'] ?? null);
    }

    #[Test]
    public function body_credentials_override_the_stored_settings(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test', 'other.test']]);
        Http::fake([
            'https://other.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok']),
        ]);

        [$user, $company] = $this->skUserWithCompany();

        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [
                'efaktura_sapi_base_url' => 'https://other.test',
                'efaktura_sapi_client_id' => 'other-client',
                'efaktura_sapi_client_secret' => 'other-secret',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://other.test/')
                && $request['client_id'] === 'other-client';
        });
    }

    #[Test]
    public function globally_disabled_or_ineligible_companies_are_rejected(): void
    {
        config(['efaktura.enabled' => false]);
        [$user, $company] = $this->skUserWithCompany();

        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [])
            ->assertStatus(422);

        config(['efaktura.enabled' => true]);
        $company->forceFill(['jurisdiction' => CompanyJurisdiction::EuCz, 'country' => 'CZ'])->save();

        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [])
            ->assertStatus(422);
    }

    #[Test]
    public function non_payer_sk_company_can_test_connection_for_inbound_only(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        ]);

        [$user, $company] = $this->skUserWithCompany(['vat_status' => 'none', 'vat_payer' => false]);

        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [])
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    #[Test]
    public function non_payer_sk_company_cannot_send_outbound(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake();

        [$user, $company] = $this->skUserWithCompany(['vat_status' => 'none', 'vat_payer' => false]);
        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'lines' => [],
        ]);

        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/documents/{$document->id}/efaktura/send")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['efaktura']);

        Http::assertNothingSent();
    }

    #[Test]
    public function ephemeral_test_requires_all_fields_in_the_body(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake([
            'https://sapi.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok']),
        ]);

        [$user] = $this->skUserWithCompany();

        // Local-first credentials live in Evolu - nothing stored server-side
        // can back-fill them, so missing fields come back as a stable code.
        $response = $this->actingAs($user)
            ->postJson('/api/invoicing/ephemeral/efaktura/test-connection', [
                'efaktura_sapi_base_url' => 'https://sapi.test',
            ])
            ->assertOk();
        $this->assertFalse($response->json('data.ok'));
        $this->assertSame('missing_fields', $response->json('data.code'));

        $this->actingAs($user)
            ->postJson('/api/invoicing/ephemeral/efaktura/test-connection', [
                'efaktura_sapi_base_url' => 'https://sapi.test',
                'efaktura_sapi_client_id' => 'client-test',
                'efaktura_sapi_client_secret' => 'secret-test',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    #[Test]
    public function active_member_cannot_test_stored_or_overridden_company_credentials(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test', 'other.test']]);
        Http::fake(['*' => Http::response(['access_token' => 'tok'])]);
        [$owner, $company] = $this->skUserWithCompany();
        $member = User::factory()->create();
        CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => CompanyMemberRole::Accountant,
            'invited_by' => $owner->id,
            'accepted_at' => now(),
        ]);

        // Membership is active and the owner's plan opens the module.
        $this->actingAs($member)->getJson("/api/invoicing/companies/{$company->id}")->assertOk();
        foreach ([[], ['efaktura_sapi_base_url' => 'https://other.test'], [
            'efaktura_sapi_base_url' => 'https://other.test',
            'efaktura_sapi_client_id' => 'caller-client',
            'efaktura_sapi_client_secret' => 'caller-secret',
        ]] as $payload) {
            $this->actingAs($member)
                ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", $payload)
                ->assertForbidden();
        }
        Http::assertNothingSent();
        $this->assertNull($company->fresh()->app_settings['efaktura_connection_tested_at'] ?? null);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function unsafeCredentialOverrides(): iterable
    {
        yield 'URL only' => [['efaktura_sapi_base_url' => 'https://other.test']];
        yield 'port only' => [['efaktura_sapi_base_url' => 'https://sapi.test:8443']];
        yield 'client ID only' => [['efaktura_sapi_client_id' => 'other-client']];
        yield 'zero client ID is not an omitted ID' => [['efaktura_sapi_client_id' => '0']];
        yield 'host casing requires explicit secret' => [['efaktura_sapi_base_url' => 'https://SAPI.TEST']];
        yield 'explicit default port requires explicit secret' => [['efaktura_sapi_base_url' => 'https://sapi.test:443']];
        yield 'URL and client ID' => [['efaktura_sapi_base_url' => 'https://other.test', 'efaktura_sapi_client_id' => 'other-client']];
        yield 'empty secret' => [['efaktura_sapi_base_url' => 'https://other.test', 'efaktura_sapi_client_secret' => '']];
        yield 'null secret' => [['efaktura_sapi_base_url' => 'https://other.test', 'efaktura_sapi_client_secret' => null]];
        yield 'whitespace secret' => [['efaktura_sapi_base_url' => 'https://other.test', 'efaktura_sapi_client_secret' => '   ']];
        yield 'spoofed secret flag' => [['efaktura_sapi_base_url' => 'https://other.test', 'efaktura_sapi_client_secret_set' => true]];
    }

    #[Test]
    #[DataProvider('unsafeCredentialOverrides')]
    public function changed_provider_or_client_cannot_inherit_the_stored_secret(array $payload): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test', 'other.test']]);
        Http::fake(['*' => Http::response(['access_token' => 'tok'])]);
        [$owner, $company] = $this->skUserWithCompany();
        $before = $company->app_settings;

        $this->actingAs($owner)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('efaktura_sapi_client_secret');

        Http::assertNothingSent();
        $this->assertSame($before, $company->fresh()->app_settings);
    }

    #[Test]
    public function normalized_unchanged_settings_can_reuse_the_stored_secret(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test']]);
        Http::fake(['https://sapi.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok'])]);
        [$owner, $company] = $this->skUserWithCompany();

        $this->actingAs($owner)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [
                'efaktura_sapi_base_url' => ' https://sapi.test/ ',
                'efaktura_sapi_client_id' => ' client-test ',
                'efaktura_sapi_client_secret' => null,
            ])->assertOk()->assertJsonPath('data.ok', true);

        Http::assertSent(fn ($request) => $request->url() === 'https://sapi.test/sapi/v1/auth/token'
            && $request['client_id'] === 'client-test'
            && $request['client_secret'] === 'secret-test');
    }

    #[Test]
    public function explicit_secret_can_test_a_new_destination_without_reusing_the_stored_secret(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test', 'other.test']]);
        Http::fake(['https://other.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok'])]);
        [$owner, $company] = $this->skUserWithCompany();

        $this->actingAs($owner)
            ->postJson("/api/invoicing/companies/{$company->id}/efaktura/test-connection", [
                'efaktura_sapi_base_url' => 'https://other.test',
                'efaktura_sapi_client_secret' => 'caller-secret',
            ])->assertOk()->assertJsonPath('data.ok', true);

        Http::assertSent(fn ($request) => $request->url() === 'https://other.test/sapi/v1/auth/token'
            && $request['client_secret'] === 'caller-secret');
        $this->assertSame('secret-test', Crypt::decryptString($company->fresh()->app_settings['efaktura_sapi_client_secret_encrypted']));
    }

    #[Test]
    public function company_less_member_test_never_backfills_shared_company_credentials(): void
    {
        config(['efaktura.enabled' => true, 'efaktura.allowed_sapi_hosts' => ['sapi.test', 'other.test']]);
        Http::fake(['https://other.test/sapi/v1/auth/token' => Http::response(['access_token' => 'tok'])]);
        [$owner, $company] = $this->skUserWithCompany();
        $member = User::factory()->create();
        CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => CompanyMemberRole::Accountant,
            'invited_by' => $owner->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)
            ->postJson('/api/invoicing/ephemeral/efaktura/test-connection', [
                'efaktura_sapi_base_url' => 'https://other.test',
            ])->assertOk()->assertJsonPath('data.code', 'missing_fields');
        Http::assertNothingSent();

        $this->actingAs($member)
            ->postJson('/api/invoicing/ephemeral/efaktura/test-connection', [
                'efaktura_sapi_base_url' => 'https://other.test',
                'efaktura_sapi_client_id' => 'caller-client',
                'efaktura_sapi_client_secret' => 'caller-secret',
            ])->assertOk()->assertJsonPath('data.ok', true);
        Http::assertSent(fn ($request) => $request['client_id'] === 'caller-client' && $request['client_secret'] === 'caller-secret');
    }
}
