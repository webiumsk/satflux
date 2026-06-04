<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function retention_purges_old_processed_webhooks(): void
    {
        DB::table('webhook_events')->insert([
            'store_id' => null,
            'event_type' => 'InvoiceSettled',
            'payload' => json_encode(['test' => true]),
            'verified' => true,
            'processed_at' => now()->subDays(100),
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        config([
            'data_retention.webhook_events_days' => 90,
            'data_retention.audit_logs_days' => 9999,
            'data_retention.draft_documents_days' => 9999,
            'data_retention.soft_deleted_companies_days' => 9999,
            'data_retention.export_files_days' => 9999,
        ]);

        $stats = app(DataRetentionService::class)->run(dryRun: false);

        $this->assertGreaterThanOrEqual(1, $stats['webhook_events_deleted']);
        $this->assertSame(0, DB::table('webhook_events')->count());
    }

    #[Test]
    public function soft_deleted_company_is_force_deleted_after_grace(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Gone s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $company->delete();
        Company::withTrashed()->where('id', $company->id)->update([
            'deleted_at' => now()->subDays(40),
        ]);

        config(['data_retention.soft_deleted_companies_days' => 30]);

        $stats = app(DataRetentionService::class)->run(dryRun: false);

        $this->assertSame(1, $stats['companies_force_deleted']);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    #[Test]
    public function stale_draft_documents_are_deleted(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'status' => BusinessDocumentStatus::Draft,
            'currency' => 'EUR',
        ]);
        DB::table('business_documents')->where('id', $doc->id)->update([
            'updated_at' => now()->subDays(400),
        ]);

        config(['data_retention.draft_documents_days' => 30]);

        $stats = app(DataRetentionService::class)->run(dryRun: false);

        $this->assertSame(1, $stats['draft_documents_deleted']);
        $this->assertDatabaseMissing('business_documents', ['id' => $doc->id]);
    }

    #[Test]
    public function data_retention_command_requires_enable_or_force(): void
    {
        config(['data_retention.enabled' => false]);

        $this->artisan('data:retention-run')->assertFailed();
        $this->artisan('data:retention-run', ['--force' => true])->assertSuccessful();
    }
}
