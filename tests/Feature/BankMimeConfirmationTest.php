<?php

namespace Tests\Feature;

use App\Jobs\ProcessBankNotificationEmail;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\BankInboundEmailService;
use App\Services\Invoicing\BankMimeVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BankMimeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private string $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bank_inbound.enabled' => true,
            'bank_inbound.domain' => 'payments.satflux.io',
            'bank_inbound.mailgun_webhook_signing_key' => 'synthetic-mailgun-key',
            'services.btcpay.base_url' => 'https://btcpay.test',
        ]);
        $this->store = Store::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->recipient = app(BankInboundAddressService::class)->buildStoreAddress($this->store);
        Http::preventStrayRequests();
    }

    private function delivery(string $rawMime): array
    {
        $timestamp = (string) time();
        $token = bin2hex(random_bytes(12));

        return [
            'recipient' => $this->recipient,
            'body-mime' => $rawMime,
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'synthetic-mailgun-key'),
        ];
    }

    public function test_original_mime_is_not_trimmed_and_only_ciphertext_is_queued(): void
    {
        Queue::fake();
        $rawMime = "From: b-mail@tatrabanka.sk\r\n\r\nSynthetic message\r\n\r\n";
        $this->post('/api/webhooks/bank-inbound.mime', $this->delivery($rawMime), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('accepted', true);
        Queue::assertPushed(ProcessBankNotificationEmail::class, function ($job) use ($rawMime) {
            $this->assertSame($rawMime, Crypt::decryptString($job->payload['_encrypted_bank_mime']));
            $this->assertSame('', $job->payload['body']);
            $this->assertSame('', $job->payload['from']);
            $this->assertStringNotContainsString($rawMime, serialize($job));

            return true;
        });
    }

    public function test_mime_route_rejects_unsigned_or_parsed_only_deliveries(): void
    {
        Queue::fake();
        $this->postJson('/api/webhooks/bank-inbound.mime', ['recipient' => $this->recipient, 'body-mime' => 'message'])
            ->assertForbidden();
        $parsed = $this->delivery('message');
        unset($parsed['body-mime']);
        $parsed['body-plain'] = 'message';
        $this->postJson('/api/webhooks/bank-inbound.mime', $parsed)->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_mime_route_rejects_oversized_messages_before_queueing(): void
    {
        Queue::fake();
        $this->postJson('/api/webhooks/bank-inbound.mime', $this->delivery(str_repeat('a', 262145)))
            ->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_invalid_bank_mime_is_review_only_without_provider_calls(): void
    {
        $this->stubVerification(['status' => 'invalid', 'reason' => 'signature']);
        $this->postJson('/api/webhooks/bank-inbound.mime', $this->delivery('Synthetic unsigned bank MIME'))
            ->assertOk();
        Http::assertNothingSent();
        $this->assertSame('unverified_bank_mime', AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata['reason']);
    }

    public function test_store_mime_never_falls_back_to_company_extraction_or_envelope_sender_proof(): void
    {
        $mock = Mockery::mock(BankMimeVerificationService::class, [
            app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('verify')->once()->andReturn(['status' => 'invalid']);
        $mock->shouldNotReceive('extractCompany');
        $this->app->instance(BankMimeVerificationService::class, $mock);

        $this->postJson('/api/webhooks/bank-inbound.mime', array_merge($this->delivery('Unsigned company-compatible body'), [
            'sender' => 'bank@tatrabanka.sk', 'mode' => 'company', '_verified_bank_domain' => 'tatrabanka.sk',
        ]))->assertOk();

        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
        $this->assertSame('unverified_bank_mime', AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata['reason']);
    }

    public function test_invalid_utf8_is_review_only_without_starting_the_verifier(): void
    {
        $mock = Mockery::mock(BankMimeVerificationService::class, [
            app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldNotReceive('verify');
        $this->app->instance(BankMimeVerificationService::class, $mock);

        $rawMime = "From: b-mail@tatrabanka.sk\r\n\r\nUnsupported encoding: \xE9\r\n";
        $this->assertFalse(mb_check_encoding($rawMime, 'UTF-8'));
        $this->post('/api/webhooks/bank-inbound.mime', $this->delivery($rawMime), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('accepted', true);

        Http::assertNothingSent();
        $this->assertSame('unverified_bank_mime', AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata['reason']);
    }

    #[TestWith(['retryable'])]
    #[TestWith(['broken-process'])]
    public function test_transient_verification_failure_keeps_ciphertext_for_queue_retry(string $status): void
    {
        $this->stubVerification(['status' => $status]);
        $payload = [
            'to' => $this->recipient, 'from' => '', 'subject' => '', 'body' => '',
            '_encrypted_bank_mime' => Crypt::encryptString('Synthetic signed MIME'),
        ];
        $job = new ProcessBankNotificationEmail($payload);
        try {
            app()->call([$job, 'handle']);
            $this->fail('Transient or malformed verifier results must retry.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('temporarily unavailable', $e->getMessage());
        }
        $this->assertSame($payload, $job->payload);
        Http::assertNothingSent();
    }

    private function stubVerification(array $result): void
    {
        $mock = Mockery::mock(BankMimeVerificationService::class, [
            app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('verify')->once()->andReturn($result);
        $this->app->instance(BankMimeVerificationService::class, $mock);
    }

    public function test_downstream_failure_cannot_persist_decrypted_message_in_exception_or_job(): void
    {
        $privateMarker = 'synthetic-private-bank-body-marker';
        $rawMime = 'Synthetic signed MIME '.$privateMarker;
        $this->stubVerification([
            'status' => 'verified', 'bankDomain' => 'tatrabanka.sk',
            'from' => 'b-mail@tatrabanka.sk', 'subject' => 'Kredit', 'body' => $privateMarker,
            'contentSha256' => hash('sha256', $rawMime),
        ]);
        $this->mock(BankInboundEmailService::class)->shouldReceive('handle')->once()
            ->andThrow(new \RuntimeException($privateMarker));
        $job = new ProcessBankNotificationEmail([
            'to' => $this->recipient, 'from' => '', 'subject' => '', 'body' => '',
            '_encrypted_bank_mime' => Crypt::encryptString($rawMime),
        ]);
        try {
            app()->call([$job, 'handle']);
            $this->fail('A downstream failure must preserve queue retry.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString($privateMarker, $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertStringNotContainsString($privateMarker, serialize($job));
        $this->assertSame($rawMime, Crypt::decryptString($job->payload['_encrypted_bank_mime']));
    }

    public function test_signed_mailgun_mime_reaches_the_actual_parser_and_report_contract(): void
    {
        $body = $this->classicBody();
        $this->withSignedFixture($body, function (string $rawMime) {
            Http::fake([
                '*/plugins/sepa-instant-qr/settings' => Http::response(['confirmationContractVersion' => 2]),
                '*/plugins/sepa-instant-qr/payment-requests/report' => Http::response(['outcome' => 'settled']),
            ]);
            $this->postJson('/api/webhooks/bank-inbound.mime', $this->delivery($rawMime))->assertOk();
            Http::assertSentCount(2);
            Http::assertSent(fn ($request) => $request->method() === 'POST'
                && $request['reference'] === 'QR-ab29e346f1d841c8a95a63d857490818'
                && $request['creditorIban'] === 'SK4811000000002944116480'
                && $request['bankOriginVerified'] === true
                && $request['amount'] === 12.5
                && strlen($request['dedupKey']) === 64);
            $this->assertSame('settled', AuditLog::where('action', 'sepa.bmail_processed')->sole()->metadata['outcome']);
        });
    }

    public function test_unusable_optional_sender_never_blocks_valid_store_bank_proof(): void
    {
        $this->withSignedFixture($this->classicBody(), function (string $rawMime) {
            Http::fake([
                '*/plugins/sepa-instant-qr/settings' => Http::response(['confirmationContractVersion' => 2]),
                '*/plugins/sepa-instant-qr/payment-requests/report' => Http::response(['outcome' => 'settled']),
            ]);
            $processed = 0;
            foreach (BankCompanyMimeImportTest::unusableEnvelopeSenders() as [$sender]) {
                $this->post('/api/webhooks/bank-inbound.mime', array_merge($this->delivery($rawMime), ['sender' => $sender]), ['Accept' => 'application/json'])
                    ->assertOk()->assertJsonPath('accepted', true);
                Http::assertSentCount(++$processed * 2);
            }
            $this->assertSame($processed, AuditLog::where('action', 'sepa.bmail_processed')->where('target_id', $this->store->id)->count());
            $this->assertSame(0, AuditLog::where('action', 'sepa.bmail_review_required')->count());
        });
    }

    public function test_valid_mailgun_delivery_cannot_hide_a_tampered_bank_body(): void
    {
        $this->withSignedFixture($this->classicBody(), function (string $rawMime) {
            $tampered = str_replace('12,50', '99,99', $rawMime);
            $this->assertNotSame($rawMime, $tampered);
            $this->postJson('/api/webhooks/bank-inbound.mime', $this->delivery($tampered))->assertOk();
            Http::assertNothingSent();
            $this->assertSame('unverified_bank_mime', AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata['reason']);
        });
    }

    public function test_signed_bank_message_cannot_be_retargeted_using_another_mailgun_recipient(): void
    {
        $this->withSignedFixture($this->classicBody(), function (string $rawMime) {
            $otherStore = Store::factory()->create(['user_id' => $this->store->user_id]);
            $this->recipient = app(BankInboundAddressService::class)->buildStoreAddress($otherStore);
            $this->postJson('/api/webhooks/bank-inbound.mime', $this->delivery($rawMime))->assertOk();
            Http::assertNothingSent();
            $this->assertSame($otherStore->id, AuditLog::where('action', 'sepa.bmail_review_required')->sole()->target_id);
        });
    }

    /** Synthetic QR adaptation of a publicly witnessed historical template. */
    private function classicBody(): string
    {
        return "Vazeny klient,\r\n"
            ."01.06.2026 12:31 bol zostatok Vasho uctu SK4811000000002944116480 zvyseny o 12,50 EUR.\r\n"
            ."uctovny zostatok: 1 012,50 EUR\r\naktualny zostatok: 1 012,50 EUR\r\n"
            ."disponibilny zostatok: 1 012,50 EUR\r\nPopis transakcie: CCINT 1100/000000-261426464\r\n"
            ."Referencia platitela: QR-ab29e346f1d841c8a95a63d857490818\r\n"
            ."Informacia pre prijemcu: Invoice payment\r\nS pozdravom\r\nTATRA BANKA, a.s.\r\n";
    }

    /** Real production CLI and crypto; only DNS is replaced by a test preload. */
    private function withSignedFixture(string $body, callable $callback): void
    {
        $directory = sys_get_temp_dir().'/satflux-bank-mime-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $producer = new Process(['node', base_path('runtime/bank-mail-verifier/test/build-message.mjs')], base_path(), null,
                json_encode(['recipient' => $this->recipient, 'body' => $body], JSON_THROW_ON_ERROR), 15);
            $producer->mustRun();
            $fixture = json_decode($producer->getOutput(), true, 16, JSON_THROW_ON_ERROR);
            file_put_contents($directory.'/dns.json', json_encode([
                'dnsName' => $fixture['dnsName'], 'dnsRecord' => $fixture['dnsRecord'],
            ], JSON_THROW_ON_ERROR));
            file_put_contents($directory.'/node', '#!/bin/sh'."\nexport SATFLUX_TEST_DNS_FIXTURE="
                .escapeshellarg($directory.'/dns.json')."\nexec node --import "
                .escapeshellarg(base_path('runtime/bank-mail-verifier/test/preload-dns.mjs')).' "$@"'."\n");
            chmod($directory.'/node', 0700);
            config(['bank_inbound.node_binary' => $directory.'/node']);
            $preflight = new Process([$directory.'/node', base_path('runtime/bank-mail-verifier/cli.mjs')], base_path(), null,
                json_encode(['rawMime' => $fixture['rawMime'], 'recipient' => $this->recipient], JSON_THROW_ON_ERROR), 15);
            $this->assertSame(0, $preflight->run(), $preflight->getErrorOutput());
            $this->assertSame('verified', json_decode($preflight->getOutput(), true)['status']);
            $callback($fixture['rawMime']);
        } finally {
            foreach (['dns.json', 'node'] as $file) {
                if (is_file($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }
}
