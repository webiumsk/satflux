<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Jobs\ProcessBankNotificationEmail;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\BankMimeVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BankCompanyMimeImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private string $recipient;

    private const BODY = 'Suma: 150,50 EUR. VS: 20260042. Protistrana: Client s.r.o. 01.06.2026';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bank_inbound.enabled' => true,
            'bank_inbound.domain' => 'payments.satflux.io',
            'bank_inbound.address_prefix' => 'pay',
            'bank_inbound.reject_forwarded' => true,
            'bank_inbound.webhook_secret' => 'synthetic-native-key',
            'bank_inbound.mailgun_webhook_signing_key' => 'synthetic-mailgun-key',
        ]);
        $this->company = $this->company();
        $this->recipient = app(BankInboundAddressService::class)->buildAddress($this->company);
        Http::fake(fn () => throw new \RuntimeException('Company MIME must never call a provider.'));
    }

    private function company(): Company
    {
        return Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
    }

    private function document(?Company $company = null): BusinessDocument
    {
        return BusinessDocument::create([
            'company_id' => ($company ?? $this->company)->id,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260042',
            'variable_symbol' => '20260042',
            'currency' => 'EUR',
            'total' => 150.50,
            'issue_date' => now(),
        ]);
    }

    private function mime(string $body = self::BODY, string $from = 'notify@tatrabanka.sk', string $type = 'text/plain; charset=utf-8', array $headers = []): string
    {
        // A different header To is intentional: the authenticated envelope
        // recipient retains legacy company routing authority, including BCC.
        return implode("\r\n", array_merge([
            'From: '.$from, 'To: unrelated@example.test', 'Subject: Payment notification',
            'MIME-Version: 1.0', 'Content-Type: '.$type, 'Content-Transfer-Encoding: 8bit',
        ], $headers, ['', $body]));
    }

    private function delivery(string $rawMime, ?string $sender = null): array
    {
        $timestamp = (string) time();
        $token = bin2hex(random_bytes(12));
        $payload = [
            'recipient' => $this->recipient,
            'body-mime' => $rawMime,
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'synthetic-mailgun-key'),
        ];
        if ($sender !== null) {
            $payload['sender'] = $sender;
        }

        return $payload;
    }

    private function job(string $rawMime, ?string $sender = null): ProcessBankNotificationEmail
    {
        $payload = [
            'to' => $this->recipient, 'from' => '', 'subject' => '', 'body' => '',
            '_encrypted_bank_mime' => Crypt::encryptString($rawMime),
        ];
        if ($sender !== null) {
            $payload['_encrypted_bank_envelope_sender'] = Crypt::encryptString($sender);
        }

        return new ProcessBankNotificationEmail($payload);
    }

    #[DataProvider('companyFormats')]
    public function test_one_mime_endpoint_preserves_company_import_and_tenant_scoped_matching(string $from, string $format): void
    {
        $doc = $this->document();
        $other = $this->document($this->company());
        $body = self::BODY;
        $type = 'text/plain; charset=utf-8';
        if ($format === 'html') {
            $type = 'text/html; charset=utf-8';
            $body = '<p>'.self::BODY.'</p><img src="https://never-fetch.invalid/private">';
        } elseif ($format === 'multipart') {
            $type = 'multipart/alternative; boundary=synthetic';
            $body = implode("\r\n", [
                '--synthetic', 'Content-Type: text/plain; charset=windows-1250', '', self::BODY." Pr\xEDjem",
                '--synthetic', 'Content-Type: text/html; charset=utf-8', '', '<p>Suma: 999,00 EUR. VS: 999.</p>', '--synthetic--', '',
            ]);
        }

        $this->post('/api/webhooks/bank-inbound.mime', $this->delivery($this->mime($body, $from, $type)), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('accepted', true);

        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        $this->assertSame(BusinessDocumentStatus::Issued, $other->fresh()->status);
        $this->assertDatabaseCount('bank_transactions', 1);
        $this->assertDatabaseHas('bank_transactions', ['company_id' => $this->company->id, 'variable_symbol' => '20260042']);
        $this->assertDatabaseCount('bank_transaction_matches', 1);
        $this->assertSame(0, AuditLog::where('action', 'sepa.bmail_review_required')->count());
        Http::assertNothingSent();
    }

    public static function companyFormats(): array
    {
        return [
            ['notify@tatrabanka.sk', 'plain'], ['notify@slsp.sk', 'plain'],
            ['notify@tatrabanka.sk', 'multipart'], ['notify@slsp.sk', 'html'],
        ];
    }

    public function test_raw_job_encrypts_original_bytes_and_envelope_sender_without_caller_proof(): void
    {
        Queue::fake();
        $raw = $this->mime(self::BODY." Pr\xEDjem", type: 'text/plain; charset=windows-1250');
        $sender = 'synthetic-private-sender@slsp.sk';
        $this->post('/api/webhooks/bank-inbound.mime', array_merge($this->delivery($raw, $sender), [
            'mode' => 'store', 'company_id' => 'caller-selected', '_verified_bank_domain' => 'tatrabanka.sk',
        ]), ['Accept' => 'application/json'])->assertOk();

        Queue::assertPushed(ProcessBankNotificationEmail::class, function ($job) use ($raw, $sender) {
            $this->assertSame($raw, Crypt::decryptString($job->payload['_encrypted_bank_mime']));
            $this->assertSame($sender, Crypt::decryptString($job->payload['_encrypted_bank_envelope_sender']));
            $this->assertSame('', $job->payload['from']);
            $this->assertSame('', $job->payload['body']);
            $this->assertStringNotContainsString($sender, serialize($job));
            $this->assertStringNotContainsString(self::BODY, serialize($job));
            $this->assertArrayNotHasKey('mode', $job->payload);
            $this->assertArrayNotHasKey('company_id', $job->payload);
            $this->assertArrayNotHasKey('_verified_bank_domain', $job->payload);

            return true;
        });
    }

    public function test_company_prefers_envelope_sender_and_old_jobs_fall_back_to_mime_from_without_proof(): void
    {
        $raw = $this->mime(from: 'neutral@example.test');
        $prepared = app(BankMimeVerificationService::class)->preparePayload($this->job($raw, 'notify@slsp.sk')->payload);
        $this->assertSame($this->company->id, $prepared->owner->id);
        $this->assertSame('notify@slsp.sk', $prepared->payload['from']);
        $this->assertArrayNotHasKey('_verified_bank_domain', $prepared->payload);
        $doc = $this->document();
        app()->call([$this->job($raw, 'notify@slsp.sk'), 'handle']);
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        Http::assertNothingSent();

        $old = app(BankMimeVerificationService::class)->preparePayload($this->job($this->mime())->payload);
        $this->assertSame('notify@tatrabanka.sk', $old->payload['from']);
        $this->assertArrayNotHasKey('_verified_bank_domain', $old->payload);
    }

    #[DataProvider('unusableEnvelopeSenders')]
    public function test_unusable_optional_envelope_sender_is_dropped_and_company_uses_mime_from(mixed $sender): void
    {
        Queue::fake();
        $doc = $this->document();
        $this->post('/api/webhooks/bank-inbound.mime', array_merge($this->delivery($this->mime()), ['sender' => $sender]), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('accepted', true);

        Queue::assertPushed(ProcessBankNotificationEmail::class, function ($job) {
            $this->assertArrayNotHasKey('_encrypted_bank_envelope_sender', $job->payload);
            app()->call([$job, 'handle']);

            return true;
        });
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        $this->assertDatabaseCount('bank_transactions', 1);
        Http::assertNothingSent();
    }

    public static function unusableEnvelopeSenders(): array
    {
        return [
            'array' => [['invalid']], 'integer' => [123], 'boolean' => [true],
            'bytes' => [str_repeat('x', 256)], 'multibyte bytes' => [str_repeat('é', 128)],
            'CRLF' => ["private\r\nsender@slsp.sk"], 'NUL' => ["private\0sender@slsp.sk"],
            'invalid UTF8' => ["private\xE9@slsp.sk"], 'null' => [null], 'empty' => [''],
        ];
    }

    public function test_a_usable_unsupported_envelope_sender_keeps_legacy_priority_over_mime_from(): void
    {
        $doc = $this->document();
        $this->post('/api/webhooks/bank-inbound.mime', $this->delivery($this->mime(), 'notify@example.test'), ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(BusinessDocumentStatus::Issued, $doc->fresh()->status);
        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
    }

    #[TestWith(['X-Forwarded-For: private-forward-marker'])]
    #[TestWith(['Resent-From: private-forward-marker'])]
    #[TestWith(['Fwd: private-forward-marker'])]
    public function test_forwarded_company_mime_keeps_existing_rejection_and_safe_job_errors(string $header): void
    {
        $job = $this->job($this->mime(headers: [$header]));
        $before = $job->payload;
        try {
            app()->call([$job, 'handle']);
            $this->fail('Forwarded company MIME must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Bank MIME processing is temporarily unavailable.', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertSame($before, $job->payload);
        $this->assertStringNotContainsString('private-forward-marker', serialize($job));
        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_forward_rejection_can_still_be_disabled_for_existing_company_policy(): void
    {
        config(['bank_inbound.reject_forwarded' => false]);
        app()->call([$this->job($this->mime(headers: ['Resent-From: private-forward-marker'])), 'handle']);
        $this->assertDatabaseCount('bank_transactions', 1);
        Http::assertNothingSent();
    }

    public function test_parsed_then_mime_and_retried_mime_do_not_duplicate_company_transactions(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $doc = $this->document();
        // This transport regression needs a bank-supplied booking time.
        // Compact messages retain their existing processing-time defaults.
        $body = "01.06.2026 12:31 bol zostatok Vasho uctu SK3112000000198742637541 zvyseny o 150,50 EUR.\r\nReferencia platitela: /VS20260042/SS/KS";
        $this->postJson('/api/webhooks/bank-inbound', [
            'to' => $this->recipient, 'from' => 'notify@tatrabanka.sk', 'subject' => 'Payment notification', 'body' => $body,
        ], ['X-Bank-Inbound-Secret' => 'synthetic-native-key'])->assertOk();
        $job = $this->job($this->mime($body));
        $this->travel(2)->seconds();
        app()->call([$job, 'handle']);
        $this->travel(3)->seconds();
        app()->call([$job, 'handle']);

        $this->assertSame(BusinessDocumentStatus::Paid, $doc->fresh()->status);
        $this->assertDatabaseCount('bank_transactions', 1);
        $this->assertDatabaseCount('bank_transaction_matches', 1);
        Http::assertNothingSent();
    }

    public function test_same_day_compact_notifications_keep_separate_legacy_processing_times(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        app()->call([$this->job($this->mime()), 'handle']);
        $this->travel(2)->seconds();
        app()->call([$this->job($this->mime()), 'handle']);

        // No bank event ID is available to distinguish a retry from another
        // identical compact credit. Preserve the existing import policy.
        $this->assertDatabaseCount('bank_transactions', 2);
        $bookedAt = BankTransaction::where('company_id', $this->company->id)->pluck('booked_at');
        $this->assertCount(2, $bookedAt->unique());
        Http::assertNothingSent();
    }

    public function test_no_rows_company_mime_logs_no_decoded_metadata(): void
    {
        Log::spy();
        app()->call([$this->job($this->mime('synthetic-private-body', 'synthetic-private-sender@example.test')), 'handle']);
        Log::shouldHaveReceived('warning')->with('Bank inbound: no transactions parsed', ['company_id' => $this->company->id])->once();
        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_a_recipient_reassigned_during_extraction_cannot_import_into_another_company(): void
    {
        $other = $this->company();
        $raw = $this->mime();
        $mock = Mockery::mock(BankMimeVerificationService::class, [app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('extractCompany')->once()->andReturnUsing(function () use ($raw, $other) {
            $token = $this->company->bank_inbound_token;
            $this->company->update(['bank_inbound_token' => null]);
            $other->update(['bank_inbound_token' => $token]);

            return ['status' => 'extracted', 'from' => 'notify@tatrabanka.sk', 'subject' => 'Payment',
                'body' => self::BODY, 'headers' => '', 'contentSha256' => hash('sha256', $raw)];
        });
        $this->app->instance(BankMimeVerificationService::class, $mock);
        $job = $this->job($raw);
        $before = $job->payload;
        try {
            app()->call([$job, 'handle']);
            $this->fail('A changed recipient owner must fail closed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Bank MIME processing is temporarily unavailable.', $e->getMessage());
        }
        $this->assertSame($before, $job->payload);
        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_child_proof_markers_are_discarded_and_invalid_raw_digest_cannot_import(): void
    {
        $raw = $this->mime();
        $mock = Mockery::mock(BankMimeVerificationService::class, [app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $result = ['status' => 'extracted', 'from' => 'notify@tatrabanka.sk', 'subject' => 'Payment',
            'body' => self::BODY, 'headers' => '', 'contentSha256' => hash('sha256', $raw),
            'bankDomain' => 'tatrabanka.sk', '_verified_bank_domain' => 'tatrabanka.sk'];
        $mock->shouldReceive('extractCompany')->twice()->andReturn($result, array_merge($result, ['contentSha256' => str_repeat('0', 64)]));
        $prepared = $mock->preparePayload($this->job($raw)->payload);
        $this->assertArrayNotHasKey('_verified_bank_domain', $prepared->payload);
        $this->assertArrayNotHasKey('bankDomain', $prepared->payload);
        $this->assertNull($mock->preparePayload($this->job($raw)->payload));
        $this->assertDatabaseCount('bank_transactions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bank_inbound.mime_rejected']);
        Http::assertNothingSent();
    }

    public function test_company_subprocess_failure_keeps_ciphertext_and_sanitizes_failed_hook_and_exception(): void
    {
        Log::spy();
        $marker = 'synthetic-private-process-error';
        $mock = Mockery::mock(BankMimeVerificationService::class, [app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('extractCompany')->once()->andThrow(new \RuntimeException($marker));
        $this->app->instance(BankMimeVerificationService::class, $mock);
        $job = $this->job($this->mime($marker), $marker.'@slsp.sk');
        $before = $job->payload;
        try {
            app()->call([$job, 'handle']);
            $this->fail('Subprocess failure must retry.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString($marker, $e->getMessage());
            $this->assertNull($e->getPrevious());
            $job->failed($e);
        }
        $this->assertSame($before, $job->payload);
        $this->assertStringNotContainsString($marker, serialize($job));
        Log::shouldHaveReceived('error')->with('Bank inbound email processing failed', ['exception_type' => \RuntimeException::class])->once();
        Log::shouldHaveReceived('error')->with('Bank MIME processing failed permanently', ['exception_type' => \RuntimeException::class])->once();
        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
    }
}
