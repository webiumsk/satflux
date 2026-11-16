<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Http\Controllers\Invoicing\BusinessDocumentPaymentIntentPayController;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\BusinessDocumentPaymentIntentService;
use App\Services\Invoicing\EphemeralBtcpayCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentIntentFollowupReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private Store $store;

    private BusinessDocumentPaymentIntentService $intents;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['btcpay_api_key' => 'synthetic-followup-key']);
        $this->company = Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Synthetic issuer',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
        $this->store = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id,
            'name' => 'Owned review store']);
        $this->intents = app(BusinessDocumentPaymentIntentService::class);
    }

    private function document(bool $persisted = true): BusinessDocument
    {
        $document = new BusinessDocument(['company_id' => $this->company->id, 'store_id' => $this->store->id,
            'type' => BusinessDocumentType::Invoice, 'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001', 'subtotal' => '100.00', 'total' => '100.00', 'tax_total' => '0.00',
            'currency' => 'EUR', 'payment_btc_enabled' => true]);
        $document->setRelation('company', $this->company);
        $document->setRelation('store', $this->store);
        if ($persisted) {
            $document->save();
            $document->lines()->create(['name' => 'Synthetic service', 'quantity' => 1,
                'unit_price' => '100.00', 'line_total' => '100.00', 'tax_rate' => 0]);
        }

        return $document;
    }

    private function otherCompany(): Company
    {
        return Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Other issuer',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
    }

    #[Test]
    public function owned_store_mismatch_is_actionable_before_any_issuance_transaction(): void
    {
        $this->actingAs($this->owner);
        $document = $this->document();
        $document->update(['status' => BusinessDocumentStatus::Draft, 'number' => null]);
        $this->store->update(['company_id' => $this->otherCompany()->id]);
        $transactions = 0;
        DB::connection()->beforeStartingTransaction(function () use (&$transactions) {
            $transactions++;
        });
        try {
            app(BusinessDocumentIssueService::class)->issue($document);
            $this->fail('A mismatched Bitcoin store must stop issuance.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, $transactions);
            $message = $exception->errors()['store_id'][0];
            $this->assertStringNotContainsString($this->store->name, $message);
            $this->assertStringContainsString($this->store->id, $message);
            $this->assertStringContainsString('Relink', $message);
            $this->assertStringContainsString('disable Bitcoin', $message);
            $this->assertStringNotContainsString($this->store->btcpay_store_id, $message);
        }
        $this->assertSame(BusinessDocumentStatus::Draft, $document->fresh()->status);
        $this->assertNull($document->fresh()->number);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_store_reassignment_after_preflight_is_rechecked_before_number_allocation(): void
    {
        $this->actingAs($this->owner);
        $document = $this->document();
        $document->update(['status' => BusinessDocumentStatus::Draft, 'number' => null]);
        $other = $this->otherCompany();
        $changed = false;
        $transactions = 0;
        DB::connection()->beforeStartingTransaction(function () use (&$changed, &$transactions, $other) {
            $transactions++;
            if (! $changed) {
                $changed = true;
                $this->store->update(['company_id' => $other->id]);
            }
        });
        try {
            app(BusinessDocumentIssueService::class)->issue($document);
            $this->fail('The fresh locked store assignment must stop issuance.');
        } catch (ValidationException $exception) {
            $this->assertSame(1, $transactions);
            $this->assertStringContainsString('disable Bitcoin', $exception->errors()['store_id'][0]);
        }
        $this->assertSame(BusinessDocumentStatus::Draft, $document->fresh()->status);
        $this->assertNull($document->fresh()->number);
        Http::assertNothingSent();
    }

    #[Test]
    public function delegated_issuers_receive_only_the_selected_local_store_identifier(): void
    {
        $this->actingAs(User::factory()->create());
        $document = $this->document();
        $document->update(['status' => BusinessDocumentStatus::Draft, 'number' => null]);
        $this->store->update(['company_id' => $this->otherCompany()->id]);
        try {
            app(BusinessDocumentIssueService::class)->issue($document);
            $this->fail('An unlinked store cannot issue Bitcoin payments.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['store_id'][0];
            $this->assertStringNotContainsString($this->store->name, $message);
            $this->assertStringContainsString($this->store->id, $message);
            $this->assertStringNotContainsString($this->store->btcpay_store_id, $message);
            $this->assertStringContainsString('disable Bitcoin', $message);
        }
    }

    #[Test]
    public function an_already_issued_document_keeps_its_status_validation(): void
    {
        $document = $this->document();
        $this->store->update(['company_id' => $this->otherCompany()->id]);
        try {
            app(BusinessDocumentIssueService::class)->issue($document);
            $this->fail('An issued document cannot be issued again.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertArrayNotHasKey('store_id', $exception->errors());
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function issuance_preflight_uses_fresh_bitcoin_settings(): void
    {
        $document = $this->document();
        $document->update(['status' => BusinessDocumentStatus::Draft, 'number' => null, 'payment_btc_enabled' => false]);
        BusinessDocument::whereKey($document->id)->update(['payment_btc_enabled' => true]);
        $this->store->update(['company_id' => $this->otherCompany()->id]);
        try {
            app(BusinessDocumentIssueService::class)->issue($document);
            $this->fail('Stale disabled settings cannot bypass fresh store validation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('disable Bitcoin', $exception->errors()['store_id'][0]);
        }
        $this->assertSame(BusinessDocumentStatus::Draft, $document->fresh()->status);
        $this->assertNull($document->fresh()->number);
        Http::assertNothingSent();
    }

    public static function disabledPayments(): array
    {
        return ['Bitcoin disabled' => [false, true], 'no payment store' => [true, false]];
    }

    #[Test]
    #[DataProvider('disabledPayments')]
    public function issuance_without_configured_bitcoin_payments_keeps_its_existing_contract(bool $enabled, bool $hasStore): void
    {
        $document = $this->document();
        $document->update(['status' => BusinessDocumentStatus::Draft, 'number' => null,
            'payment_btc_enabled' => $enabled, 'store_id' => $hasStore ? $this->store->id : null]);
        $this->store->update(['company_id' => $this->otherCompany()->id]);
        $issued = app(BusinessDocumentIssueService::class)->issue($document);
        $this->assertSame(BusinessDocumentStatus::Issued, $issued->status);
        $this->assertNotEmpty($issued->number);
        $this->assertDatabaseCount('business_document_payment_intents', 0);
        Http::assertNothingSent();
    }

    public static function publicStates(): array
    {
        return [
            'issued active' => ['active', false, 'ready', 'ready'],
            'issued paid' => ['paid', false, 'paid', 'paid'],
            'issued revoked' => ['revoked', false, 'unavailable', 'unavailable'],
            'issued unknown' => ['unknown', false, 'unavailable', 'unavailable'],
            'issued creating' => ['creating', false, 'unavailable', 'unavailable'],
            'paid active' => ['active', true, 'paid', 'ready'],
            'paid paid' => ['paid', true, 'paid', 'paid'],
            'paid revoked' => ['revoked', true, 'paid', 'unavailable'],
            'paid unknown' => ['unknown', true, 'paid', 'unavailable'],
            'paid creating' => ['creating', true, 'paid', 'unavailable'],
        ];
    }

    #[Test]
    #[DataProvider('publicStates')]
    public function get_and_post_fallback_state_precedence_is_preserved_without_provider_reads(string $state, bool $paid, string $get, string $post): void
    {
        $document = $this->document();
        $intent = $this->intents->prepare($document, $this->store);
        $intent->update(['state' => $state]);
        if ($paid) {
            $document->update(['status' => BusinessDocumentStatus::Paid, 'amount_paid' => '100.00']);
        }
        $service = \Mockery::mock($this->intents);
        $service->shouldReceive('activate')->once()->andReturn(['checkout_link' => null]);
        $this->app->instance(BusinessDocumentPaymentIntentService::class, $service);
        $url = '/pay/e/'.$intent->token;
        $this->get($url)->assertOk()->assertViewHas('state', $get);
        $this->post($url)->assertOk()->assertViewHas('state', $post);
        $this->assertSame($state, $intent->fresh()->state);
        Http::assertNothingSent();
    }

    #[Test]
    public function an_active_intent_cannot_override_an_explicit_paid_presentation(): void
    {
        $intent = $this->intents->prepare($this->document(false), $this->store, 'paid-precedence');

        $this->assertSame('paid', $this->intents->publicAvailability($intent, paid: true));
        $this->assertSame('active', $intent->fresh()->state);
        Http::assertNothingSent();
    }

    #[Test]
    public function activating_an_already_paid_document_keeps_the_paid_page_without_provider_calls(): void
    {
        $document = $this->document();
        $intent = $this->intents->prepare($document, $this->store);
        $document->update(['status' => BusinessDocumentStatus::Paid, 'amount_paid' => '100.00']);

        $this->post('/pay/e/'.$intent->token)->assertOk()->assertViewHas('state', 'paid')
            ->assertDontSee('method="POST"', false);
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame('external', $intent->fresh()->payment_method);
        Http::assertNothingSent();
    }

    #[Test]
    public function an_active_capability_never_becomes_ready_for_a_foreign_owned_store(): void
    {
        foreach ([false, true] as $persisted) {
            $document = $this->document($persisted);
            $intent = $this->intents->prepare($document, $this->store, 'owner-check');
            $this->store->update(['user_id' => User::factory()->create()->id]);
            $this->get('/pay/e/'.$intent->token)->assertOk()->assertViewHas('state', 'unavailable');
            $this->store->update(['user_id' => $this->owner->id]);
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function legacy_nullable_intent_and_ready_terms_keep_their_existing_precedence(): void
    {
        $controller = new BusinessDocumentPaymentIntentPayController($this->intents);
        $method = new \ReflectionMethod($controller, 'landing');
        foreach ([[false, false, null, 'unavailable'], [false, true, null, 'paid'],
            [false, false, ['amount' => '1.00', 'currency' => 'EUR'], 'ready'],
            [true, true, ['amount' => '1.00', 'currency' => 'EUR'], 'unavailable']] as [$unavailable, $paid, $terms, $expected]) {
            $response = $method->invoke($controller, null, '/synthetic', $unavailable, $paid, $terms);
            $this->assertSame($expected, $response->getOriginalContent()->getData()['state']);
        }
        Http::assertNothingSent();
    }

    public static function failingStages(): array
    {
        return ['preflight' => [false, false], 'submission' => [true, false],
            'preflight logger outage' => [false, true], 'submission logger outage' => [true, true]];
    }

    #[Test]
    #[DataProvider('failingStages')]
    public function failures_report_only_safe_metadata_without_changing_recovery(bool $submitted, bool $loggerFails): void
    {
        $document = $this->document(false);
        $intent = $this->intents->prepare($document, $this->store, 'report-safe');
        $secret = 'synthetic-private-token-merchant-email@example.test';
        $cause = new \LogicException('synthetic-response-body-secret');
        $original = new \RuntimeException($secret, 0, $cause);
        $client = \Mockery::mock(InvoiceService::class);
        $client->shouldReceive('createInvoice')->once()->andReturnUsing(function (...$args) use ($submitted, $original) {
            if ($submitted) {
                $this->assertTrue($args[4]());
            }
            throw $original;
        });
        $records = [];
        $manager = Log::getFacadeRoot();
        $logger = \Mockery::mock($manager);
        $logger->shouldReceive('warning')->once()->andReturnUsing(function ($message, $context) use (&$records, $loggerFails) {
            if ($loggerFails) {
                throw new \RuntimeException('Synthetic logging sink outage.');
            }
            $records[] = [$message, $context];
        });
        Log::swap($logger);
        $service = new BusinessDocumentPaymentIntentService($client, app(EphemeralBtcpayCheckoutService::class));
        $result = $service->activate($intent);
        $this->assertSame($submitted ? 'unknown' : 'active', $result['status']);
        $this->assertDatabaseCount('business_document_payment_attempts', $submitted ? 1 : 0);
        if (! $loggerFails) {
            $this->assertCount(1, $records);
            $this->assertSame('Business invoice Bitcoin checkout failed.', $records[0][0]);
            $this->assertSame(['phase' => $submitted ? 'submission' : 'preflight', 'exception_type' => \RuntimeException::class], $records[0][1]);
            $encoded = json_encode($records, JSON_THROW_ON_ERROR);
            foreach ([$secret, 'synthetic-response-body-secret', $intent->token, $this->owner->email, $this->store->btcpay_store_id] as $private) {
                $this->assertStringNotContainsString($private, $encoded);
            }
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function an_unknown_attempt_without_provider_identity_cannot_be_reopened(): void
    {
        $document = $this->document(false);
        $intent = $this->intents->prepare($document, $this->store, 'unknown-reopen');
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('Synthetic unknown submission.');
        });
        $this->intents->activate($intent);
        try {
            $this->intents->merchantCheckout($document, $this->store, 'unknown-reopen', $intent->fresh()->version);
            $this->fail('An unresolved attempt cannot be reopened.');
        } catch (ValidationException) {
            $this->assertSame('unknown', $intent->fresh()->state);
            $this->assertNull($intent->fresh()->btcpay_invoice_id);
            $this->assertSame(1, $calls);
        }
    }
}
