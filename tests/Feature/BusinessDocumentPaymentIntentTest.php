<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentPaymentIntent;
use App\Models\Company;
use App\Models\EphemeralBtcpayCheckout;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use App\Services\Invoicing\BusinessDocumentMarkPaidService;
use App\Services\Invoicing\BusinessDocumentPaymentIntentService;
use App\Services\Invoicing\BusinessDocumentPaymentWebhookService;
use App\Services\Invoicing\EphemeralBtcpayCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentPaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private Company $company;

    private BusinessDocumentPaymentIntentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.public_url' => 'https://btcpay.example']);
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['btcpay_api_key' => 'test-key']);
        $this->company = Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Synthetic issuer',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
        $this->store = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
        $this->service = app(BusinessDocumentPaymentIntentService::class);
    }

    private function document(bool $persisted = false): BusinessDocument
    {
        $document = new BusinessDocument(['company_id' => $this->company->id, 'store_id' => $this->store->id,
            'type' => BusinessDocumentType::Invoice, 'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001', 'total' => '100.00', 'currency' => 'EUR', 'payment_btc_enabled' => true]);
        $document->setRelation('company', $this->company);
        $document->setRelation('store', $this->store);
        if ($persisted) {
            $document->save();
        }

        return $document;
    }

    private function intent(?BusinessDocument $document = null): BusinessDocumentPaymentIntent
    {
        return $this->service->prepare($document ?? $this->document(), $this->store, 'local-document');
    }

    private function invoice(array $changes = []): array
    {
        return array_replace(['id' => 'invoice-one', 'checkoutLink' => 'https://btcpay.example/i/invoice-one',
            'status' => 'New', 'amount' => '100.00', 'currency' => 'EUR', 'paidAmount' => '0', 'additionalStatus' => 'None'], $changes);
    }

    #[Test]
    public function preparation_and_get_head_are_stable_and_never_contact_btcpay(): void
    {
        Http::fake();
        $intent = $this->intent();
        $this->assertSame($intent->token, $this->intent()->token);
        $url = '/pay/e/'.$intent->token;
        $this->get($url)->assertOk()->assertSee('method="POST"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->head($url)->assertOk();
        Http::assertNothingSent();
        $this->assertDatabaseCount('ephemeral_btcpay_checkouts', 0);
        $raw = DB::table('business_document_payment_intents')->value('terms');
        $this->assertStringNotContainsString('100.00', $raw);
        $this->assertStringNotContainsString('20260001', $raw);
        $this->assertArrayNotHasKey('terms', $intent->toArray());
    }

    #[Test]
    public function post_creates_once_and_later_public_and_merchant_requests_reuse_it(): void
    {
        Http::fake(fn () => Http::response($this->invoice()));
        $document = $this->document();
        $intent = $this->intent($document);
        $this->post('/pay/e/'.$intent->token)->assertStatus(303)->assertRedirect('https://btcpay.example/i/invoice-one');
        $this->post('/pay/e/'.$intent->token)->assertStatus(303);
        $this->assertSame('invoice-one', $this->service->merchantCheckout($document, $this->store, 'local-document')['btcpay_invoice_id']);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertDatabaseCount('ephemeral_btcpay_checkouts', 1);
    }

    #[Test]
    public function a_nested_activation_observes_durable_creating_and_cannot_create_again(): void
    {
        $intent = $this->intent();
        Http::fake(function () use ($intent) {
            $this->assertSame('creating', $intent->fresh()->state);
            $this->assertNotNull($intent->fresh()->attempt_id);
            $nested = $this->service->activate($intent->fresh());
            $this->assertSame('creating', $nested['status']);

            return Http::response($this->invoice());
        });
        $this->service->activate($intent);
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_lost_response_is_not_retried_and_permanently_blocks_blind_creation(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('synthetic lost response');
        });
        $intent = $this->intent();
        $this->service->activate($intent);
        $this->assertSame('unknown', $intent->fresh()->state);
        $this->service->activate($intent->fresh());
        $this->assertSame(1, $calls);
        $this->assertNotNull($intent->fresh()->attempt_id);
        $this->assertSame(3, $intent->fresh()->version);
    }

    #[Test]
    public function malformed_success_retains_recovered_identity_and_never_creates_again(): void
    {
        Http::fake(fn () => Http::response(['id' => 'invoice-one', 'checkoutLink' => 'https://foreign.invalid/i/one']));
        $intent = $this->intent();
        $this->service->activate($intent);
        $this->service->activate($intent->fresh());
        Http::assertSentCount(1);
        $this->assertSame('unknown', $intent->fresh()->state);
        $this->assertSame('invoice-one', $intent->fresh()->btcpay_invoice_id);
    }

    public static function nonReplaceableStates(): array
    {
        return [
            'processing' => [['status' => 'Processing', 'paidAmount' => '100']],
            'invalid' => [['status' => 'Invalid', 'paidAmount' => '100']],
            'expired partial' => [['status' => 'Expired', 'additionalStatus' => 'PaidPartial', 'paidAmount' => '10']],
            'expired missing amount' => [['status' => 'Expired', 'paidAmount' => null]],
            'expired malformed amount' => [['status' => 'Expired', 'paidAmount' => 'bad']],
            'unknown status' => [['status' => 'Mystery']],
            'archived new' => [['archived' => true]],
            'manual marked' => [['status' => 'Settled', 'additionalStatus' => 'Marked', 'paidAmount' => '100']],
            'settled underpaid' => [['status' => 'Settled', 'paidAmount' => '20']],
            'wrong currency' => [['status' => 'Settled', 'paidAmount' => '100', 'currency' => 'USD']],
            'wrong id' => [['id' => 'foreign-invoice']],
        ];
    }

    #[Test]
    #[DataProvider('nonReplaceableStates')]
    public function unsupported_or_inflight_evidence_never_replaces_or_confirms(array $changes): void
    {
        $intent = $this->intent();
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-one'])->save();
        Http::fake(fn () => Http::response($this->invoice($changes)));
        $this->service->activate($intent);
        $this->assertSame('active', $this->service->status($intent)->state);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    #[Test]
    public function only_positively_unpaid_expired_checkout_can_be_replaced(): void
    {
        $intent = $this->intent();
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-old'])->save();
        Http::fake(fn ($request) => Http::response($request->method() === 'GET'
            ? $this->invoice(['id' => 'invoice-old', 'status' => 'Expired']) : $this->invoice()));
        $this->assertSame('invoice-one', $this->service->activate($intent)['btcpay_invoice_id']);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    #[Test]
    public function exact_confirmed_settlement_marks_persisted_invoice_paid_with_btc_provenance(): void
    {
        $document = $this->document(true);
        $intent = $this->intent($document);
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-one'])->save();
        Http::fake(fn () => Http::response($this->invoice(['status' => 'Settled', 'paidAmount' => '100'])));
        $this->assertSame('paid', $this->service->status($intent)->state);
        $this->assertSame('btcpay', $document->fresh()->paid_payment_method);
        $this->assertSame(BusinessDocumentStatus::Paid, $document->fresh()->status);
    }

    #[Test]
    public function changed_persisted_total_blocks_old_settlement_and_partial_payment_revokes_offer(): void
    {
        $document = $this->document(true);
        $intent = $this->intent($document);
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-one'])->save();
        $document->update(['total' => '200.00']);
        Http::fake(fn () => Http::response($this->invoice(['status' => 'Settled', 'paidAmount' => '100'])));
        $this->assertSame('revoked', $this->service->status($intent)->state);
        $this->assertSame(BusinessDocumentStatus::Issued, $document->fresh()->status);
        Http::assertNothingSent();
        app(BusinessDocumentMarkPaidService::class)->markPaid($document, 50, null, 'bank_manual');
        $this->assertSame('revoked', $intent->fresh()->state);
    }

    #[Test]
    public function bank_paid_and_revoked_states_survive_stale_render_and_store_deletion(): void
    {
        Http::fake();
        $document = $this->document();
        $intent = $this->intent($document);
        $this->service->updateState($this->owner, $intent->id, 1, 'paid');
        $this->assertNull($this->service->url($this->intent($document)));
        $this->assertSame('external', $intent->fresh()->payment_method);
        $this->store->delete();
        $this->assertNotNull($intent->fresh());
        $this->assertNull($intent->fresh()->store_id);
        Http::assertNothingSent();
    }

    #[Test]
    public function deliberate_reopen_rotates_token_and_stale_waiting_token_cannot_activate(): void
    {
        $intent = $this->intent();
        $old = clone $intent;
        $this->service->updateState($this->owner, $intent->id, 1, 'revoked');
        Http::fake(fn () => Http::response($this->invoice()));
        $this->service->merchantCheckout($this->document(), $this->store, 'local-document', 2);
        $this->assertNotSame($old->token, $intent->fresh()->token);
        $this->assertSame('revoked', $this->service->activate($old)['status']);
        $this->get('/pay/e/'.$old->token)->assertNotFound();
        Http::assertSentCount(1);
    }

    #[Test]
    public function safety_apis_are_owner_scoped_compare_and_swap_and_available_without_a_plan(): void
    {
        Http::fake();
        $intent = $this->intent();
        $foreign = User::factory()->create();
        $this->actingAs($foreign)->getJson('/api/invoicing/ephemeral/btcpay-payment-links')->assertOk()->assertJsonCount(0, 'data');
        $url = '/api/invoicing/ephemeral/btcpay-payment-links/'.$intent->id.'/state';
        $this->postJson($url, ['expected_version' => 1, 'state' => 'revoked'])->assertNotFound();
        $this->actingAs($this->owner)->getJson('/api/invoicing/ephemeral/btcpay-payment-links')->assertOk()
            ->assertJsonPath('data.0.number', '20260001');
        $this->postJson($url, ['expected_version' => 2, 'state' => 'revoked'])->assertConflict();
        $this->postJson($url, ['expected_version' => 1, 'state' => 'paid'])->assertOk()
            ->assertJsonPath('data.payment_method', 'external');
        $this->getJson('/api/invoicing/ephemeral/btcpay-payment-link-status?evolu_document_id=local-document')
            ->assertOk()->assertJsonPath('data.state', 'paid')->assertJsonPath('data.payment_method', 'external');
        $this->postJson($url, ['expected_version' => 2, 'state' => 'active'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    #[Test]
    public function same_owner_store_company_reassignment_revokes_the_old_capability(): void
    {
        $intent = $this->intent();
        $other = Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Other issuer',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
        $this->store->update(['company_id' => $other->id]);
        Http::fake();
        $this->service->activate($intent);
        $this->assertSame('revoked', $intent->fresh()->state);
        Http::assertNothingSent();
    }

    #[Test]
    public function older_confirmed_mapping_prevents_payment_through_a_newer_duplicate(): void
    {
        foreach (['older-paid', 'newer-unpaid'] as $id) {
            EphemeralBtcpayCheckout::create(['user_id' => $this->owner->id, 'store_id' => $this->store->id,
                'evolu_document_id' => 'local-document', 'btcpay_invoice_id' => $id, 'status' => 'pending',
                'amount' => '100.00', 'currency' => 'EUR']);
        }
        $intent = $this->intent();
        Http::fake(fn ($request) => Http::response($this->invoice(str_contains($request->url(), 'older-paid')
            ? ['id' => 'older-paid', 'status' => 'Settled', 'paidAmount' => '100'] : ['id' => 'newer-unpaid'])));
        $this->service->activate($intent);
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame('older-paid', $this->service->payload($intent->fresh())['btcpay_invoice_id']);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    #[Test]
    public function late_settlement_of_replaced_attempt_remains_bound_and_is_reconciled(): void
    {
        $intent = $this->intent();
        $oldPaid = false;
        $posts = 0;
        Http::fake(function ($request) use (&$oldPaid, &$posts) {
            if ($request->method() === 'POST') {
                $posts++;

                return Http::response($this->invoice(['id' => 'attempt-'.$posts]));
            }
            if (str_ends_with($request->url(), '/attempt-1')) {
                return Http::response($this->invoice(['id' => 'attempt-1', 'status' => $oldPaid ? 'Settled' : 'Expired',
                    'paidAmount' => $oldPaid ? '100' : '0', 'additionalStatus' => $oldPaid ? 'PaidLate' : 'None']));
            }

            return Http::response($this->invoice(['id' => 'attempt-2']));
        });
        $this->service->activate($intent);
        $this->assertSame('attempt-2', $this->service->activate($intent->fresh())['btcpay_invoice_id']);
        $this->assertSame(2, $posts);
        $this->assertDatabaseCount('business_document_payment_attempts', 2);
        $oldPaid = true;
        $handled = app(BusinessDocumentPaymentWebhookService::class)->handleInvoicePayment(
            'InvoiceSettled', ['invoiceId' => 'attempt-1'], $this->store);
        $this->assertTrue($handled);
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame('attempt-1', $this->service->payload($intent->fresh())['btcpay_invoice_id']);
        $this->assertSame('attempt-1', $this->service->activate($intent->fresh())['btcpay_invoice_id']);
        $this->assertSame(2, $posts);
    }

    #[Test]
    public function a_terminal_update_during_provider_creation_is_not_overwritten_by_completion(): void
    {
        $intent = $this->intent();
        Http::fake(function () use ($intent) {
            $current = $intent->fresh();
            $this->service->updateState($this->owner, $intent->id, $current->version, 'paid');

            return Http::response($this->invoice());
        });
        $result = $this->service->activate($intent);
        $this->assertNull($result['checkout_link']);
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame('external', $intent->fresh()->payment_method);
        $this->service->activate($intent->fresh());
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_terminal_update_during_status_read_cannot_return_a_payable_checkout(): void
    {
        $intent = $this->intent();
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-one'])->save();
        Http::fake(function () use ($intent) {
            $this->service->updateState($this->owner, $intent->id, $intent->fresh()->version, 'revoked');

            return Http::response($this->invoice());
        });
        $this->assertNull($this->service->activate($intent)['checkout_link']);
        $this->assertSame('revoked', $intent->fresh()->state);
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_total_change_during_settlement_read_cannot_settle_the_larger_document(): void
    {
        $document = $this->document(true);
        $intent = $this->intent($document);
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-one'])->save();
        Http::fake(function () use ($document) {
            $document->update(['total' => '200.00']);

            return Http::response($this->invoice(['status' => 'Settled', 'paidAmount' => '100']));
        });
        $this->assertSame('revoked', $this->service->status($intent)->state);
        $this->assertSame(BusinessDocumentStatus::Issued, $document->fresh()->status);
        $this->assertNull($document->fresh()->paid_payment_method);
    }

    #[Test]
    public function mapping_persistence_failure_retains_attempt_and_blocks_duplicate_creation(): void
    {
        $mapping = \Mockery::mock(EphemeralBtcpayCheckoutService::class);
        $mapping->shouldReceive('registerCheckout')->once()->andThrow(new \RuntimeException('synthetic mapping write failure'));
        $service = new BusinessDocumentPaymentIntentService(app(InvoiceService::class), $mapping);
        $intent = $this->intent();
        Http::fake(fn () => Http::response($this->invoice()));
        $service->activate($intent);
        $service->activate($intent->fresh());
        $this->assertSame('unknown', $intent->fresh()->state);
        $this->assertDatabaseHas('business_document_payment_attempts', ['intent_id' => $intent->id,
            'btcpay_invoice_id' => 'invoice-one', 'state' => 'unknown']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function partial_bank_payment_revokes_the_original_offer_without_reporting_btc_settlement(): void
    {
        $document = $this->document(true);
        $intent = $this->intent($document);
        Http::fake();
        app(BusinessDocumentMarkPaidService::class)->markPaid($document, 50, null, 'bank_manual');
        $this->assertSame('revoked', $intent->fresh()->state);
        $this->assertNull($intent->fresh()->payment_method);
        $this->assertSame('50.00', $document->fresh()->amount_paid);
        Http::assertNothingSent();
    }

    #[Test]
    public function public_creation_requires_a_valid_csrf_token(): void
    {
        $intent = $this->intent();
        Http::fake(fn () => Http::response($this->invoice()));
        // Only disable Laravel's unit-test CSRF shortcut. The already resolved test database,
        // caches, mail and fake provider remain the isolated testing configuration.
        $environment = $this->app['env'];
        $this->app['env'] = 'csrf-regression';
        try {
            $this->post('/pay/e/'.$intent->token)->assertStatus(419);
            Http::assertNothingSent();
            $this->withSession(['_token' => 'synthetic-csrf-token'])
                ->post('/pay/e/'.$intent->token, ['_token' => 'synthetic-csrf-token'])->assertStatus(303);
            Http::assertSentCount(1);
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public static function changedPaidTerms(): array
    {
        return ['amount' => ['amount'], 'currency' => ['currency'], 'store' => ['store'], 'number' => ['number']];
    }

    #[Test]
    #[DataProvider('changedPaidTerms')]
    public function a_historical_paid_intent_cannot_mark_revised_unmarked_terms_paid(string $change): void
    {
        $document = $this->document(true);
        $intent = $this->intent($document);
        $intent->forceFill(['btcpay_invoice_id' => 'invoice-one'])->save();
        Http::fake(fn () => Http::response($this->invoice(['status' => 'Settled', 'paidAmount' => '100'])));
        $this->service->status($intent);
        $this->assertSame(BusinessDocumentStatus::Paid, $document->fresh()->status);
        app(BusinessDocumentMarkPaidService::class)->unmarkPaid($document);
        if ($change === 'amount') {
            $document->update(['total' => '200.00']);
        } elseif ($change === 'currency') {
            $document->update(['currency' => 'USD']);
        } elseif ($change === 'number') {
            $document->update(['number' => '20260002']);
        } else {
            $other = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
            $document->update(['store_id' => $other->id]);
        }
        $this->service->status($intent->fresh());
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame(BusinessDocumentStatus::Issued, $document->fresh()->status);
        $this->assertNull($document->fresh()->paid_payment_method);
        $this->get('/pay/e/'.$intent->token)->assertOk()->assertSee(__('messages.business_invoice_pay_unavailable'), false)
            ->assertDontSee(__('messages.business_invoice_pay_already_paid'), false);
        $document->update(['payment_token' => str_repeat('l', 64)]);
        $this->get('/pay/i/'.$document->payment_token)->assertOk()->assertSee(__('messages.business_invoice_pay_unavailable'), false)
            ->assertDontSee(__('messages.business_invoice_pay_already_paid'), false);
    }

    #[Test]
    public function older_unresolved_attempts_do_not_hide_a_confirmed_current_payment(): void
    {
        foreach (['older-unavailable', 'current-paid'] as $id) {
            EphemeralBtcpayCheckout::create(['user_id' => $this->owner->id, 'store_id' => $this->store->id,
                'evolu_document_id' => 'local-document', 'btcpay_invoice_id' => $id, 'status' => 'pending',
                'amount' => '100.00', 'currency' => 'EUR']);
        }
        $intent = $this->intent();
        Http::fake(fn ($request) => Http::response($this->invoice(str_contains($request->url(), 'current-paid')
            ? ['id' => 'current-paid', 'status' => 'Settled', 'paidAmount' => '100'] : ['id' => 'older-unavailable', 'status' => 'Unknown'])));
        $this->service->status($intent);
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame('current-paid', $intent->fresh()->settled_invoice_id);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    #[Test]
    public function older_open_attempt_does_not_hide_a_later_historical_settlement(): void
    {
        foreach (['older-open', 'middle-paid', 'current-open'] as $id) {
            EphemeralBtcpayCheckout::create(['user_id' => $this->owner->id, 'store_id' => $this->store->id,
                'evolu_document_id' => 'local-document', 'btcpay_invoice_id' => $id, 'status' => 'pending',
                'amount' => '100.00', 'currency' => 'EUR']);
        }
        $intent = $this->intent();
        Http::fake(fn ($request) => Http::response($this->invoice(str_contains($request->url(), 'middle-paid')
            ? ['id' => 'middle-paid', 'status' => 'Settled', 'paidAmount' => '100'] : ['id' => 'older-open'])));
        $this->service->status($intent);
        $this->assertSame('paid', $intent->fresh()->state);
        $this->assertSame('middle-paid', $intent->fresh()->settled_invoice_id);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }
}
