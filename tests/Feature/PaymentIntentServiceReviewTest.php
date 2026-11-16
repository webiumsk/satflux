<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentPaymentAttempt;
use App\Models\BusinessDocumentPaymentIntent;
use App\Models\Company;
use App\Models\EphemeralBtcpayCheckout;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConfigurationAttempt;
use App\Services\BtcPay\InvoiceService;
use App\Services\Invoicing\BankQrGenerator;
use App\Services\Invoicing\BusinessDocumentBtcPayService;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\BusinessDocumentMarkPaidService;
use App\Services\Invoicing\BusinessDocumentPaymentIntentService;
use App\Services\Invoicing\BusinessDocumentPdfService;
use App\Services\Invoicing\EphemeralBtcpayCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentIntentServiceReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private Store $store;

    private BusinessDocumentPaymentIntentService $intents;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.public_url' => 'https://btcpay.example', 'data_retention.clear_payment_token_when_paid' => false]);
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['btcpay_api_key' => 'synthetic-review-key']);
        $this->company = Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Synthetic review issuer',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR', 'vat_payer' => false]);
        $this->store = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
        $this->intents = app(BusinessDocumentPaymentIntentService::class);
    }

    private function document(bool $persisted = true, string $total = '100.00'): BusinessDocument
    {
        $doc = new BusinessDocument(['company_id' => $this->company->id, 'store_id' => $this->store->id,
            'type' => BusinessDocumentType::Invoice, 'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001', 'subtotal' => $total, 'tax_total' => '0.00', 'total' => $total,
            'currency' => 'EUR', 'payment_btc_enabled' => true, 'payment_bank_enabled' => true]);
        $doc->setRelation('company', $this->company);
        $doc->setRelation('store', $this->store);
        if ($persisted) {
            $doc->save();
            $doc->lines()->create(['name' => 'Synthetic service', 'quantity' => 1, 'unit_price' => $total,
                'line_total' => $total, 'tax_rate' => 0]);
        }

        return $doc;
    }

    private function invoice(array $replace = []): array
    {
        return array_replace(['id' => 'old-invoice', 'amount' => '100.00', 'currency' => 'EUR',
            'status' => 'Settled', 'additionalStatus' => 'None', 'paidAmount' => '100.00',
            'checkoutLink' => 'https://btcpay.example/i/old-invoice'], $replace);
    }

    #[Test]
    public function retained_historical_paid_intent_cannot_settle_an_edited_document_through_outer_sync(): void
    {
        $doc = $this->document();
        $intent = $this->intents->prepare($doc, $this->store);
        $intent->update(['btcpay_invoice_id' => 'old-invoice']);
        $doc->update(['btcpay_invoice_id' => 'old-invoice']);
        Http::fake(fn () => Http::response($this->invoice()));
        $this->intents->status($intent);
        app(BusinessDocumentMarkPaidService::class)->unmarkPaid($doc);
        $doc->update(['total' => '200.00', 'subtotal' => '200.00']);
        $this->assertFalse(app(BusinessDocumentBtcPayService::class)->syncPaidFromBtcpayIfSettled($doc));
        $this->assertSame(BusinessDocumentStatus::Issued, $doc->fresh()->status);
        $this->assertNull($doc->fresh()->paid_payment_method);
    }

    #[Test]
    public function partial_bank_balance_can_be_settled_by_the_exact_outstanding_btc_amount(): void
    {
        $doc = $this->document();
        $doc->update(['amount_paid' => '25.00']);
        $intent = $this->intents->prepare($doc, $this->store);
        Http::fake(fn ($request) => Http::response($this->invoice(['amount' => '75.00',
            'status' => $request->method() === 'POST' ? 'New' : 'Settled',
            'paidAmount' => $request->method() === 'POST' ? '0' : '75.00'])));
        $this->intents->activate($intent);
        $this->assertTrue(app(BusinessDocumentBtcPayService::class)->syncPaidFromBtcpayIfSettled($doc));
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        $this->assertSame('100.00', $doc->fresh()->amount_paid);
    }

    #[Test]
    public function issue_acquires_the_payment_mutex_before_starting_its_row_transaction(): void
    {
        $doc = $this->document();
        $doc->update(['status' => BusinessDocumentStatus::Draft, 'number' => null]);
        $level = DB::transactionLevel();
        $levels = [];
        $manager = Cache::getFacadeRoot();
        $proxy = \Mockery::mock($manager);
        $proxy->shouldReceive('lock')->andReturnUsing(function ($name, $seconds = 0, $owner = null) use ($manager, &$levels) {
            if (str_starts_with($name, 'business-payment:')) {
                $levels[] = DB::transactionLevel();
            }

            return $manager->lock($name, $seconds, $owner);
        });
        Cache::swap($proxy);
        Http::fake();
        app(BusinessDocumentIssueService::class)->issue($doc);
        $this->assertNotEmpty($levels);
        $this->assertSame($level, $levels[0]);
        $this->assertSame(BusinessDocumentStatus::Issued, $doc->fresh()->status);
        Http::assertNothingSent();
    }

    public static function sourceKinds(): array
    {
        return ['local first' => [false], 'persisted' => [true]];
    }

    #[Test]
    #[DataProvider('sourceKinds')]
    public function an_unpaid_expired_old_face_value_can_be_safely_replaced_after_an_edit(bool $persisted): void
    {
        $doc = $this->document($persisted, '200.00');
        if ($persisted) {
            $doc->update(['btcpay_invoice_id' => 'old-invoice']);
        } else {
            EphemeralBtcpayCheckout::create(['user_id' => $this->owner->id, 'store_id' => $this->store->id,
                'evolu_document_id' => 'edited-local', 'btcpay_invoice_id' => 'old-invoice', 'status' => 'pending',
                'amount' => '100.00', 'currency' => 'EUR']);
        }
        $intent = $this->intents->prepare($doc, $this->store, 'edited-local');
        $calls = 0;
        Http::fake(function ($request) use (&$calls) {
            if ($request->method() === 'GET') {
                return Http::response($this->invoice(['status' => 'Expired', 'paidAmount' => '0']));
            }
            $calls++;
            $this->assertSame('200.00', $request['amount']);

            return Http::response($this->invoice(['id' => 'fresh-invoice', 'amount' => '200.00',
                'status' => 'New', 'paidAmount' => '0', 'checkoutLink' => 'https://btcpay.example/i/fresh-invoice']));
        });
        $this->assertSame('fresh-invoice', $this->intents->activate($intent)['btcpay_invoice_id']);
        $this->assertSame(1, $calls);
        $attempt = BusinessDocumentPaymentAttempt::where('btcpay_invoice_id', 'old-invoice')->sole();
        $this->assertSame('100.00', $attempt->terms['amount']);
        $this->assertSame('200.00', $intent->fresh()->terms['amount']);
    }

    #[Test]
    public function a_missing_merchant_key_is_retryable_without_an_attempt_or_provider_call(): void
    {
        $doc = $this->document(false);
        $intent = $this->intents->prepare($doc, $this->store, 'missing-key');
        $this->owner->update(['btcpay_api_key' => null]);
        Http::fake(fn () => Http::response($this->invoice(['status' => 'New', 'paidAmount' => '0'])));
        $this->intents->activate($intent);
        $this->assertSame('active', $intent->fresh()->state);
        $this->assertDatabaseCount('business_document_payment_attempts', 0);
        Http::assertNothingSent();
        $this->owner->update(['btcpay_api_key' => 'synthetic-restored-key']);
        $this->assertSame('old-invoice', $this->intents->activate($intent->fresh())['btcpay_invoice_id']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function an_unresolved_wallet_update_stays_fail_closed_and_is_retryable_after_resolution(): void
    {
        $intent = $this->intents->prepare($this->document(false), $this->store, 'wallet-preflight');
        $wallet = WalletConfigurationAttempt::create(['store_id' => $this->store->id,
            'connection_type' => 'lightning', 'encrypted_secret' => 'synthetic', 'status' => 'uncertain']);
        Http::fake(fn () => Http::response($this->invoice(['status' => 'New', 'paidAmount' => '0'])));
        $this->intents->activate($intent);
        $this->assertSame('active', $intent->fresh()->state);
        $this->assertDatabaseCount('business_document_payment_attempts', 0);
        Http::assertNothingSent();
        $wallet->update(['status' => 'resolved']);
        $this->assertSame('old-invoice', $this->intents->activate($intent->fresh())['btcpay_invoice_id']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_confirmed_client_setup_failure_before_post_remains_retryable(): void
    {
        $intent = $this->intents->prepare($this->document(false), $this->store, 'client-preflight');
        $invoices = \Mockery::mock(InvoiceService::class);
        $invoices->shouldReceive('createInvoice')->once()->andThrow(new \InvalidArgumentException('Synthetic client setup failed.'));
        $service = new BusinessDocumentPaymentIntentService($invoices, app(EphemeralBtcpayCheckoutService::class));
        $service->activate($intent);
        $this->assertSame('active', $intent->fresh()->state);
        $this->assertDatabaseCount('business_document_payment_attempts', 0);
    }

    #[Test]
    public function a_connection_failure_after_submission_is_still_unknown_and_never_retried(): void
    {
        $intent = $this->intents->prepare($this->document(false), $this->store, 'transport-uncertain');
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('Synthetic lost response.');
        });
        $this->intents->activate($intent);
        $this->intents->activate($intent->fresh());
        $this->assertSame('unknown', $intent->fresh()->state);
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function an_interleaved_edit_refreshes_the_entire_persisted_pdf_snapshot_under_the_mutex(): void
    {
        $doc = $this->document();
        $stale = $doc->fresh(['company', 'store', 'lines']);
        $mutated = false;
        $manager = Cache::getFacadeRoot();
        $proxy = \Mockery::mock($manager);
        $proxy->shouldReceive('lock')->andReturnUsing(function ($name, $seconds = 0, $owner = null) use ($manager, $doc, &$mutated) {
            if (! $mutated && str_starts_with($name, 'business-payment:')) {
                $mutated = true;
                $doc->update(['total' => '200.00', 'subtotal' => '200.00']);
                $doc->lines()->update(['unit_price' => '200.00', 'line_total' => '200.00']);
                $this->intents->prepare($doc->fresh(['company', 'store']), $this->store);
            }

            return $manager->lock($name, $seconds, $owner);
        });
        Cache::swap($proxy);
        $seen = [];
        $bank = \Mockery::mock(BankQrGenerator::class);
        $bank->shouldReceive('generateQrDataUri')->andReturnUsing(function ($company, $document) use (&$seen) {
            $seen[] = $document->total;

            return null;
        });
        $this->app->instance(BankQrGenerator::class, $bank);
        Process::fake(['*' => Process::result(exitCode: 1)]);
        Http::fake();
        $pdf = app(BusinessDocumentPdfService::class);
        $view = (new \ReflectionMethod($pdf, 'viewDataForDocument'))->invoke($pdf, $stale);
        $this->assertSame(['200.00'], $seen);
        $this->assertSame('200.00', $view['document']->total);
        $this->assertSame('active', BusinessDocumentPaymentIntent::sole()->state);
        $this->assertStringContainsString('/pay/i/', $view['btcPayUrl']);
        Http::assertNothingSent();
    }

    #[Test]
    public function outer_sync_discovers_an_older_settled_attempt_while_the_latest_checkout_is_new(): void
    {
        $doc = $this->document();
        $intent = $this->intents->prepare($doc, $this->store);
        $oldPaid = false;
        $posts = 0;
        Http::fake(function ($request) use (&$oldPaid, &$posts) {
            if ($request->method() === 'POST') {
                $posts++;

                return Http::response($this->invoice(['id' => 'attempt-'.$posts, 'status' => 'New', 'paidAmount' => '0']));
            }
            if (str_ends_with($request->url(), '/attempt-1')) {
                return Http::response($this->invoice(['id' => 'attempt-1', 'status' => $oldPaid ? 'Settled' : 'Expired',
                    'paidAmount' => $oldPaid ? '100' : '0', 'additionalStatus' => $oldPaid ? 'PaidLate' : 'None']));
            }

            return Http::response($this->invoice(['id' => 'attempt-2', 'status' => 'New', 'paidAmount' => '0']));
        });
        $this->intents->activate($intent);
        $this->intents->activate($intent->fresh());
        $this->assertSame('attempt-2', $doc->fresh()->btcpay_invoice_id);
        $oldPaid = true;
        $this->assertTrue(app(BusinessDocumentBtcPayService::class)->syncPaidFromBtcpayIfSettled($doc));
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        $this->assertSame('attempt-1', $intent->fresh()->settled_invoice_id);
        $this->assertSame(2, $posts);
    }

    #[Test]
    public function losing_the_claim_inside_the_wallet_guard_never_posts_or_reopens_the_offer(): void
    {
        $intent = $this->intents->prepare($this->document(false), $this->store, 'claim-loser');
        $manager = Cache::getFacadeRoot();
        $proxy = \Mockery::mock($manager);
        $proxy->shouldReceive('lock')->andReturnUsing(function ($name, $seconds = 0, $owner = null) use ($manager, $intent) {
            if (str_starts_with($name, 'wallet-update:')) {
                $this->intents->updateState($this->owner, $intent->id, $intent->fresh()->version, 'revoked');
            }

            return $manager->lock($name, $seconds, $owner);
        });
        Cache::swap($proxy);
        Http::fake();
        $this->intents->activate($intent);
        $this->assertSame('revoked', $intent->fresh()->state);
        $this->assertDatabaseCount('business_document_payment_attempts', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function explicit_outer_reconciliation_recovers_the_original_legacy_partial_face_from_the_provider(): void
    {
        $doc = $this->document();
        $doc->update(['amount_paid' => '25.00', 'btcpay_invoice_id' => 'old-invoice']);
        $intent = $this->intents->prepare($doc, $this->store);
        $attempt = BusinessDocumentPaymentAttempt::where('intent_id', $intent->id)->sole();
        $this->assertNull($attempt->terms['amount']);
        Http::fake(fn () => Http::response($this->invoice(['amount' => '75.00', 'paidAmount' => '75.00'])));
        $this->assertTrue(app(BusinessDocumentBtcPayService::class)->syncPaidFromBtcpayIfSettled($doc));
        $this->assertSame('75.00', $attempt->fresh()->terms['amount']);
        $this->assertSame('100.00', $doc->fresh()->amount_paid);
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    public static function unsafeOldStates(): array
    {
        return [
            'new' => [['status' => 'New', 'paidAmount' => '0']],
            'processing' => [['status' => 'Processing']],
            'partial expired' => [['status' => 'Expired', 'additionalStatus' => 'PaidPartial', 'paidAmount' => '20']],
            'invalid' => [['status' => 'Invalid']],
            'unknown' => [['status' => 'Unrecognized', 'paidAmount' => '0']],
            'settled smaller amount' => [['status' => 'Settled']],
        ];
    }

    #[Test]
    #[DataProvider('unsafeOldStates')]
    public function edited_legacy_terms_never_replace_or_settle_from_payable_partial_or_unknown_old_funds(array $state): void
    {
        $doc = $this->document(true, '200.00');
        $doc->update(['btcpay_invoice_id' => 'old-invoice']);
        $intent = $this->intents->prepare($doc, $this->store);
        Http::fake(fn () => Http::response($this->invoice($state)));
        $result = $this->intents->activate($intent);
        $this->assertNull($result['checkout_link']);
        $this->assertNotSame('paid', $intent->fresh()->state);
        $this->assertSame(BusinessDocumentStatus::Issued, $doc->fresh()->status);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    #[Test]
    public function a_stale_prepare_cannot_revoke_a_current_valid_persisted_offer(): void
    {
        $doc = $this->document();
        $stale = $doc->fresh(['company', 'store']);
        $doc->update(['total' => '200.00', 'subtotal' => '200.00']);
        $intent = $this->intents->prepare($doc->fresh(['company', 'store']), $this->store);
        Http::fake();
        $observed = $this->intents->prepare($stale, $this->store);
        $this->assertSame($intent->token, $observed->token);
        $this->assertSame('active', $intent->fresh()->state);
        $this->assertSame('200.00', $intent->fresh()->terms['amount']);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_stale_btc_disabled_render_uses_one_fresh_visible_snapshot(): void
    {
        $doc = $this->document();
        $doc->update(['payment_btc_enabled' => false, 'payment_bank_enabled' => false]);
        $stale = $doc->fresh(['company', 'store', 'lines']);
        $doc->update(['payment_btc_enabled' => true]);
        Process::fake(['*' => Process::result(exitCode: 1)]);
        Http::fake();
        $pdf = app(BusinessDocumentPdfService::class);
        $view = (new \ReflectionMethod($pdf, 'viewDataForDocument'))->invoke($pdf, $stale);
        $this->assertTrue($view['document']->payment_btc_enabled);
        $this->assertStringContainsString('/pay/i/', $view['btcPayUrl']);
        Http::assertNothingSent();
    }

    #[Test]
    public function the_creation_claim_is_committed_before_post_under_the_authoritative_wallet_guard(): void
    {
        $intent = $this->intents->prepare($this->document(false), $this->store, 'durable-boundary');
        $level = DB::transactionLevel();
        Http::fake(function () use ($intent, $level) {
            $current = $intent->fresh();
            $this->assertSame('creating', $current->state);
            $this->assertDatabaseHas('business_document_payment_attempts', ['id' => $current->attempt_id, 'state' => 'creating']);
            $this->assertSame($level, DB::transactionLevel());
            $this->assertFalse(Cache::lock('wallet-update:'.$this->store->id, 1)->get());

            return Http::response($this->invoice(['status' => 'New', 'paidAmount' => '0']));
        });
        $this->intents->activate($intent);
        Http::assertSentCount(1);
    }
}
