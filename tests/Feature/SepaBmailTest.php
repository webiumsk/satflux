<?php

namespace Tests\Feature;

use App\Enums\BankTransactionDirection;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\BankInboundEmailService;
use App\Services\SepaEmailConfirmationService;
use App\Support\Invoicing\ParsedBankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Strict authenticated Tatra plaintext runs through the real production parser.
 * QR values in the public historical layout are explicitly synthetic adaptations.
 * Unsupported body formats remain on manual review.
 */
class SepaBmailTest extends TestCase
{
    use RefreshDatabase;

    private const RECIPIENT_IBAN = 'SK4811000000002944116480';

    private const E2E_REFERENCE = 'QR-ab29e346f1d841c8a95a63d857490818';

    private User $user;

    private Store $store;

    private BankInboundAddressService $addressService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bank_inbound.enabled' => true,
            'bank_inbound.webhook_secret' => 'test-inbound-secret',
            'bank_inbound.domain' => 'payments.satflux.io',
            'bank_inbound.address_prefix' => 'pay',
            'bank_inbound.store_address_prefix' => 'ps',
            'bank_inbound.max_address_length' => 50,
            'bank_inbound.reject_forwarded' => true,
            'services.btcpay.base_url' => 'https://btcpay.test',
        ]);

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create(['user_id' => $this->user->id]);
        $this->addressService = app(BankInboundAddressService::class);
    }

    private function reportUrl(): string
    {
        return "https://btcpay.test/api/v1/stores/{$this->store->btcpay_store_id}/plugins/sepa-instant-qr/payment-requests/report";
    }

    private function fakeCapablePlugin(int $version = 2): void
    {
        Http::fake([
            str_replace('/payment-requests/report', '/settings', $this->reportUrl()) => Http::response([
                'confirmationContractVersion' => $version,
            ]),
            $this->reportUrl() => Http::response(['outcome' => 'settled']),
        ]);
    }

    private function creditBody(string $reference = self::E2E_REFERENCE): string
    {
        return '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Referencia platitela: '.$reference."\n"
            .'Informacia pre prijemcu: Invoice payment';
    }

    /** Historical Tatra layout; account, balances and QR value are synthetic. */
    private function strictCreditBody(string $reference = self::E2E_REFERENCE, string $note = 'Invoice payment'): string
    {
        return "Vazeny klient,\n"
            .'01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            ."uctovny zostatok: 1 012,50 EUR\naktualny zostatok: 1 012,50 EUR\n"
            ."disponibilny zostatok: 1 012,50 EUR\nPopis transakcie: CCINT 1100/000000-261426464\n"
            .'Referencia platitela: '.$reference."\nInformacia pre prijemcu: ".$note."\n"
            ."S pozdravom\nTATRA BANKA, a.s.";
    }

    private function nativePayload(array $overrides = []): array
    {
        return array_replace([
            'to' => $this->addressService->buildStoreAddress($this->store),
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat na ucte',
            'body' => $this->creditBody(),
            'bank_authentication' => [
                'dkim_result' => 'pass',
                'dkim_domain' => 'tatrabanka.sk',
                'dmarc_result' => 'pass',
            ],
        ], $overrides);
    }

    /** Hypothetical structured bank evidence, not output of a current email parser. */
    private function trustedRow(string $reference = self::E2E_REFERENCE): ParsedBankTransaction
    {
        return new ParsedBankTransaction(
            bookedAt: now(), amount: 12.50, currency: 'EUR', direction: BankTransactionDirection::Credit,
            recipientIban: self::RECIPIENT_IBAN, isExplicitCredit: true, hasExplicitCurrency: true,
            e2eReference: $reference,
        );
    }

    private function verifiedPayload(): array
    {
        return [...$this->nativePayload(), '_verified_bank_domain' => 'tatrabanka.sk'];
    }

    #[Test]
    #[TestWith(['QR-ab29e346f1d841c8a95a63d857490818'])]
    #[TestWith(['/VS1234567890/SS/KS'])]
    public function current_bank_mail_references_require_manual_review(string $reference): void
    {
        Http::fake();
        $address = $this->addressService->buildStoreAddress($this->store);
        $this->assertLessThanOrEqual(50, strlen($address));
        $this->assertStringStartsWith('ps', $address);

        $this->postJson('/api/webhooks/bank-inbound', $this->nativePayload([
            'body' => $this->creditBody($reference),
        ]), ['X-Bank-Inbound-Secret' => 'test-inbound-secret'])
            ->assertOk()->assertJsonPath('accepted', true);

        Http::assertNothingSent();
        $audit = AuditLog::where('action', 'sepa.bmail_review_required')->sole();
        $this->assertSame('incomplete_credit_evidence', $audit->metadata['reason']);
    }

    #[Test]
    #[TestWith(['QR-ab29e346f1d841c8a95a63d857490818', 'QR-ab29e346f1d841c8a95a63d857490818'])]
    #[TestWith(['QR-AB29E346F1D841C8A95A63D857490818', 'QR-AB29E346F1D841C8A95A63D857490818'])]
    #[TestWith(['/VS1234567890/SS/KS', '1234567890'])]
    public function authenticated_strict_plaintext_runs_the_real_parser_and_reports_the_observed_credit(string $bankReference, string $expectedReference): void
    {
        $this->fakeCapablePlugin();
        $payload = $this->nativePayload(['body' => $this->strictCreditBody($bankReference)]);

        $this->postJson('/api/webhooks/bank-inbound', $payload, [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk()->assertJsonPath('accepted', true);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->data()['reference'] === $expectedReference
            && (float) $request->data()['amount'] === 12.50
            && $request->data()['currency'] === 'EUR'
            && $request->data()['creditorIban'] === self::RECIPIENT_IBAN
            && $request->data()['bankOriginVerified'] === true
            && strlen($request->data()['dedupKey']) === 64);
        $audit = AuditLog::where('action', 'sepa.bmail_processed')->sole();
        $this->assertSame(['reported' => 1, 'outcome' => 'settled'], $audit->metadata);
    }

    #[Test]
    #[TestWith(['QR-00000000000000000000000000000002'])]
    #[TestWith(['/VS1234567890/SS/KS'])]
    public function a_complete_bank_prefix_cannot_promote_a_reference_in_remittance_after_a_blank_bank_field(string $injectedReference): void
    {
        Http::fake();
        $body = str_replace('Referencia platitela: '.self::E2E_REFERENCE, 'Referencia platitela: ', $this->strictCreditBody());
        $body = str_replace('Invoice payment', "Invoice payment\nReferencia platitela: ".$injectedReference, $body);

        $this->postJson('/api/webhooks/bank-inbound', $this->nativePayload(['body' => $body]), [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
        $audit = AuditLog::where('action', 'sepa.bmail_review_required')->sole();
        $this->assertSame('incomplete_credit_evidence', $audit->metadata['reason']);
    }

    #[Test]
    public function one_bank_credit_keeps_the_same_dedup_key_across_candidate_notes_and_formatting(): void
    {
        $reports = [];
        Http::fake(function (Request $request) use (&$reports) {
            if ($request->method() === 'GET') {
                return Http::response(['confirmationContractVersion' => 2]);
            }
            $reports[] = $request->data();

            return Http::response(['outcome' => count($reports) === 1 ? 'settled' : 'duplicate']);
        });
        $first = $this->strictCreditBody();
        $second = $this->strictCreditBody('QR-00000000000000000000000000000002', 'Another invoice');
        $third = str_replace(['01.06.2026', '1 012,50', "\n"], ['1.6.2026', '1012,50', "\r\n  "], $second);
        $service = app(BankInboundEmailService::class);
        foreach ([$first, $second, $third] as $body) {
            $service->handle([...$this->verifiedPayload(), 'body' => $body]);
        }

        $this->assertCount(3, $reports);
        $this->assertNotSame($reports[0]['reference'], $reports[1]['reference']);
        $this->assertSame($reports[0]['dedupKey'], $reports[1]['dedupKey']);
        $this->assertSame($reports[1]['dedupKey'], $reports[2]['dedupKey']);
        // The companion provider owns atomic dedup reservation. This verifies
        // the real parser/consumer supplies the same event key to that boundary.
    }

    #[Test]
    public function trusted_structured_evidence_uses_the_store_account_bound_contract(): void
    {
        $this->fakeCapablePlugin();
        $result = app(SepaEmailConfirmationService::class)->handle(
            $this->store, $this->verifiedPayload(), [$this->trustedRow()],
        );

        $this->assertSame(['store_id' => $this->store->id, 'reported' => 1, 'outcome' => 'settled'], $result);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->data()['reference'] === self::E2E_REFERENCE
            && (float) $request->data()['amount'] === 12.50
            && $request->data()['currency'] === 'EUR'
            && $request->data()['creditorIban'] === self::RECIPIENT_IBAN
            && $request->data()['bankOriginVerified'] === true
            && strlen($request->data()['dedupKey']) === 64);
    }

    #[Test]
    #[TestWith(['missing'])]
    #[TestWith(['dkim-fail'])]
    #[TestWith(['dmarc-fail'])]
    #[TestWith(['non-bank-domain'])]
    #[TestWith(['suffix-impersonation'])]
    #[TestWith(['raw-headers-only'])]
    public function unverified_native_bank_origin_cannot_report_a_payment(string $case): void
    {
        Http::fake();
        $payload = $this->nativePayload(['body' => $this->strictCreditBody()]);
        if ($case === 'missing' || $case === 'raw-headers-only') {
            unset($payload['bank_authentication']);
        } elseif ($case === 'dkim-fail') {
            $payload['bank_authentication']['dkim_result'] = 'fail';
        } elseif ($case === 'dmarc-fail') {
            $payload['bank_authentication']['dmarc_result'] = 'fail';
        } else {
            $payload['bank_authentication']['dkim_domain'] = $case === 'non-bank-domain'
                ? 'attacker.test' : 'tatrabanka.sk.attacker.test';
        }
        $payload['bankOriginVerified'] = true;
        $payload['headers'] = 'Authentication-Results: attacker.test; dkim=pass header.d=tatrabanka.sk; dmarc=pass';

        $this->postJson('/api/webhooks/bank-inbound', $payload, [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function native_bank_attestation_cannot_bypass_the_delivery_secret(): void
    {
        Http::fake();

        $this->postJson('/api/webhooks/bank-inbound', $this->nativePayload(), [
            'X-Bank-Inbound-Secret' => 'wrong-secret',
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    #[TestWith(['missing-account'])]
    #[TestWith(['invalid-account'])]
    #[TestWith(['implicit-direction'])]
    #[TestWith(['implicit-currency'])]
    #[TestWith(['ambiguous-reference'])]
    public function incomplete_or_ambiguous_transaction_evidence_cannot_report(string $case): void
    {
        Http::fake();
        $body = match ($case) {
            'missing-account' => 'Kredit. Suma: 12,50 EUR. Referencia platitela: '.self::E2E_REFERENCE,
            'invalid-account' => str_replace(self::RECIPIENT_IBAN, 'SK4811000000002944116481', $this->creditBody()),
            'implicit-direction' => 'Suma: 12,50 EUR. VS: 1234567890.',
            'implicit-currency' => 'Kredit. Suma: 12,50. VS: 1234567890.',
            'ambiguous-reference' => $this->creditBody(self::E2E_REFERENCE.' QR-00000000000000000000000000000001'),
        };

        $this->postJson('/api/webhooks/bank-inbound', $this->nativePayload(['body' => $body]), [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([1])]
    public function old_plugin_without_enforced_confirmation_contract_receives_no_report(int $version): void
    {
        $this->fakeCapablePlugin($version);

        $result = app(SepaEmailConfirmationService::class)->handle(
            $this->store, $this->verifiedPayload(), [$this->trustedRow()],
        );

        $this->assertSame('review', $result['outcome']);
        $this->assertSame(0, $result['reported']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    #[Test]
    public function mailgun_delivery_authentication_does_not_attest_a_bank_sender(): void
    {
        Http::fake();
        $signingKey = 'synthetic-mailgun-signing-key';
        config(['bank_inbound.mailgun_webhook_signing_key' => $signingKey]);
        $timestamp = (string) time();
        $token = 'synthetic-mailgun-token';

        $this->postJson('/api/webhooks/bank-inbound', [
            'recipient' => $this->addressService->buildStoreAddress($this->store),
            'sender' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat na ucte',
            'body-plain' => $this->creditBody(),
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
            'bank_authentication' => $this->nativePayload()['bank_authentication'],
            'message-headers' => json_encode([
                ['Authentication-Results', 'attacker.test; dkim=pass header.d=tatrabanka.sk; dmarc=pass'],
                ['Authentication-Results', 'attacker.test; dkim=pass header.d=tatrabanka.sk; dmarc=pass'],
            ], JSON_THROW_ON_ERROR),
        ])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function authenticated_bank_mail_cannot_use_payer_text_as_a_larger_credit(bool $embeddedClassic): void
    {
        Http::fake();
        $body = "Prijata platba 1,00 EUR\nVas ucet: ".self::RECIPIENT_IBAN."\nInformacia pre prijemcu: ";
        $body .= $embeddedClassic
            ? "\n01.06.2026 12:31 bol zostatok Vasho uctu ".self::RECIPIENT_IBAN.' zvyseny o 100,00 EUR.'
            : 'Suma: 100,00 EUR';
        $body .= "\nReferencia platitela: ".self::E2E_REFERENCE;

        $this->postJson('/api/webhooks/bank-inbound', $this->nativePayload(['body' => $body]), [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    #[TestWith(['tatrabanka.sk', 'QR-ab29e346f1d841c8a95a63d857490818', false])]
    #[TestWith(['tatrabanka.sk', '/VS1234567890/SS/KS', false])]
    #[TestWith(['tatrabanka.sk', 'QR-ab29e346f1d841c8a95a63d857490818', true])]
    #[TestWith(['slsp.sk', 'QR-ab29e346f1d841c8a95a63d857490818', false])]
    public function a_genuine_credit_header_cannot_authenticate_an_injected_reference(string $domain, string $reference, bool $html): void
    {
        Http::fake();
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            ."Informacia pre prijemcu: Invoice payment\n"
            .'Referencia platitela: '.$reference;
        if ($html) {
            $body = '<div>'.str_replace("\n", "</div>\n<div>", $body).'</div>';
        }
        $payload = $this->nativePayload(['body' => $body, 'from' => 'notify@'.$domain]);
        $payload['bank_authentication']['dkim_domain'] = $domain;

        $this->postJson('/api/webhooks/bank-inbound', $payload, [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
        $audit = AuditLog::where('action', 'sepa.bmail_review_required')->sole();
        $this->assertSame('incomplete_credit_evidence', $audit->metadata['reason']);
    }

    #[Test]
    public function an_invoice_number_in_payer_remittance_cannot_become_a_payment_reference(): void
    {
        Http::fake();
        $body = '01.06.2026 12:31 bol zostatok Vasho uctu '.self::RECIPIENT_IBAN." zvyseny o 12,50 EUR.\n"
            .'Informacia pre prijemcu: Invoice 1234567890';

        $this->postJson('/api/webhooks/bank-inbound', $this->nativePayload(['body' => $body]), [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function a_later_settled_row_does_not_hide_an_earlier_review_outcome(): void
    {
        $secondReference = 'QR-00000000000000000000000000000002';
        Http::fake(fn (Request $request) => Http::response($request->method() === 'GET'
            ? ['confirmationContractVersion' => 2]
            : ['outcome' => $request->data()['reference'] === self::E2E_REFERENCE ? 'review' : 'settled']));
        $row = fn (string $reference) => new ParsedBankTransaction(
            bookedAt: now(), amount: 12.50, currency: 'EUR', direction: BankTransactionDirection::Credit,
            recipientIban: self::RECIPIENT_IBAN, isExplicitCredit: true, hasExplicitCurrency: true,
            e2eReference: $reference,
        );

        $result = app(SepaEmailConfirmationService::class)->handle($this->store, [
            ...$this->nativePayload(), '_verified_bank_domain' => 'tatrabanka.sk',
        ], [$row(self::E2E_REFERENCE), $row($secondReference)]);

        $this->assertSame(2, $result['reported']);
        $this->assertSame('review', $result['outcome']);
        Http::assertSentCount(3);
        $posts = Http::recorded(fn (Request $request) => $request->method() === 'POST')->values();
        $this->assertNotSame($posts[0][0]->data()['dedupKey'], $posts[1][0]->data()['dedupKey']);
    }

    #[Test]
    #[TestWith([['unknown'], 0])]
    #[TestWith([['unknown', 'settled'], 1])]
    #[TestWith([['settled', 'unknown'], 1])]
    #[TestWith([['unknown', 'review'], 1])]
    public function unknown_provider_outcomes_require_review_and_are_not_counted_as_reported(array $outcomes, int $reported): void
    {
        $next = 0;
        Http::fake(function (Request $request) use ($outcomes, &$next) {
            return Http::response($request->method() === 'GET'
                ? ['confirmationContractVersion' => 2]
                : ['outcome' => $outcomes[$next++]]);
        });
        $rows = [];
        foreach (array_keys($outcomes) as $index) {
            $rows[] = $this->trustedRow('QR-'.str_pad((string) $index, 32, '0', STR_PAD_LEFT));
        }

        $result = app(SepaEmailConfirmationService::class)->handle($this->store, $this->verifiedPayload(), $rows);

        $this->assertSame($reported, $result['reported']);
        $this->assertSame('review', $result['outcome']);
        $audit = AuditLog::where('action', 'sepa.bmail_processed')->sole();
        $this->assertSame(['reported' => $reported, 'outcome' => 'review'], $audit->metadata);
        Http::assertSentCount(count($outcomes) + 1);
    }

    #[Test]
    public function failed_report_can_retry_with_the_original_payment_identity(): void
    {
        $unavailable = true;
        $reports = [];
        Http::fake(function (Request $request) use (&$unavailable, &$reports) {
            if ($request->method() === 'GET') {
                return Http::response(['confirmationContractVersion' => 2]);
            }

            $reports[] = $request->data();

            return $unavailable
                ? Http::response(['message' => 'Synthetic provider unavailable'], 503)
                : Http::response(['outcome' => 'settled']);
        });
        $service = app(SepaEmailConfirmationService::class);
        $payload = $this->verifiedPayload();
        $rows = [$this->trustedRow()];

        try {
            $service->handle($this->store, $payload, $rows);
            $this->fail('An unavailable report must propagate to the existing queue retry path.');
        } catch (BtcPayException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $unavailable = false;
        $result = $service->handle($this->store, $payload, $rows);

        $this->assertSame(1, $result['reported']);
        $this->assertSame('settled', $result['outcome']);
        $this->assertCount(2, $reports);
        $this->assertSame($reports[0], $reports[1]);
        $this->assertSame(64, strlen($reports[1]['dedupKey']));
    }

    #[Test]
    public function debit_notifications_are_ignored(): void
    {
        Http::fake();

        $address = $this->addressService->buildStoreAddress($this->store);
        $this->postJson('/api/webhooks/bank-inbound', [
            'to' => $address,
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat na ucte - debet',
            'body' => 'Debet. Suma: -12,50 EUR. Referencia platitela: QR-ab29e346f1d841c8a95a63d857490818.',
        ], [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function unknown_store_token_still_rejects(): void
    {
        Http::fake();
        $service = app(BankInboundEmailService::class);

        $this->expectException(ValidationException::class);

        $service->handle([
            'to' => 'psunknown00000@payments.satflux.io',
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat',
            'body' => 'Suma: 1,00 EUR. VS: 1',
        ]);
    }

    #[Test]
    public function store_tokens_are_stable_and_unique(): void
    {
        $first = $this->addressService->buildStoreAddress($this->store);
        $second = $this->addressService->buildStoreAddress($this->store->fresh());
        $this->assertSame($first, $second);

        $other = Store::factory()->create(['user_id' => $this->user->id]);
        $this->assertNotSame($first, $this->addressService->buildStoreAddress($other));
    }

    #[Test]
    public function inbound_email_endpoint_returns_the_address(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/stores/{$this->store->id}/sepa/inbound-email");

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
        $this->assertStringStartsWith('ps', $response->json('data.address'));
    }

    #[Test]
    public function disabled_channel_generates_no_token_on_get(): void
    {
        config(['bank_inbound.enabled' => false]);
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/stores/{$this->store->id}/sepa/inbound-email");

        $response->assertOk();
        $response->assertJsonPath('data.enabled', false);
        $response->assertJsonPath('data.address', null);
        $this->assertNull($this->store->fresh()->bank_inbound_token);
    }

    #[Test]
    public function issued_addresses_survive_a_config_length_change(): void
    {
        $address = $this->addressService->buildStoreAddress($this->store);

        // shortening the max length changes the COMPUTED token length -
        // already issued addresses must still resolve
        config(['bank_inbound.max_address_length' => 40]);

        $owner = $this->addressService->resolveOwner($address);
        $this->assertTrue($owner->is($this->store));
    }

    #[Test]
    public function token_creation_is_audited(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson("/api/stores/{$this->store->id}/sepa/inbound-email")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sepa.bmail_address_created',
            'target_id' => $this->store->id,
        ]);
    }
}
