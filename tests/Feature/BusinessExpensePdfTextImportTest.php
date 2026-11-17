<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentIsdocService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use App\Support\Invoicing\ExpensePaymentReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpensePdfTextImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.free_pro_enabled' => false]);
        Storage::fake('local');
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3,
            'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id, 'legal_name' => 'Synthetic buyer s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
            'registration_number' => '12345678', 'street' => 'Synthetic street 1',
            'city' => 'Synthetic city', 'postal_code' => '00000', 'country' => 'SK',
            'vat_payer' => false,
        ]);
    }

    /** @return array<string, array{bool}> */
    public static function storageModes(): array
    {
        return ['server' => [false], 'ephemeral' => [true]];
    }

    private function root(bool $ephemeral): string
    {
        return $ephemeral ? '/api/invoicing/ephemeral/expenses/'
            : "/api/invoicing/companies/{$this->company->id}/expenses/";
    }

    private function invoice(): string
    {
        return "Supplier: Synthetic carrier s.r.o.\nIBAN: SK6807200002891987426353\nCustomer: Synthetic buyer\nInvoice no.: INV-001\nInvoice date: 2026-11-01\nDue date: 2026-11-15\nVariable symbol: 0000123\nTotal due: 19.50 EUR";
    }

    /** Create an actual small PDF with only synthetic contents and optional attachment. */
    private function pdf(string|array $text, ?string $embedded = null, int $pages = 1): UploadedFile
    {
        $pages = is_array($text) ? count($text) : $pages;
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];
        for ($i = 0; $i < $pages; $i++) {
            $page = count($objects) + 1;
            $content = $page + 1;
            $kids[] = "{$page} 0 R";
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$content} 0 R >>";
            $stream = "BT /F1 10 Tf 40 790 Td\n";
            foreach (explode("\n", is_array($text) ? $text[$i] : $text) as $line) {
                $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $stream .= "({$line}) Tj 0 -15 Td\n";
            }
            $stream .= 'ET';
            $objects[$content] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.$pages.' /Kids ['.implode(' ', $kids).'] >>';
        if ($embedded !== null) {
            $stream = count($objects) + 1;
            $file = $stream + 1;
            $objects[$stream] = '<< /Type /EmbeddedFile /Subtype /application#2Fxml /Length '.strlen($embedded)." >>\nstream\n{$embedded}\nendstream";
            $objects[$file] = "<< /Type /Filespec /F (invoice.isdoc) /EF << /F {$stream} 0 R >> >>";
            $objects[1] = "<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names [(invoice.isdoc) {$file} 0 R] >> >> >>";
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF\n";

        return UploadedFile::fake()->createWithContent('synthetic.pdf', $pdf);
    }

    #[Test]
    #[DataProvider('storageModes')]
    public function detection_offers_text_draft_without_returning_fields_or_using_quota(bool $ephemeral): void
    {
        $this->actingAs($this->user)->postJson($this->root($ephemeral).'detect-isdoc', ['file' => $this->pdf($this->invoice())])
            ->assertOk()->assertJsonPath('data.has_isdoc', false)
            ->assertJsonPath('data.extraction_source', 'pdf_text')->assertJsonPath('data.quota.used', 0)
            ->assertJsonMissingPath('data.total')->assertJsonMissingPath('data.text')
            ->assertJsonMissingPath('data.supplier_iban');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNoExpenseStored();
    }

    #[Test]
    #[DataProvider('storageModes')]
    public function text_extraction_returns_a_reviewable_draft_and_records_one_usage(bool $ephemeral): void
    {
        $this->actingAs($this->user)->postJson($this->root($ephemeral).'extract', ['file' => $this->pdf($this->invoice())])
            ->assertOk()->assertJsonPath('data.source', 'pdf_text')
            ->assertJsonPath('data.payment_review_required', true)
            ->assertJsonPath('data.external_number', 'INV-001')
            ->assertJsonPath('data.variable_symbol', '0000123')
            ->assertJsonPath('data.supplier_iban', 'SK6807200002891987426353')
            ->assertJsonPath('data.total', 19.5)->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('quota.used', 1)->assertJsonMissingPath('data.mark_paid')
            ->assertJsonMissingPath('data.paid_at')->assertJsonMissingPath('data.text');
        $audit = AuditLog::where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->sole();
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertNull($audit->target_id);
        $this->assertSame($ephemeral ? ['billing' => 'free'] : ['company_id' => $this->company->id, 'billing' => 'free'], $audit->metadata);
        $this->assertNoExpenseStored();
    }

    #[Test]
    #[DataProvider('storageModes')]
    public function quota_remains_required_before_text_extraction_and_detection_remains_free(bool $ephemeral): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        $this->actingAs($this->user)->postJson($this->root($ephemeral).'extract', [
            'file' => UploadedFile::fake()->createWithContent('synthetic.pdf', '%PDF invalid'),
        ])->assertUnprocessable()->assertJsonValidationErrors('quota');
        $this->postJson($this->root($ephemeral).'detect-isdoc', ['file' => $this->pdf($this->invoice())])
            ->assertOk()->assertJsonPath('data.extraction_source', 'pdf_text')->assertJsonPath('data.quota.can_extract', false);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[Test]
    public function purchased_text_extraction_uses_only_the_authenticated_users_existing_credit(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        $other = User::factory()->create();
        ExpenseIsdocCreditBalance::create(['user_id' => $this->user->id, 'balance' => 2]);
        ExpenseIsdocCreditBalance::create(['user_id' => $other->id, 'balance' => 7]);
        $this->actingAs($this->user)->postJson($this->root(true).'extract', ['file' => $this->pdf($this->invoice())])
            ->assertOk()->assertJsonPath('quota.purchased_credits', 1);
        $this->assertSame(7, (int) ExpenseIsdocCreditBalance::where('user_id', $other->id)->value('balance'));
        $this->assertSame(['billing' => 'purchased'], AuditLog::where('user_id', $this->user->id)->sole()->metadata);
    }

    #[Test]
    public function malformed_empty_and_over_limit_pdfs_do_not_consume_quota_or_leak_text(): void
    {
        $files = [
            UploadedFile::fake()->createWithContent('synthetic.pdf', '%PDF invalid PRIVATE-SYNTHETIC-MARKER'),
            $this->pdf(''), $this->pdf('ordinary document'),
            $this->pdf($this->invoice(), null, 26),
            $this->pdf('Invoice no.: INV-001'.str_repeat('x', 200001)),
        ];
        $this->actingAs($this->user);
        foreach ($files as $file) {
            $response = $this->postJson($this->root(true).'extract', ['file' => $file]);
            $response->assertUnprocessable()->assertJsonValidationErrors('file');
            $this->assertStringNotContainsString('PRIVATE-SYNTHETIC-MARKER', $response->getContent());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNoExpenseStored();
    }

    #[Test]
    public function embedded_xml_failures_never_downgrade_to_the_visible_text(): void
    {
        $this->actingAs($this->user);
        foreach (['<?xml version="1.0"?><Invoice broken>', str_repeat('x', 262144).'<?xml version="1.0"?><Invoice/>'] as $case => $xml) {
            $detection = $this->postJson($this->root(true).'detect-isdoc', ['file' => $this->pdf($this->invoice(), $xml)]);
            if ($case === 0) {
                $detection->assertUnprocessable()->assertJsonValidationErrors('file');
                $this->assertStringNotContainsString('Premature end', $detection->getContent());
            } else {
                $detection->assertOk()->assertJsonPath('data.has_isdoc', false)->assertJsonPath('data.extraction_source', null);
            }
            $this->postJson($this->root(true).'extract', ['file' => $this->pdf($this->invoice(), $xml)])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNoExpenseStored();
    }

    #[Test]
    public function valid_embedded_isdoc_wins_over_conflicting_visible_text(): void
    {
        $doc = BusinessDocument::create([
            'company_id' => $this->company->id, 'type' => 'invoice', 'status' => BusinessDocumentStatus::Issued,
            'number' => 'STRUCTURED-001', 'variable_symbol' => '0000042', 'total' => 25,
            'subtotal' => 25, 'tax_total' => 0, 'currency' => 'EUR', 'issue_date' => '2026-11-01',
            'due_date' => '2026-11-15', 'payment_bank_enabled' => true,
        ]);
        BusinessDocumentLine::create([
            'business_document_id' => $doc->id, 'sort_order' => 0, 'name' => 'Synthetic service',
            'quantity' => 1, 'unit' => 'unit', 'unit_price' => 25, 'line_total' => 25, 'tax_rate' => 0,
        ]);
        $xml = app(BusinessDocumentIsdocService::class)->xml($doc->fresh(['company', 'contact', 'lines']));
        $this->actingAs($this->user)->postJson($this->root(true).'detect-isdoc', ['file' => $this->pdf($this->invoice(), $xml)])
            ->assertOk()->assertJsonPath('data.has_isdoc', true)->assertJsonPath('data.extraction_source', 'isdoc');
        $this->postJson($this->root(true).'extract', ['file' => $this->pdf($this->invoice(), $xml)])
            ->assertOk()->assertJsonPath('data.source', 'isdoc')
            ->assertJsonPath('data.external_number', 'STRUCTURED-001')->assertJsonPath('data.total', 25);
    }

    #[Test]
    public function text_pdf_cannot_bypass_review_through_the_legacy_direct_import_endpoint(): void
    {
        $this->actingAs($this->user)->postJson($this->root(false).'import', ['file' => $this->pdf($this->invoice()), 'mark_paid' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNoExpenseStored();
    }

    #[Test]
    public function actual_pdf_page_boundaries_distinguish_an_invoice_summary_from_an_annex(): void
    {
        $main = $this->invoice();
        $annex = "Synthetic annex\nTotal due: 3.00 EUR\nPaid: 3.00 EUR";
        $this->actingAs($this->user);
        foreach ([[$main, $annex], [$annex, $main]] as $pages) {
            $response = $this->postJson($this->root(true).'extract', ['file' => $this->pdf($pages)]);
            $response->assertOk()->assertJsonPath('data.total', 19.5)->assertJsonPath('data.payment_review_required', true);
            $this->assertTrue(ExpensePaymentReview::hasPaymentEvidence($response->json('data.internal_note')));
        }
    }

    private function assertNoExpenseStored(): void
    {
        $this->assertDatabaseCount('business_expenses', 0);
        $this->assertDatabaseCount('business_expense_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
