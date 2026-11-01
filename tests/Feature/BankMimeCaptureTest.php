<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\BankMimeCaptureService;
use App\Services\Invoicing\BankMimeVerificationService;
use App\Support\Invoicing\BankMimeDiagnosticRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tests\Unit\BankMimeDiagnosticSummaryTest;

class BankMimeCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private string $recipient;

    private BankMimeCaptureService $capture;

    private bool $redisReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('BANK_MAIL_CAPTURE_TEST_REDIS') !== '1') {
            $this->markTestSkipped('Requires an explicitly configured disposable Redis fixture.');
        }
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('127.0.0.1', config('database.redis.cache.host'));
        $this->assertSame('1', (string) config('database.redis.cache.database'));
        $this->assertStringStartsWith('bank_mime_capture_', config('database.redis.options.prefix'));
        config(['database.redis.client' => 'phpredis']);
        $this->app->forgetInstance('redis');
        Redis::clearResolvedInstance('redis');
        $this->assertTrue((bool) Redis::connection('cache')->ping());
        $this->redisReady = true;
        Redis::connection('cache')->del(BankMimeCaptureService::KEY);
        config(['bank_mail_capture.enabled' => true, 'bank_mail_capture.redis_connection' => 'cache', 'bank_inbound.enabled' => true]);
        $this->store = Store::factory()->create();
        $this->recipient = app(BankInboundAddressService::class)->buildStoreAddress($this->store);
        $this->capture = app(BankMimeCaptureService::class);
        Queue::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if ($this->redisReady) {
            Redis::connection('cache')->del(BankMimeCaptureService::KEY);
            Redis::purge('cache');
        }
        parent::tearDown();
    }

    private function summary(): array
    {
        return BankMimeDiagnosticSummaryTest::summary();
    }

    private function ciphertext(): string
    {
        return Redis::connection('cache')->get(BankMimeCaptureService::KEY);
    }

    private function slot(): array
    {
        return json_decode(Crypt::decryptString($this->ciphertext()), true, 16, JSON_THROW_ON_ERROR);
    }

    private function expiry(): int
    {
        return (int) Redis::connection('cache')->eval("return redis.call('PEXPIRETIME', KEYS[1])", 1, BankMimeCaptureService::KEY);
    }

    private function replace(array $slot, ?int $expiry = null): void
    {
        Redis::connection('cache')->eval("return redis.call('SET', KEYS[1], ARGV[1], 'PXAT', ARGV[2])", 1,
            BankMimeCaptureService::KEY, Crypt::encryptString(json_encode($slot, JSON_THROW_ON_ERROR)), $expiry ?? $slot['expires_at']);
    }

    private function captureOne(string $raw = 'Synthetic private MIME'): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        $this->assertIsString($token);
        $this->assertSame('captured', $this->capture->capture($this->store, $token, $raw, $this->recipient, $this->summary()));
    }

    public function test_default_is_off_and_disabled_capture_cannot_create_a_slot(): void
    {
        $config = require base_path('config/bank_mail_capture.php');
        $this->assertFalse($config['enabled']);
        $this->assertSame('cache', $config['redis_connection']);
        config(['bank_mail_capture.enabled' => false]);
        $this->assertSame('disabled', $this->capture->arm($this->store, 120));
        $this->assertNull($this->capture->armedFor($this->store));
        $this->assertSame('disabled', $this->capture->capture($this->store, 'missing', 'MIME', $this->recipient, $this->summary()));
        $this->assertSame('empty', $this->capture->status()['status']);
        $this->assertSame(0, Redis::connection('cache')->exists(BankMimeCaptureService::KEY));
    }

    public function test_only_one_existing_store_can_be_armed_and_generation_is_random(): void
    {
        $this->assertSame('invalid_store', $this->capture->arm(new Store, 120));
        $this->assertSame('invalid_window', $this->capture->arm($this->store, 121));
        $this->assertSame('invalid_window', $this->capture->arm($this->store, 0));
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $first = $this->ciphertext();
        $slot = $this->slot();
        $this->assertSame($slot['expires_at'], $this->expiry());
        $this->assertSame(7200000, $slot['expires_at'] - $slot['armed_at']);
        $this->assertSame('occupied', $this->capture->arm(Store::factory()->create(), 120));
        $this->assertSame($first, $this->ciphertext());
        Redis::connection('cache')->del(BankMimeCaptureService::KEY);
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $this->assertNotSame($slot['generation'], $this->slot()['generation']);
    }

    public function test_arm_uses_redis_time_even_when_php_clock_is_a_day_ahead(): void
    {
        $this->travel(1)->days();
        try {
            $this->assertSame('armed', $this->capture->arm($this->store, 120));
            $slot = $this->slot();
            $time = Redis::connection('cache')->time();
            $now = (int) $time[0] * 1000 + intdiv((int) $time[1], 1000);
            $this->assertLessThanOrEqual(7200000, $slot['expires_at'] - $now);
            $this->assertSame('armed', $this->capture->status()['status']);
        } finally {
            $this->travelBack();
        }
    }

    public function test_capture_preserves_exact_expiry_and_encrypts_the_entire_bound_envelope(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        $original = $this->slot();
        $expiry = $this->expiry();
        $raw = "Synthetic private MIME\r\n\r\n";
        $this->assertSame('captured', $this->capture->capture($this->store, $token, $raw, $this->recipient, $this->summary()));
        $ciphertext = $this->ciphertext();
        foreach ([$raw, $this->recipient, $this->store->id, $original['generation']] as $private) {
            $this->assertStringNotContainsString($private, $ciphertext);
        }
        $captured = $this->slot();
        $this->assertSame($original['generation'], $captured['generation']);
        $this->assertSame($original['store_id'], $captured['store_id']);
        $this->assertSame($original['owner_user_id'], $captured['owner_user_id']);
        $this->assertSame($raw, base64_decode($captured['raw_mime_base64'], true));
        $this->assertSame(hash('sha256', $raw), $captured['content_sha256']);
        $this->assertSame($this->summary(), $captured['original_summary']);
        $this->assertSame($expiry, $this->expiry());
        $this->assertLessThanOrEqual(1048576, strlen($ciphertext));
        $this->assertSame('not_armed', $this->capture->capture($this->store, $token, 'Second MIME', $this->recipient, $this->summary()));
        $this->assertSame($ciphertext, $this->ciphertext());
    }

    public function test_eviction_never_recreates_a_key_and_old_generation_cannot_capture_a_new_activation(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        Redis::connection('cache')->del(BankMimeCaptureService::KEY);
        $this->assertSame('not_armed', $this->capture->capture($this->store, $token, 'MIME', $this->recipient, $this->summary()));
        $this->assertSame(0, Redis::connection('cache')->exists(BankMimeCaptureService::KEY));
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $new = $this->ciphertext();
        $this->assertSame('not_armed', $this->capture->capture($this->store, $token, 'MIME', $this->recipient, $this->summary()));
        $this->assertSame($new, $this->ciphertext());
    }

    public function test_disabled_writing_and_new_service_instances_never_extend_expiry(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        $expiry = $this->expiry();
        config(['bank_mail_capture.enabled' => false]);
        $this->assertSame('disabled', $this->capture->capture($this->store, $token, 'MIME', $this->recipient, $this->summary()));
        $this->assertNull($this->capture->armedFor($this->store));
        $this->assertSame('armed', app(BankMimeCaptureService::class)->status()['status']);
        config(['bank_mail_capture.enabled' => true]);
        $this->assertSame($token, $this->capture->armedFor($this->store));
        $this->assertSame($expiry, $this->expiry());
    }

    public function test_selection_is_bound_to_both_local_store_and_current_user_owner(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        $other = Store::factory()->create();
        $otherRecipient = app(BankInboundAddressService::class)->buildStoreAddress($other);
        $this->assertNull($this->capture->armedFor($other));
        $this->assertSame('not_armed', $this->capture->capture($other, $token, 'MIME', $otherRecipient, $this->summary()));
        $this->assertSame('owner_mismatch', $this->capture->capture($this->store, $token, 'MIME', $otherRecipient, $this->summary()));
        $this->store->update(['user_id' => $other->user_id]);
        $this->assertNull($this->capture->armedFor($this->store));
    }

    #[TestWith(['hash'])]
    #[TestWith(['deadline'])]
    #[TestWith(['summary'])]
    #[TestWith(['recipient'])]
    #[TestWith(['extra'])]
    public function test_invalid_authenticated_records_are_not_analyzed(string $mutation): void
    {
        $this->captureOne();
        $slot = $this->slot();
        $expiry = $this->expiry();
        match ($mutation) {
            'hash' => $slot['content_sha256'] = str_repeat('0', 64),
            'deadline' => $slot['expires_at'] = $expiry + 1,
            'summary' => $slot['original_summary']['signatures'][0]['bank_signer'] = 'true',
            'recipient' => $slot['recipient'] = 'private@unknown.test',
            'extra' => $slot['private'] = 'private',
        };
        $this->replace($slot, $expiry);
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldNotReceive('run');
        $this->assertSame('invalid_record', app(BankMimeCaptureService::class)->status()['status']);
        $this->assertSame(['version' => 1, 'status' => 'invalid_record'], app(BankMimeCaptureService::class)->diagnose());
    }

    public function test_bad_ciphertext_and_missing_expiry_fail_closed(): void
    {
        Redis::connection('cache')->set(BankMimeCaptureService::KEY, 'synthetic-private-corrupt-ciphertext');
        $this->assertSame('invalid_record', $this->capture->status()['status']);
        $this->assertNull($this->capture->armedFor($this->store));
        Redis::connection('cache')->del(BankMimeCaptureService::KEY);
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        Redis::connection('cache')->persist(BankMimeCaptureService::KEY);
        $this->assertSame('invalid_record', $this->capture->status()['status']);
    }

    public function test_restored_expired_envelope_with_new_ttl_is_rejected(): void
    {
        $this->captureOne();
        $slot = $this->slot();
        $futurePhysicalExpiry = $this->expiry();
        $slot['armed_at'] -= 8000000;
        $slot['expires_at'] -= 8000000;
        $slot['captured_at'] -= 8000000;
        $this->replace($slot, $futurePhysicalExpiry);
        $this->assertSame('invalid_record', $this->capture->diagnose()['status']);
    }

    public function test_naturally_expired_slot_is_never_recreated_by_a_delivery(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $slot = $this->slot();
        $time = Redis::connection('cache')->time();
        $slot['expires_at'] = (int) $time[0] * 1000 + intdiv((int) $time[1], 1000) + 30;
        $this->replace($slot);
        $token = $this->capture->armedFor($this->store);
        $this->assertIsString($token);
        usleep(50000);
        $this->assertSame('not_armed', $this->capture->capture($this->store, $token, 'MIME', $this->recipient, $this->summary()));
        $this->assertSame('empty', $this->capture->status()['status']);
        $this->assertSame(0, Redis::connection('cache')->exists(BankMimeCaptureService::KEY));
    }

    public function test_plaintext_limit_and_complete_encrypted_limit_preserve_the_armed_slot(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        $this->assertSame('oversized', $this->capture->capture($this->store, $token, str_repeat('a', 262145), $this->recipient, $this->summary()));
        $this->assertSame($token, $this->ciphertext());
        $encrypter = Crypt::getFacadeRoot();
        Crypt::shouldReceive('decryptString')->andReturnUsing(fn (string $value) => $encrypter->decryptString($value));
        Crypt::shouldReceive('encryptString')->once()->andReturn(str_repeat('x', 1048577));
        $this->assertSame('oversized', $this->capture->capture($this->store, $token, 'MIME', $this->recipient, $this->summary()));
        $this->assertSame($token, $this->ciphertext());
    }

    public function test_malformed_or_ineligible_summary_never_captures(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        foreach ([['private' => 'private'], array_replace($this->summary(), ['reason' => 'unsupported_mime'])] as $summary) {
            $this->assertSame('invalid_summary', $this->capture->capture($this->store, $token, 'MIME', $this->recipient, $summary));
            $this->assertSame($token, $this->ciphertext());
        }
    }

    public function test_operator_commands_require_acknowledgement_and_print_only_fixed_json(): void
    {
        $this->artisan('bank-mail:capture-arm', ['store' => $this->store->id])
            ->expectsOutput('{"version":1,"status":"acknowledgement_required"}')->assertFailed();
        $this->artisan('bank-mail:capture-arm', ['store' => 'synthetic-private-not-uuid', '--acknowledge-backup-retention' => true])
            ->expectsOutput('{"version":1,"status":"invalid_store"}')->assertFailed();
        $this->artisan('bank-mail:capture-arm', ['store' => $this->store->id, '--minutes' => '121', '--acknowledge-backup-retention' => true])
            ->expectsOutput('{"version":1,"status":"invalid_window"}')->assertFailed();
        $this->artisan('bank-mail:capture-arm', ['store' => $this->store->id, '--acknowledge-backup-retention' => true])
            ->expectsOutput('{"version":1,"status":"armed"}')->assertSuccessful();
        $this->artisan('bank-mail:capture-status')
            ->expectsOutput('{"version":1,"status":"armed","writing_enabled":true}')->assertSuccessful();
    }

    public function test_diagnose_is_read_only_while_disabled_and_labels_original_and_current(): void
    {
        $raw = 'synthetic-private-original-body';
        $this->captureOne($raw);
        $expiry = $this->expiry();
        $ciphertext = $this->ciphertext();
        $auditCount = AuditLog::count();
        config(['bank_mail_capture.enabled' => false]);
        $summary = array_replace($this->summary(), ['status' => 'retryable', 'reason' => 'dns_unavailable']);
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldReceive('run')->once()->with($raw, $this->recipient)->andReturn([
            'verification' => ['status' => 'retryable', 'reason' => 'dns_unavailable', 'body' => 'synthetic-private-child-output'],
            'diagnostics' => $summary, 'contentSha256' => hash('sha256', $raw),
        ]);
        $this->assertSame(0, Artisan::call('bank-mail:capture-diagnose'));
        $output = Artisan::output();
        $this->assertSame([
            'version' => 1, 'status' => 'diagnosed', 'original' => $this->summary(), 'recheck' => $summary,
        ], json_decode(trim($output), true, 16, JSON_THROW_ON_ERROR));
        foreach ([$raw, $this->recipient, $this->store->id, 'synthetic-private-child-output'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        $this->assertSame($ciphertext, $this->ciphertext());
        $this->assertSame($expiry, $this->expiry());
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertDatabaseCount('bank_transactions', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_diagnose_preserves_original_on_sanitized_recheck_failure(): void
    {
        $this->captureOne();
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldReceive('run')->once()
            ->andThrow(new \RuntimeException('synthetic-private-child-exception'));
        $result = app(BankMimeCaptureService::class)->diagnose();
        $this->assertSame(['version' => 1, 'status' => 'recheck_unavailable', 'original' => $this->summary()], $result);
    }

    #[TestWith(['nonzero'])]
    #[TestWith(['malformed'])]
    #[TestWith(['stderr_limit'])]
    public function test_real_child_failures_never_print_raw_output_or_exceptions(string $mode): void
    {
        $private = 'synthetic-private-child-marker';
        $this->captureOne($private);
        $script = tempnam(sys_get_temp_dir(), 'bank-diagnostic-child-');
        $code = match ($mode) {
            'nonzero' => 'fwrite(STDERR, "synthetic-private-child-marker"); exit(7);',
            'malformed' => 'echo "synthetic-private-child-marker";',
            'stderr_limit' => 'fwrite(STDERR, str_repeat("synthetic-private-child-marker", 20000));',
        };
        try {
            file_put_contents($script, "#!/bin/sh\nexec php -r ".escapeshellarg($code)."\n");
            chmod($script, 0700);
            config(['bank_inbound.node_binary' => $script]);
            $this->assertSame(1, Artisan::call('bank-mail:capture-diagnose'));
            $output = Artisan::output();
            $this->assertSame([
                'version' => 1, 'status' => 'recheck_unavailable', 'original' => $this->summary(),
            ], json_decode(trim($output), true, 16, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString($private, $output);
            $this->assertStringNotContainsString($this->recipient, $output);
            Http::assertNothingSent();
            Queue::assertNothingPushed();
        } finally {
            unlink($script);
        }
    }

    public function test_expiry_during_recheck_suppresses_original_and_current_summary(): void
    {
        $this->captureOne();
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldReceive('run')->once()
            ->andReturnUsing(function () {
                Redis::connection('cache')->del(BankMimeCaptureService::KEY);

                return ['diagnostics' => $this->summary()];
            });
        $this->assertSame(['version' => 1, 'status' => 'empty'], app(BankMimeCaptureService::class)->diagnose());
    }

    public function test_real_redis_compare_and_swap_has_exactly_one_concurrent_winner(): void
    {
        $this->assertTrue(function_exists('pcntl_fork'), 'Disposable Redis concurrency fixture requires pcntl.');
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $token = $this->capture->armedFor($this->store);
        $expiry = $this->expiry();
        $statuses = $this->race(fn (int $index) => $this->capture->capture($this->store, $token, 'MIME '.$index, $this->recipient, $this->summary()));
        sort($statuses);
        $this->assertSame(['captured', 'not_armed'], $statuses);
        $this->assertSame($expiry, $this->expiry());
        $this->assertContains(base64_decode($this->slot()['raw_mime_base64'], true), ['MIME 0', 'MIME 1']);
    }

    public function test_real_redis_arming_has_exactly_one_concurrent_winner(): void
    {
        $this->assertTrue(function_exists('pcntl_fork'));
        $statuses = $this->race(fn () => $this->capture->arm($this->store, 120));
        sort($statuses);
        $this->assertSame(['armed', 'occupied'], $statuses);
    }

    /** Forked processes share the synthetic in-memory DB snapshot, never a production DB. */
    private function race(callable $operation): array
    {
        $children = [];
        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                fclose($sockets[0]);
                Redis::purge('cache');
                fwrite($sockets[1], $operation($index));
                fclose($sockets[1]);
                exit(0);
            }
            fclose($sockets[1]);
            $children[] = [$pid, $sockets[0]];
        }
        $results = [];
        foreach ($children as [$pid, $socket]) {
            $results[] = stream_get_contents($socket);
            fclose($socket);
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        return $results;
    }

    private function strictVerifier(array $result): BankMimeVerificationService
    {
        $service = Mockery::mock(BankMimeVerificationService::class, [app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('verify')->once()->andReturn($result);

        return $service;
    }

    private function payload(string $raw = 'Synthetic MIME'): array
    {
        return ['to' => $this->recipient, '_encrypted_bank_mime' => Crypt::encryptString($raw)];
    }

    public function test_disabled_or_unarmed_path_uses_only_the_existing_strict_verifier(): void
    {
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldNotReceive('run');
        $this->assertNull($this->strictVerifier(['status' => 'invalid', 'reason' => 'invalid_signature'])->verifiedPayload($this->payload()));
        config(['bank_mail_capture.enabled' => false]);
        $this->assertNull($this->strictVerifier(['status' => 'invalid', 'reason' => 'invalid_signature'])->verifiedPayload($this->payload()));
        $this->assertSame('empty', $this->capture->status()['status']);
    }

    public function test_armed_verification_uses_one_diagnostic_result_and_keeps_review_unchanged(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldReceive('run')->once()->andReturn([
            'verification' => ['status' => 'invalid', 'reason' => 'invalid_signature'],
            'diagnostics' => $this->summary(), 'contentSha256' => hash('sha256', 'Synthetic MIME'),
        ]);
        $service = Mockery::mock(BankMimeVerificationService::class, [app(BankInboundAddressService::class), app(BankEmailOriginVerifier::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldNotReceive('verify');
        $this->assertNull($service->verifiedPayload($this->payload()));
        $this->assertSame('captured', $this->capture->status()['status']);
        $this->assertSame([
            'reason' => 'unverified_bank_mime', 'verifier_reason' => 'invalid_signature',
        ], AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata);
        Http::assertNothingSent();
    }

    public function test_diagnostic_failure_falls_back_to_strict_verification_and_keeps_retry(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldReceive('run')->once()
            ->andThrow(new \RuntimeException('synthetic-private-child-output'));
        $service = $this->strictVerifier(['status' => 'retryable', 'reason' => 'dns_unavailable']);
        try {
            $service->verifiedPayload($this->payload());
            $this->fail('Strict retry must be preserved.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Bank message verification is temporarily unavailable.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame('armed', $this->capture->status()['status']);
        $this->assertSame(0, AuditLog::where('action', 'sepa.bmail_review_required')->count());
        Http::assertNothingSent();
    }

    public function test_capture_only_cache_failure_cannot_change_a_rejection(): void
    {
        $capture = $this->mock(BankMimeCaptureService::class);
        $capture->shouldReceive('armedFor')->once()->andReturn('synthetic-opaque-token');
        $capture->shouldReceive('capture')->once()->andThrow(new \RuntimeException('synthetic-private-redis-output'));
        $this->mock(BankMimeDiagnosticRunner::class)->makePartial()->shouldReceive('run')->once()->andReturn([
            'verification' => ['status' => 'invalid', 'reason' => 'invalid_signature'],
            'diagnostics' => $this->summary(), 'contentSha256' => hash('sha256', 'Synthetic MIME'),
        ]);
        $this->assertNull(app(BankMimeVerificationService::class)->verifiedPayload($this->payload()));
        $this->assertSame('invalid_signature', AuditLog::where('action', 'sepa.bmail_review_required')->sole()->metadata['verifier_reason']);
        Http::assertNothingSent();
    }

    public function test_actual_diagnostic_cli_keeps_verified_delivery_and_captures_only_tampered_rejection(): void
    {
        $this->assertSame('armed', $this->capture->arm($this->store, 120));
        $this->withSignedFixture(function (string $raw) {
            $service = app(BankMimeVerificationService::class);
            $valid = $service->verifiedPayload($this->payload($raw));
            $this->assertSame('tatrabanka.sk', $valid['_verified_bank_domain']);
            $this->assertSame('armed', $this->capture->status()['status']);
            $tampered = str_replace('Synthetic original body', 'Synthetic tampered body', $raw);
            $this->assertNotSame($raw, $tampered);
            $this->assertNull($service->verifiedPayload($this->payload($tampered)));
            $this->assertSame('captured', $this->capture->status()['status']);
            $diagnosis = $this->capture->diagnose();
            $this->assertSame('diagnosed', $diagnosis['status']);
            $this->assertSame('invalid_signature', $diagnosis['original']['reason']);
            $this->assertSame($diagnosis['original'], $diagnosis['recheck']);
            $this->assertNotEmpty($diagnosis['original']['signatures']);
            $this->assertSame('fail', $diagnosis['original']['signatures'][0]['verdict']);
            $this->assertSame(hash('sha256', $tampered), $this->slot()['content_sha256']);
            $this->assertDatabaseCount('bank_transactions', 0);
            Http::assertNothingSent();
            Queue::assertNothingPushed();
        });
    }

    private function withSignedFixture(callable $callback): void
    {
        $directory = sys_get_temp_dir().'/satflux-bank-capture-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $producer = new Process(['node', base_path('runtime/bank-mail-verifier/test/build-message.mjs')], base_path(), null,
                json_encode(['recipient' => $this->recipient, 'body' => "Synthetic original body\r\n"], JSON_THROW_ON_ERROR), 15);
            $producer->mustRun();
            $fixture = json_decode($producer->getOutput(), true, 16, JSON_THROW_ON_ERROR);
            file_put_contents($directory.'/dns.json', json_encode(['dnsName' => $fixture['dnsName'], 'dnsRecord' => $fixture['dnsRecord']], JSON_THROW_ON_ERROR));
            file_put_contents($directory.'/node', '#!/bin/sh'."\nexport SATFLUX_TEST_DNS_FIXTURE="
                .escapeshellarg($directory.'/dns.json')."\nexec node --import "
                .escapeshellarg(base_path('runtime/bank-mail-verifier/test/preload-dns.mjs')).' "$@"'."\n");
            chmod($directory.'/node', 0700);
            config(['bank_inbound.node_binary' => $directory.'/node']);
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
