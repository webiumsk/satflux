<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessRecurringProfile;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Integrations\WooCommerceDocumentService;
use App\Services\Invoicing\BusinessDocumentFromProformaService;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\DocumentSequenceService;
use App\Services\Invoicing\RecurringDocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Creation / duplication audit 2026-10: what a new document inherits from
 * its source and how it gets its number.
 */
class DocumentCreationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected CompanyContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-05-10 12:00:00'));

        $plan = SubscriptionPlan::create([
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
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
        ]);
        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);

        $this->contact = CompanyContact::create([
            'company_id' => $this->company->id,
            'name' => 'SITMAR, s.r.o.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function issuedDocument(BusinessDocumentType $type, array $attributes = []): BusinessDocument
    {
        $document = BusinessDocument::create([
            'company_id' => $this->company->id,
            'company_contact_id' => $this->contact->id,
            'type' => $type,
            'status' => BusinessDocumentStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 87,
            'tax_total' => 0,
            'total' => 87,
            ...$attributes,
        ]);
        $document->lines()->create([
            'sort_order' => 0,
            'name' => 'Služba',
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 87,
            'line_total' => 87,
        ]);

        return app(BusinessDocumentIssueService::class)->issue($document);
    }

    public function test_invoice_from_quote_gets_its_own_variable_symbol_and_asks_for_payment(): void
    {
        // Quotes are stored with payment off (store() forces it).
        $quote = $this->issuedDocument(BusinessDocumentType::Quote, [
            'payment_bank_enabled' => false,
            'pdf_show_payment_info' => false,
        ]);
        $quote->update(['quote_status' => BusinessDocumentQuoteStatus::Approved]);

        $invoice = $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$quote->id}/create-invoice-from-quote")
            ->assertCreated()
            ->json('data');

        $this->assertSame('INV20260001', $invoice['number']);
        // Not the quote's 20260001 digits - the invoice's own number.
        $this->assertSame('20260001', $invoice['variable_symbol']);
        $this->assertNotSame($quote->variable_symbol, 'QT'.$invoice['variable_symbol']);
        $this->assertTrue($invoice['payment_bank_enabled']);
        $this->assertTrue($invoice['pdf_show_payment_info']);
    }

    public function test_final_invoice_from_a_paid_proforma_opens_no_btc_checkout_and_is_created_once(): void
    {
        $proforma = $this->issuedDocument(BusinessDocumentType::Proforma, ['payment_btc_enabled' => true]);
        $proforma->update([
            'status' => BusinessDocumentStatus::Paid,
            'paid_at' => now(),
            'amount_paid' => 87,
        ]);

        $final = $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$proforma->id}/create-final-invoice")
            ->assertCreated()
            ->json('data');

        $this->assertSame('paid', $final['status']);
        $this->assertFalse($final['payment_btc_enabled']);
        $this->assertFalse($final['pdf_show_payment_info']);

        $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$proforma->id}/create-final-invoice")
            ->assertStatus(422);
        $this->assertSame(1, BusinessDocument::query()->where('source_document_id', $proforma->id)->count());
    }

    public function test_duplicate_is_a_new_unsent_document_without_the_source_number_or_frozen_buyer(): void
    {
        $source = $this->issuedDocument(BusinessDocumentType::Invoice);
        $source->update([
            'title' => 'Faktúra '.$source->number,
            'email_sent_at' => now(),
            'buyer_snapshot' => ['name' => 'Old buyer'],
        ]);

        $copy = $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/{$source->id}/duplicate")
            ->assertCreated()
            ->json('data');

        $this->assertSame('draft', $copy['status']);
        $this->assertNull($copy['number']);
        $this->assertNull($copy['title']);
        $this->assertNull($copy['email_sent_at']);
        $this->assertNull(BusinessDocument::query()->findOrFail($copy['id'])->buyer_snapshot);
    }

    public function test_next_number_skips_a_number_another_document_type_already_holds(): void
    {
        // Two types sharing a format: the credit note took INV20260001.
        BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => BusinessDocumentType::CreditNote,
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'INV20260001',
            'total' => 1,
            'currency' => 'EUR',
        ]);

        $this->assertSame('INV20260002', app(DocumentSequenceService::class)->nextNumber($this->company, 'invoice'));
    }

    public function test_a_recurring_profile_is_not_generated_twice_for_the_same_period(): void
    {
        $profile = BusinessRecurringProfile::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'is_active' => true,
            'recurrence_interval' => 'yearly',
            'first_issue_date' => now()->toDateString(),
            'next_issue_date' => now()->toDateString(),
            'repeat_indefinitely' => true,
            'title' => 'Faktúra #INVOICE_NUMBER#',
            'currency' => 'EUR',
            'total' => 100,
            'payment_terms_days' => 14,
        ]);
        $profile->lines()->create([
            'sort_order' => 0,
            'name' => 'Služba',
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        // Two requests that loaded the profile before either generated.
        $first = BusinessRecurringProfile::query()->findOrFail($profile->id);
        $second = BusinessRecurringProfile::query()->findOrFail($profile->id);
        $generator = app(RecurringDocumentGeneratorService::class);

        $generator->generateForProfile($first);

        $this->expectException(ValidationException::class);
        try {
            $generator->generateForProfile($second);
        } finally {
            $this->assertSame(1, BusinessDocument::query()->where('company_id', $this->company->id)->count());
        }
    }

    public function test_woo_order_lookup_matches_the_exact_order_of_the_same_shop(): void
    {
        $store = Store::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id]);
        $integration = StoreIntegration::createForStore($store)['integration'];
        config(['invoicing.woocommerce_inbox_mode' => false, 'invoicing.local_first' => false]);
        $service = app(WooCommerceDocumentService::class);

        $payload = fn (int $orderId) => [
            'woocommerce_order_id' => $orderId,
            'type' => 'invoice',
            'currency' => 'EUR',
            'buyer' => ['name' => 'Jane Buyer', 'email' => 'jane@example.com'],
            'lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 0]],
        ];

        $order120 = $service->createDocument($integration, $payload(120));
        $order12 = $service->createDocument($integration, $payload(12));

        // "%woocommerce_order_id=12%" used to return order 120's document.
        $this->assertNotSame($order120->id, $order12->id);
        $this->assertSame($order12->id, $service->createDocument($integration, $payload(12))->id);
    }

    public function test_a_deactivated_recurring_profile_is_not_generated(): void
    {
        $profile = BusinessRecurringProfile::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'is_active' => true,
            'recurrence_interval' => 'yearly',
            'first_issue_date' => now()->toDateString(),
            'next_issue_date' => now()->toDateString(),
            'repeat_indefinitely' => true,
            'currency' => 'EUR',
            'total' => 100,
            'payment_terms_days' => 14,
        ]);
        $profile->lines()->create([
            'sort_order' => 0,
            'name' => 'Služba',
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        // Loaded while active, deactivated before the run took the lock.
        $stale = BusinessRecurringProfile::query()->findOrFail($profile->id);
        $profile->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        try {
            app(RecurringDocumentGeneratorService::class)->generateForProfile($stale);
        } finally {
            $this->assertSame(0, BusinessDocument::query()->where('company_id', $this->company->id)->count());
        }
    }

    public function test_final_invoice_rechecks_the_proforma_under_the_lock(): void
    {
        $proforma = $this->issuedDocument(BusinessDocumentType::Proforma);
        $proforma->update(['status' => BusinessDocumentStatus::Paid, 'paid_at' => now(), 'amount_paid' => 87]);

        // A stale copy still says "paid" - the row was unmarked meanwhile.
        $stale = BusinessDocument::query()->findOrFail($proforma->id);
        $proforma->update(['status' => BusinessDocumentStatus::Issued, 'paid_at' => null, 'amount_paid' => null]);

        try {
            app(BusinessDocumentFromProformaService::class)->createFinalInvoice($this->company, $stale);
            $this->fail('A no longer paid proforma must not be converted.');
        } catch (ValidationException) {
            $this->assertSame(0, BusinessDocument::query()->where('source_document_id', $proforma->id)->count());
        }
    }
}
