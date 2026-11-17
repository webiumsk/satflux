<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\BusinessExpense;
use App\Models\BusinessExpenseAttachment;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyDocumentSequence;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicingMigrationExportTest extends TestCase
{
    use RefreshDatabase;

    private User $proUser;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    #[Test]
    public function migration_status_reports_server_data(): void
    {
        $company = $this->createCompany();
        CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client s.r.o.',
        ]);

        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/status');

        $response->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.companies_count', 1)
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.attachments_on_server_count', 0);
    }

    #[Test]
    public function migration_export_returns_evolu_snapshot_shape(): void
    {
        $company = $this->createCompany();
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client s.r.o.',
            'is_active' => true,
        ]);

        CompanyDocumentSequence::create([
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'name' => 'Default',
            'format' => '{YYYY}{NNNN}',
            'reset_period' => 'yearly',
            'is_default' => true,
            'period_key' => '2026',
            'last_number' => 3,
        ]);

        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'title' => 'Invoice',
            'total' => 121,
            'subtotal' => 100,
            'tax_total' => 21,
            'currency' => 'EUR',
            'issue_date' => now(),
        ]);

        BusinessDocumentLine::create([
            'business_document_id' => $document->id,
            'sort_order' => 1,
            'name' => 'Service',
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 100,
            'tax_rate' => 21,
            'line_total' => 121,
        ]);

        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export');

        $response->assertOk()
            ->assertJsonPath('data.company.0.id', $company->id)
            ->assertJsonPath('data.company.0.legalName', 'Acme s.r.o.')
            ->assertJsonPath('data.contact.0.companyId', $company->id)
            ->assertJsonPath('data.document.0.documentType', 'invoice')
            ->assertJsonPath('data.documentLine.0.documentId', $document->id)
            ->assertJsonCount(1, 'data.numberSeries')
            ->assertJsonPath('meta.counts.company', 1)
            ->assertJsonPath('meta.counts.document', 1);
    }

    #[Test]
    public function migration_export_preserves_supplier_snapshots_and_original_payment_review(): void
    {
        $company = $this->createCompany();
        $contact = CompanyContact::create(['company_id' => $company->id, 'name' => 'Synthetic supplier']);
        BusinessExpense::create([
            'company_id' => $company->id, 'company_contact_id' => $contact->id,
            'supplier_name' => 'Saved supplier', 'supplier_iban' => 'SK6807200002891987426353',
            'payment_review_required' => false, 'internal_number' => 'EXP20260001',
            'issue_date' => '2026-11-03', 'total' => -20.25, 'currency' => 'EUR',
        ]);
        BusinessExpense::create([
            'company_id' => $company->id, 'internal_number' => 'EXP20260002',
            'issue_date' => '2026-11-03', 'total' => 10, 'currency' => 'EUR',
            'internal_note' => 'Imported from ISDOC.',
        ]);
        $this->actingAs($this->proUser)->getJson('/api/invoicing/migration/export')->assertOk()
            ->assertJsonPath('data.expense.0.companyContactId', $contact->id)
            ->assertJsonPath('data.expense.0.supplierName', 'Saved supplier')
            ->assertJsonPath('data.expense.0.supplierIban', 'SK6807200002891987426353')
            ->assertJsonPath('data.expense.0.paymentReviewRequired', 0)
            ->assertJsonPath('data.expense.0.total', '-20.25')
            ->assertJsonPath('data.expense.1.supplierIban', null)
            ->assertJsonPath('data.expense.1.paymentReviewRequired', 1);
    }

    #[Test]
    public function owner_export_keeps_both_sapi_secrets_but_excludes_every_enterprise_field(): void
    {
        $company = $this->createCompany();
        $settings = array_fill_keys(CompanyEfakturaSettings::ENTERPRISE_PRIVATE_FIELDS, 'synthetic-enterprise-private');
        $company->update(['app_settings' => array_merge($settings, [
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_sapi_client_id' => 'synthetic-sender-client',
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-sender-secret'),
            'efaktura_inbound_sapi_client_id' => 'synthetic-receiver-client',
            'efaktura_inbound_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-receiver-secret'),
            'efaktura_epostman_api_key_encrypted' => Crypt::encryptString('synthetic-enterprise-private'),
            'efaktura_epostman_api_key_set' => true,
        ])]);
        Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic foreign owner', 'jurisdiction' => CompanyJurisdiction::EuSk,
            'app_settings' => ['efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-foreign-secret')],
        ]);

        $response = $this->actingAs($this->proUser)->getJson('/api/invoicing/migration/export')
            ->assertOk()->assertJsonCount(1, 'data.company');
        $exported = json_decode($response->json('data.company.0.appSettingsJson'), true, flags: JSON_THROW_ON_ERROR);
        foreach (array_merge(CompanyEfakturaSettings::ENTERPRISE_PRIVATE_FIELDS, ['efaktura_epostman_api_key_set']) as $field) {
            $this->assertArrayNotHasKey($field, $exported);
        }
        $this->assertSame('synthetic-sender-secret', $exported['efaktura_sapi_client_secret']);
        $this->assertSame('synthetic-receiver-secret', $exported['efaktura_inbound_sapi_client_secret']);
        $this->assertFalse($exported['efaktura_inbound_use_shared_connection']);
        $this->assertArrayNotHasKey('efaktura_sapi_client_secret_encrypted', $exported);
        $this->assertArrayNotHasKey('efaktura_inbound_sapi_client_secret_encrypted', $exported);
        $this->assertStringNotContainsString('synthetic-enterprise-private', $response->getContent());
        $this->assertStringNotContainsString('synthetic-foreign-secret', $response->getContent());
    }

    public static function appSettingsBoundaries(): array
    {
        return [
            'ASCII below limit' => [3999, 'synthetic-private-note:'],
            'ASCII at limit' => [4000, 'synthetic-private-note:'],
            'ASCII above limit' => [4001, 'synthetic-private-note:'],
            'UTF-8 below limit' => [3999, 'synthetic-private-note:žľť'],
            'UTF-8 at limit' => [4000, 'synthetic-private-note:žľť'],
            'UTF-8 above limit' => [4001, 'synthetic-private-note:žľť'],
            'surrogates and escapes below limit' => [3999, "synthetic-private-note:😀\"\\\n"],
            'surrogates and escapes at limit' => [4000, "synthetic-private-note:😀\"\\\n"],
            'surrogates and escapes above limit' => [4001, "synthetic-private-note:😀\"\\\n"],
        ];
    }

    #[Test]
    #[DataProvider('appSettingsBoundaries')]
    public function app_settings_export_is_complete_json_or_explicitly_omitted_at_the_importer_limit(int $units, string $prefix): void
    {
        $company = $this->createCompany();
        $settings = [
            'efaktura_inbound_use_shared_connection' => false,
            'efaktura_sapi_client_id' => 'synthetic-sender-client',
            'efaktura_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-sender-secret'),
            'efaktura_inbound_sapi_client_id' => 'synthetic-receiver-client',
            'efaktura_inbound_sapi_client_secret_encrypted' => Crypt::encryptString('synthetic-receiver-secret'),
            'efaktura_epostman_api_key_encrypted' => Crypt::encryptString('synthetic-enterprise-key'),
            'reverse_charge_note' => '',
        ];
        $company->update(['app_settings' => $settings]);
        $baseline = $this->actingAs($this->proUser)->getJson('/api/invoicing/migration/export')
            ->assertOk()->json('data.company.0.appSettingsJson');
        $this->assertIsString($baseline);
        $expected = json_decode($baseline, true, flags: JSON_THROW_ON_ERROR);
        $encodedPrefix = json_encode($prefix, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $prefixUnits = intdiv(strlen(mb_convert_encoding($encodedPrefix, 'UTF-16LE', 'UTF-8')), 2) - 2;
        $baselineUnits = intdiv(strlen(mb_convert_encoding($baseline, 'UTF-16LE', 'UTF-8')), 2);
        $padding = $units - $baselineUnits - $prefixUnits;
        $this->assertGreaterThan(0, $padding);
        $settings['reverse_charge_note'] = $prefix.str_repeat('x', $padding);
        $expected['reverse_charge_note'] = $settings['reverse_charge_note'];
        $expectedJson = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertSame($units, intdiv(strlen(mb_convert_encoding($expectedJson, 'UTF-16LE', 'UTF-8')), 2));
        $company->update(['app_settings' => $settings]);

        $response = $this->actingAs($this->proUser)->getJson('/api/invoicing/migration/export')
            ->assertOk()->assertJsonPath('data.company.0.id', $company->id);
        $actual = $response->json('data.company.0.appSettingsJson');
        $warnings = $response->json('meta.warnings');
        $settingsWarnings = array_values(array_filter($warnings, fn ($warning) => str_starts_with($warning, 'row_export_failed:company_app_settings:')));
        if ($units > 4000) {
            // null matches OptionalString4000 and remains null through snapshot preparation.
            $this->assertTrue($actual === null, 'Oversized settings must be omitted rather than exported as a JSON prefix.');
            $this->assertSame(["row_export_failed:company_app_settings:{$company->id}:too_large"], $settingsWarnings);
        } else {
            $this->assertIsString($actual);
            $this->assertTrue(json_validate($actual), 'Fitting settings must remain complete valid JSON.');
            $this->assertSame(hash('sha256', $expectedJson), hash('sha256', $actual));
            $parsed = json_decode($actual, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('synthetic-sender-secret', $parsed['efaktura_sapi_client_secret']);
            $this->assertSame('synthetic-receiver-secret', $parsed['efaktura_inbound_sapi_client_secret']);
            foreach (CompanyEfakturaSettings::ENTERPRISE_PRIVATE_FIELDS as $field) {
                $this->assertArrayNotHasKey($field, $parsed);
            }
            $this->assertSame([], $settingsWarnings);
        }
        $warningsJson = json_encode($warnings, JSON_THROW_ON_ERROR);
        foreach (['synthetic-private-note', 'synthetic-sender-secret', 'synthetic-receiver-secret', 'synthetic-enterprise-key'] as $secret) {
            $this->assertStringNotContainsString($secret, $warningsJson);
        }
        $this->assertSame($settings, $company->fresh()->app_settings, 'Export never rewrites the source settings.');
    }

    #[Test]
    public function migration_export_excludes_attachment_content_by_default(): void
    {
        $company = $this->createCompany();

        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export');

        $response->assertOk()
            ->assertJsonPath('meta.include_attachments', false)
            ->assertJsonPath('meta.include_branding', false);
    }

    #[Test]
    public function migration_export_is_empty_for_user_without_companies(): void
    {
        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export');

        $response->assertNotFound();
    }

    #[Test]
    public function migration_export_attachments_returns_base64_content(): void
    {
        Storage::fake('local');
        $company = $this->createCompany();
        $expense = BusinessExpense::create([
            'company_id' => $company->id,
            'internal_number' => 'N20260001',
            'issue_date' => now(),
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $path = "companies/{$company->id}/expenses/{$expense->id}/invoice.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 fake');
        BusinessExpenseAttachment::create([
            'business_expense_id' => $expense->id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => 'invoice.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 14,
        ]);

        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export-attachments');

        $response->assertOk()
            ->assertJsonPath('meta.counts.expenseAttachment', 1)
            ->assertJsonPath('data.expenseAttachment.0.originalFilename', 'invoice.pdf');

        $content = $response->json('data.expenseAttachment.0.contentBase64');
        $this->assertIsString($content);
        $this->assertNotSame('', $content);
    }

    #[Test]
    public function migration_export_attachments_is_empty_for_user_without_companies(): void
    {
        $response = $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/migration/export-attachments');

        $response->assertNotFound();
    }

    #[Test]
    public function migration_endpoints_require_pro_plan(): void
    {
        $freeUser = User::factory()->create();

        $this->actingAs($freeUser)
            ->getJson('/api/invoicing/migration/status')
            ->assertForbidden();
    }

    private function createCompany(): Company
    {
        return Company::create([
            'user_id' => $this->proUser->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
            'vat_status' => 'none',
        ]);
    }
}
