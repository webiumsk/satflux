<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EfakturaOutboundAttempt;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\User;
use App\Services\CompanyPermanentDeleteService;
use App\Services\Invoicing\CompanyBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class EpostmanRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        $company = Company::create(['user_id' => User::factory()->create()->id, 'legal_name' => 'Synthetic retention',
            'jurisdiction' => 'eu_sk', 'country' => 'SK', 'default_currency' => 'EUR']);
        $company->forceFill(['deleted_at' => now()->subDays(60)])->save();

        return $company;
    }

    private function attempt(Company $company, bool $resolved = false): EfakturaOutboundAttempt
    {
        return EfakturaOutboundAttempt::create(['user_id' => $company->user_id, 'company_id' => $company->id,
            'source_kind' => 'local', 'source_document_id' => (string) Str::uuid(),
            'connection_fingerprint' => str_repeat('a', 64), 'content_sha256' => str_repeat('b', 64),
            'request_encrypted' => $resolved ? null : Crypt::encryptString('Synthetic retained retry XML'),
            'status' => 'submitted', 'external_id' => $resolved ? (string) Str::uuid() : null,
            'response_payload' => ['epostman' => ['submission_state' => $resolved ? 'accepted' : 'uncertain']],
            'retry_until' => now()->subDays(40)]);
    }

    public function test_unresolved_expired_attempt_blocks_direct_purge_before_branding_cleanup(): void
    {
        Http::fake();
        $company = $this->company();
        $company->forceFill(['logo_path' => 'synthetic-logo.png', 'signature_stamp_path' => 'synthetic-stamp.png'])->save();
        $attempt = $this->attempt($company);
        $store = Store::factory()->create(['user_id' => $company->user_id, 'company_id' => $company->id]);
        $integration = StoreIntegration::createForStore($store)['integration'];
        $branding = $this->mock(CompanyBrandingService::class);
        $branding->shouldNotReceive('deleteLogo');
        $branding->shouldNotReceive('deleteSignatureStamp');
        $this->assertFalse(app(CompanyPermanentDeleteService::class)->forceDelete($company));
        $this->assertNotNull(Company::withTrashed()->find($company->id));
        $this->assertSame($attempt->request_encrypted, $attempt->fresh()->request_encrypted);
        $this->assertSame($company->id, $store->fresh()->company_id);
        $this->assertTrue($integration->fresh()->is_active);
        Http::assertNothingSent();
    }

    public function test_direct_purge_rejects_a_restored_company_without_a_cutoff_before_cleanup(): void
    {
        Http::fake();
        $company = $this->company();
        $company->forceFill(['logo_path' => 'synthetic-logo.png', 'signature_stamp_path' => 'synthetic-stamp.png'])->save();
        $attempt = $this->attempt($company, resolved: true);
        $store = Store::factory()->create(['user_id' => $company->user_id, 'company_id' => $company->id]);
        $integration = StoreIntegration::createForStore($store)['integration'];
        // The caller's stale snapshot must not override the fresh locked state.
        Company::withTrashed()->findOrFail($company->id)->restore();
        $branding = $this->mock(CompanyBrandingService::class);
        $branding->shouldNotReceive('deleteLogo');
        $branding->shouldNotReceive('deleteSignatureStamp');

        $this->assertFalse(app(CompanyPermanentDeleteService::class)->forceDelete($company));
        $retained = Company::findOrFail($company->id);
        $this->assertNull($retained->deleted_at);
        $this->assertSame($company->logo_path, $retained->logo_path);
        $this->assertSame($company->signature_stamp_path, $retained->signature_stamp_path);
        $this->assertNotNull($attempt->fresh());
        $this->assertSame($company->id, $store->fresh()->company_id);
        $this->assertTrue($integration->fresh()->is_active);
        Http::assertNothingSent();
    }

    public function test_held_company_does_not_consume_the_retention_batch(): void
    {
        config(['data_retention.batch_size' => 1]);
        $held = $this->company();
        $this->attempt($held);
        $eligible = $this->company();
        $service = app(CompanyPermanentDeleteService::class);
        $this->assertSame(1, $service->purgeSoftDeleted(true));
        $this->assertNotNull(Company::withTrashed()->find($eligible->id));
        $this->assertSame(1, $service->purgeSoftDeleted(false));
        $this->assertNull(Company::withTrashed()->find($eligible->id));
        $this->assertNotNull(Company::withTrashed()->find($held->id));
        $this->assertDatabaseCount('efaktura_outbound_attempts', 1);
    }

    public function test_known_receipt_without_retained_request_keeps_existing_cascade_behavior(): void
    {
        $company = $this->company();
        $attempt = $this->attempt($company, resolved: true);
        app(CompanyPermanentDeleteService::class)->forceDelete($company);
        $this->assertNull(Company::withTrashed()->find($company->id));
        $this->assertNull(EfakturaOutboundAttempt::find($attempt->id));
    }

    public function test_direct_purge_preserves_the_cutoff_for_soft_deleted_companies(): void
    {
        $company = $this->company();
        $cutoff = now()->subDays(30)->startOfSecond();
        $company->forceFill(['deleted_at' => $cutoff])->save();
        $service = app(CompanyPermanentDeleteService::class);
        $this->assertFalse($service->forceDelete($company, $cutoff));
        $this->assertNotNull(Company::withTrashed()->find($company->id));

        $company->forceFill(['deleted_at' => $cutoff->copy()->subSecond()])->save();
        $this->assertTrue($service->forceDelete($company, $cutoff));
        $this->assertNull(Company::withTrashed()->find($company->id));
    }

    public function test_retention_rechecks_restoration_and_retained_request_before_cleanup(): void
    {
        $company = $this->company();
        $cutoff = now()->subDays(30);
        $company->restore();
        $this->assertFalse(app(CompanyPermanentDeleteService::class)->forceDelete($company, $cutoff));
        $this->assertNotNull($company->fresh());
        $company->forceFill(['deleted_at' => now()->subDays(60)])->save();
        $attempt = $this->attempt($company, resolved: true);
        $attempt->update(['request_encrypted' => Crypt::encryptString('Synthetic retained original')]);
        $this->assertFalse(app(CompanyPermanentDeleteService::class)->forceDelete($company, $cutoff));
        $this->assertNotNull($attempt->fresh());
    }
}
