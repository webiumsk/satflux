<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankPaymentMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

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
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function csv_import_auto_matches_invoice_by_variable_symbol(): void
    {
        BusinessDocument::create([
            'company_id' => $this->company->id,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260042',
            'variable_symbol' => '20260042',
            'currency' => 'EUR',
            'total' => 150.50,
            'issue_date' => now(),
        ]);

        $csv = implode("\n", [
            'datum;suma;mena;vs',
            '01.06.2026;150,50;EUR;20260042',
        ]);

        $file = UploadedFile::fake()->createWithContent('export.csv', $csv);

        $response = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/import",
            ['file' => $file],
        );

        $response->assertOk();
        $response->assertJsonPath('data.imported', 1);
        $response->assertJsonPath('data.auto_matched', 1);

        $doc = BusinessDocument::first();
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->status);

        $list = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/documents",
        );
        $list->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $doc->id);
        $this->assertNotNull($row['bank_match'] ?? null);
        $this->assertSame($doc->id, $row['bank_match']['business_document_id'] ?? null);
    }

    #[Test]
    public function manual_match_marks_invoice_paid(): void
    {
        $doc = BusinessDocument::create([
            'company_id' => $this->company->id,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260099',
            'variable_symbol' => '20260099',
            'currency' => 'EUR',
            'total' => 200,
            'issue_date' => now(),
        ]);

        $import = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/import",
            [
                'file' => UploadedFile::fake()->createWithContent('x.csv', "datum;suma;mena;vs\n01.06.2026;200,00;EUR;88888888"),
            ],
        );
        $import->assertOk();
        $txId = \App\Models\BankTransaction::first()->id;

        $match = $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/{$txId}/match",
            ['business_document_id' => $doc->id],
        );
        $match->assertOk();

        $doc->refresh();
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->status);
    }

    #[Test]
    public function bank_transaction_index_includes_summary_totals(): void
    {
        $this->company->update([
            'iban' => 'SK31 1200 0000 1987 4269 76',
            'bank_name' => 'Tatra banka',
        ]);

        $csv = implode("\n", [
            'datum;suma;mena;vs;popis',
            '01.06.2026;100,00;EUR;111;Kredit na ucte',
            '02.06.2026;40,00;EUR;222;Debet na ucte',
        ]);

        $this->actingAs($this->user)->postJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/import",
            ['file' => UploadedFile::fake()->createWithContent('export.csv', $csv)],
        )->assertOk();

        $response = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions",
        );

        $response->assertOk();
        $response->assertJsonPath('meta.summary.credit_count', 1);
        $response->assertJsonPath('meta.summary.debit_count', 1);
        $response->assertJsonPath('meta.summary.credit_total', '100.00');
        $response->assertJsonPath('meta.summary.debit_total', '40.00');
        $response->assertJsonPath('meta.summary.balance', '60.00');
        $response->assertJsonPath('meta.summary.currency', 'EUR');
    }

    #[Test]
    public function company_summary_includes_masked_bank_account_label(): void
    {
        $this->company->update([
            'iban' => 'SK31 1200 0000 1987 4269 76',
            'bank_name' => 'Tatra banka',
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/summary",
        );

        $response->assertOk();
        $response->assertJsonPath('data.has_bank_account', true);
        $response->assertJsonPath('data.bank_account_label', 'Tatra banka ****6976');
        $response->assertJsonPath('data.default_currency', 'EUR');
    }
}
