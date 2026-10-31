<?php

namespace Tests\Feature;

use App\Http\Controllers\MessageController;
use App\Jobs\ProcessBtcPayWebhook;
use App\Jobs\VerifyWalletConfig;
use App\Models\AuditLog;
use App\Models\EmailVerificationChallenge;
use App\Models\Store;
use App\Models\User;
use App\Models\UserMessage;
use App\Models\WalletConnection;
use App\Models\WebhookEvent;
use App\Notifications\WalletConfigDriftNotification;
use App\Services\WalletConnectionService;
use App\Services\WalletSecurity\PayeeAttestationService;
use App\Services\WalletSecurity\WalletConfigIntegrityService;
use App\Services\WalletSecurity\WalletSecurityNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WalletConfigIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_test123;wallet-id=wallet456';

    private const OTHER_SECRET = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_ATTACKER;wallet-id=wallet999';

    private string $lnConnectionString = self::SECRET;

    private bool $lnEnabled = true;

    private bool $btcpayDown = false;

    private bool $lnurlEnabled = false;

    private bool $noMethods = false;

    private string $onchainDescriptor = 'xpub...';

    private array $additionalMethods = [];

    /**
     * Fake BTCPay once per test: payment-methods answers with the current
     * $lnConnectionString / $lnEnabled (later Http::fake() calls would not
     * override an earlier stub, so the stub reads the test state instead).
     */
    private function fakeBtcPay(string $connectionString = self::SECRET, bool $lnEnabled = true): void
    {
        $this->lnConnectionString = $connectionString;
        $this->lnEnabled = $lnEnabled;
        $this->btcpayDown = false;
        if ($this->btcpayFaked) {
            return;
        }
        $this->btcpayFaked = true;
        Http::fake(function ($request) {
            if ($this->btcpayDown) {
                return Http::response(['message' => 'down'], 503);
            }
            if (str_contains($request->url(), '/payment-methods')) {
                if ($this->noMethods) {
                    return Http::response([], 200);
                }
                $methods = [
                    ['paymentMethodId' => 'BTC-CHAIN', 'enabled' => true, 'config' => ['derivationScheme' => $this->onchainDescriptor]],
                    ['paymentMethodId' => 'BTC-LN', 'enabled' => $this->lnEnabled, 'config' => ['connectionString' => $this->lnConnectionString, 'label' => 'x']],
                ];
                if ($this->lnurlEnabled) {
                    // Toggled by Satflux's own store settings (lnurlEnabled) - must never count as drift.
                    $methods[] = ['paymentMethodId' => 'BTC-LNURL', 'enabled' => true, 'config' => ['useBech32Scheme' => true]];
                }

                return Http::response(array_merge($methods, $this->additionalMethods), 200);
            }

            return Http::response([], 200);
        });
    }

    private bool $btcpayFaked = false;

    private function btcpayDown(): void
    {
        $this->fakeBtcPay($this->lnConnectionString, $this->lnEnabled);
        $this->btcpayDown = true;
    }

    private function connectedStore(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id, 'wallet_type' => 'blink']);
        $connection = WalletConnection::create([
            'store_id' => $store->id,
            'type' => 'blink',
            'encrypted_secret' => Crypt::encryptString(self::SECRET),
            'status' => 'connected',
            'submitted_by_user_id' => $user->id,
        ]);

        return [$user, $store, $connection];
    }

    #[Test]
    public function mark_connected_records_the_btcpay_config_fingerprint(): void
    {
        $this->fakeBtcPay(self::SECRET);
        [$user, , $connection] = $this->connectedStore();
        $connection->update(['status' => 'pending']);

        app(WalletConnectionService::class)->markConnected($connection, $user);

        $connection->refresh();
        $this->assertSame(['BTC-CHAIN', 'BTC-LN'], array_keys($connection->config_fingerprint));
        $this->assertNotNull($connection->config_verified_at);
        $this->assertNull($connection->drift_detected_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.config_baselined', 'target_id' => $connection->id]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'includeConfig=true'));
    }

    #[Test]
    public function unchanged_config_verifies_ok_and_a_swapped_connection_string_is_drift(): void
    {
        Notification::fake();
        $this->fakeBtcPay(self::SECRET);
        [$user, $store, $connection] = $this->connectedStore();
        $admin = User::factory()->admin()->create();
        $integrity = app(WalletConfigIntegrityService::class);
        $this->assertTrue($integrity->baseline($connection, $user));

        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
        $this->assertNull($connection->fresh()->drift_detected_at);

        // Store settings enable LNURL: presentation only, not a wallet change.
        $this->lnurlEnabled = true;
        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
        $this->assertArrayNotHasKey('BTC-LNURL', $connection->fresh()->config_snapshot);

        // Attacker swaps the receiving wallet directly on BTCPay.
        $this->fakeBtcPay(self::OTHER_SECRET);
        $result = $integrity->verify($connection->fresh());

        $this->assertSame('drift', $result['status']);
        $this->assertSame(['BTC-LN'], $result['diff']['changed']);
        $fresh = $connection->fresh();
        $this->assertNotNull($fresh->drift_detected_at);
        $this->assertSame(['BTC-LN'], $fresh->drift_details['changed']);
        // Masked expected/actual: addresses readable, credentials never stored in clear.
        $detail = $fresh->drift_details['details']['BTC-LN'];
        $this->assertStringContainsString('api-key=****t123', $detail['expected']);
        $this->assertStringContainsString('api-key=****CKER', $detail['actual']);
        $this->assertStringNotContainsString('blink_test123', json_encode($fresh->drift_details));
        $this->assertStringNotContainsString('blink_ATTACKER', json_encode($fresh->drift_details));
        // Merchant text names what changed without leaking credentials.
        $body = UserMessage::where('user_id', $user->id)->where('type', 'security')->latest('id')->value('body');
        $this->assertStringContainsString('The connection string of BTC-LN was changed (expected', $body);
        $this->assertStringContainsString('api-key=****CKER', $body);
        $this->assertStringNotContainsString('blink_ATTACKER', $body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.drift_detected', 'target_id' => $connection->id]);

        // Merchant: pinned security message + e-mail. Admin: security message.
        $this->assertDatabaseHas('user_messages', ['user_id' => $user->id, 'type' => 'security']);
        $this->assertDatabaseHas('user_messages', ['user_id' => $admin->id, 'type' => 'security']);
        Notification::assertSentTo($user, WalletConfigDriftNotification::class);

        // A second check does not re-alert while the drift persists.
        $integrity->verify($connection->fresh());
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->where('type', 'security')->count());
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.drift_detected')->count());

        // Config restored -> incident closed.
        $this->fakeBtcPay(self::SECRET);
        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
        $this->assertNull($connection->fresh()->drift_detected_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.drift_resolved', 'target_id' => $connection->id]);
        $this->assertDatabaseHas('user_messages', ['user_id' => $user->id, 'type' => 'info']);
    }

    #[Test]
    public function disabling_the_lightning_method_is_drift_too(): void
    {
        Notification::fake();
        $this->fakeBtcPay(self::SECRET);
        [$user, , $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);
        $integrity->baseline($connection, $user);

        $this->fakeBtcPay(self::SECRET, lnEnabled: false);

        $this->assertSame('drift', $integrity->verify($connection->fresh())['status']);
    }

    #[Test]
    public function sepa_activation_lifecycle_does_not_change_the_crypto_wallet_baseline(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);
        $this->assertTrue($integrity->baseline($connection, $user, learnPayee: false));
        $expected = $connection->fresh()->only(['config_fingerprint', 'config_snapshot']);

        foreach ([false, true, false, null] as $enabled) {
            $this->additionalMethods = $enabled === null ? [] : [
                ['paymentMethodId' => 'SEPA_INSTANT', 'enabled' => $enabled, 'config' => $enabled],
            ];
            $this->assertSame(['BTC-CHAIN', 'BTC-LN'], array_keys($integrity->snapshot($store)['fingerprint']));
            $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
            $this->assertSame($expected, $connection->fresh()->only(['config_fingerprint', 'config_snapshot']));
            $this->assertNull($connection->fresh()->drift_detected_at);
        }

        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.drift_detected')->count());
        $this->assertSame(0, UserMessage::where('type', 'security')->count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_legacy_sepa_only_incident_requires_a_fresh_matching_read_to_resolve_without_rebaselining(): void
    {
        Notification::fake();
        $this->mock(PayeeAttestationService::class)->shouldNotReceive('learn');
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);
        $this->assertTrue($integrity->baseline($connection, $user, learnPayee: false));
        $legacyConfig = ['enabled' => false, 'config' => false];
        $incident = ['changed' => ['SEPA_INSTANT'], 'added' => [], 'removed' => []];
        $connection->forceFill([
            'config_fingerprint' => array_merge($connection->config_fingerprint, [
                'SEPA_INSTANT' => hash('sha256', json_encode($legacyConfig)),
            ]),
            'config_snapshot' => array_merge($connection->config_snapshot, ['SEPA_INSTANT' => $legacyConfig]),
            'config_verified_at' => now()->subHour(),
            'drift_detected_at' => now()->subMinutes(30),
            'drift_details' => $incident,
        ])->save();
        AuditLog::log('wallet_connection.drift_detected', 'wallet_connection', $connection->id, ['diff' => $incident], null);
        $expected = $connection->fresh()->only(['config_fingerprint', 'config_snapshot']);
        $before = $connection->fresh()->only(['config_verified_at', 'drift_detected_at', 'drift_details']);
        $this->additionalMethods = [['paymentMethodId' => 'SEPA_INSTANT', 'enabled' => true, 'config' => true]];

        $lock = Cache::lock('wallet-update:'.$store->id, 1800);
        $this->assertTrue($lock->get());
        $requests = count(Http::recorded());
        try {
            $this->assertSame('skipped', $integrity->verify($connection->fresh())['status']);
            $this->assertSame($requests, count(Http::recorded()));
            $this->assertEquals($before, $connection->fresh()->only(array_keys($before)));
        } finally {
            $lock->release();
        }

        $this->btcpayDown();
        $this->assertSame('error', $integrity->verify($connection->fresh())['status']);
        $this->assertEquals($before, $connection->fresh()->only(array_keys($before)));
        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.drift_resolved')->count());

        $this->fakeBtcPay();
        $apiKey = $user->btcpay_api_key;
        $user->update(['btcpay_api_key' => null]);
        $requests = count(Http::recorded());
        $this->assertSame('error', $integrity->verify($connection->fresh())['status']);
        $this->assertSame($requests, count(Http::recorded()));
        $this->assertEquals($before, $connection->fresh()->only(array_keys($before)));
        $user->update(['btcpay_api_key' => $apiKey]);

        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
        $fresh = $connection->fresh();
        $this->assertNull($fresh->drift_detected_at);
        $this->assertNull($fresh->drift_details);
        $this->assertTrue($fresh->config_verified_at->greaterThan($before['config_verified_at']));
        $this->assertSame($expected, $fresh->only(array_keys($expected)));
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.config_baselined')->count());
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.drift_detected')->count());
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.drift_resolved')->count());
        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.drift_resolved')->count());
        Notification::assertNothingSent();
    }

    #[Test]
    #[DataProvider('monitoredMethodIds')]
    public function a_sepa_activation_does_not_hide_a_simultaneous_monitored_wallet_change(string $id): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        $this->additionalMethods = [['paymentMethodId' => $id, 'enabled' => true, 'config' => ['enabled' => true]]];
        [$user, , $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);
        $this->assertTrue($integrity->baseline($connection, $user, learnPayee: false));
        $expected = $connection->fresh()->only(['config_fingerprint', 'config_snapshot']);
        $this->additionalMethods = [
            ['paymentMethodId' => $id, 'enabled' => false, 'config' => ['enabled' => false]],
            ['paymentMethodId' => 'SEPA_INSTANT', 'enabled' => true, 'config' => true],
        ];

        $result = $integrity->verify($connection->fresh());

        $this->assertSame('drift', $result['status']);
        $this->assertSame([$id], $result['diff']['changed']);
        $this->assertSame([], $result['diff']['added']);
        $this->assertSame([], $result['diff']['removed']);
        $this->assertNotNull($connection->fresh()->drift_detected_at);
        $this->assertSame($expected, $connection->fresh()->only(array_keys($expected)));
    }

    public static function monitoredMethodIds(): array
    {
        return array_map(fn ($id) => [$id], ['CASHU', 'CASHUMELT', 'UNKNOWN', 'SEPA_INSTANT_EVIL', 'FAKE_SEPA_INSTANT']);
    }

    #[Test]
    public function sepa_does_not_resolve_a_crypto_incident_until_the_original_wallet_returns(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, , $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);
        $this->assertTrue($integrity->baseline($connection, $user, learnPayee: false));
        $expected = $connection->fresh()->only(['config_fingerprint', 'config_snapshot']);
        $this->additionalMethods = [['paymentMethodId' => 'SEPA_INSTANT', 'enabled' => true, 'config' => true]];
        $this->lnConnectionString = self::OTHER_SECRET;
        $this->onchainDescriptor = 'different-xpub';

        foreach ([true, false] as $sepaEnabled) {
            $this->additionalMethods[0]['enabled'] = $sepaEnabled;
            $this->additionalMethods[0]['config'] = $sepaEnabled;
            $result = $integrity->verify($connection->fresh());
            $this->assertSame('drift', $result['status']);
            $this->assertSame(['BTC-CHAIN', 'BTC-LN'], $result['diff']['changed']);
            $this->assertSame([], $result['diff']['added']);
            $this->assertNotNull($connection->fresh()->drift_detected_at);
        }
        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.drift_resolved')->count());
        $this->assertSame($expected, $connection->fresh()->only(array_keys($expected)));

        $this->fakeBtcPay();
        $this->onchainDescriptor = 'xpub...';
        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);
        $this->assertNull($connection->fresh()->drift_detected_at);
    }

    #[Test]
    public function legacy_and_live_fingerprints_exclude_only_exact_case_insensitive_sepa_ids(): void
    {
        foreach (['SEPA_INSTANT', 'sepa_instant', 'SePa_InStAnT'] as $id) {
            $this->assertSame(['changed' => [], 'added' => [], 'removed' => []], WalletConfigIntegrityService::diff([$id => 'before'], [$id => 'after']));
            $this->assertSame(['changed' => [], 'added' => [], 'removed' => []], WalletConfigIntegrityService::diff([$id => 'before'], []));
            $this->assertSame(['changed' => [], 'added' => [], 'removed' => []], WalletConfigIntegrityService::diff([], [$id => 'after']));
        }
        foreach (['SEPA_INSTANT_EVIL', 'FAKE_SEPA_INSTANT', 'SEPA_INSTANT ', 'BTC-LN', 'BTC-CHAIN', 'CASHU', 'CASHUMELT', 'UNKNOWN'] as $id) {
            $this->assertSame([$id], WalletConfigIntegrityService::diff([$id => 'before'], [$id => 'after'])['changed']);
            $this->assertSame([$id], WalletConfigIntegrityService::diff([$id => 'before'], [])['removed']);
            $this->assertSame([$id], WalletConfigIntegrityService::diff([], [$id => 'after'])['added']);
        }
    }

    #[Test]
    public function a_row_without_fingerprint_is_baselined_late_and_btcpay_errors_never_flag_drift(): void
    {
        Notification::fake();
        [$user, , $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);

        $this->btcpayDown();
        $this->assertSame('error', $integrity->verify($connection->fresh())['status']);
        $this->assertNull($connection->fresh()->config_fingerprint);

        $this->fakeBtcPay(self::SECRET);
        $this->assertSame('baselined', $integrity->verify($connection->fresh())['status']);
        $this->assertNotNull($connection->fresh()->config_fingerprint);

        $integrity->baseline($connection->fresh(), $user);
        $this->fakeBtcPay(self::OTHER_SECRET);
        $integrity->verify($connection->fresh());
        $this->assertNotNull($connection->fresh()->drift_detected_at);

        // Outage while drifting: the incident stays open, nothing new is raised.
        $this->btcpayDown();
        $this->assertSame('error', $integrity->verify($connection->fresh())['status']);
        $this->assertNotNull($connection->fresh()->drift_detected_at);
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.drift_detected')->count());
    }

    #[Test]
    public function verify_command_reports_drift_with_a_failing_exit_code(): void
    {
        Notification::fake();
        $this->fakeBtcPay(self::SECRET);
        [$user, , $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);

        $this->artisan('wallet-connections:verify-config', ['--all' => true])
            ->expectsOutputToContain('drift: 0')
            ->assertExitCode(0);

        $this->fakeBtcPay(self::OTHER_SECRET);
        $this->artisan('wallet-connections:verify-config', ['--all' => true])
            ->expectsOutputToContain('drift: 1')
            ->assertExitCode(1);
    }

    #[Test]
    public function invoice_webhooks_queue_a_config_check_for_the_store(): void
    {
        Bus::fake([VerifyWalletConfig::class]);
        $this->fakeBtcPay();
        [, $store] = $this->connectedStore();
        $event = WebhookEvent::create([
            'store_id' => $store->id,
            'event_type' => 'InvoiceSettled',
            'payload' => ['storeId' => $store->btcpay_store_id, 'invoiceId' => 'inv1', 'type' => 'InvoiceSettled'],
            'verified' => true,
            'delivery_id' => 'd1',
        ]);

        (new ProcessBtcPayWebhook($event))->handle();

        Bus::assertDispatched(VerifyWalletConfig::class, fn ($job) => $job->storeId === $store->id);
    }

    #[Test]
    public function the_verify_job_skips_rows_checked_within_the_recheck_window(): void
    {
        $this->fakeBtcPay(self::SECRET);
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->btcpayDown();

        (new VerifyWalletConfig($store->id))->handle(app(WalletConfigIntegrityService::class));

        $this->assertNotNull($connection->fresh()->config_verified_at);
        $this->assertNull($connection->fresh()->drift_detected_at);
    }

    #[Test]
    public function replacing_a_connected_wallet_and_revealing_its_secret_leave_security_messages(): void
    {
        Notification::fake();
        $this->fakeBtcPay(self::SECRET);
        $user = User::factory()->create([
            'guest_recovery_public_key' => str_repeat('a', 64),
            'guest_recovery_enrolled_at' => now(),
        ]);
        [, $store] = $this->connectedStore($user);
        $this->seedGrant($user, $store);

        $this->actingAs($user)
            ->postJson("/api/stores/{$store->id}/wallet-connection/reveal", [])
            ->assertStatus(200);
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->where('type', 'security')->where('title', 'like', 'Wallet secret revealed%')->count());

        // The reveal keeps the grant alive; replacing the wallet consumes it.
        $this->postJson("/api/stores/{$store->id}/wallet-connection", [
            'type' => 'blink',
            'secret' => self::OTHER_SECRET,
            'fallback_lightning_address' => 'merchant@blink.sv',
        ])->assertStatus(201);
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->where('type', 'security')->where('title', 'like', 'Wallet connection replaced%')->count());
    }

    #[Test]
    public function recent_security_messages_are_available_in_the_feed_and_can_be_read(): void
    {
        $this->fakeBtcPay();
        $user = User::factory()->create();
        $old = UserMessage::createForUser($user->id, 'Old info', 'x', 'info');
        $old->forceFill(['created_at' => now()->subDays(40), 'read_at' => now()->subDays(39)])->save();
        $security = UserMessage::createForUser($user->id, 'Wallet secret revealed - Shop', 'body', 'security', 'https://x/y', 'Wallet connection');

        $this->actingAs($user)->getJson('/api/messages/count')
            ->assertJsonPath('data.security_unread', 1)
            ->assertJsonPath('data.unread', 1);

        $list = $this->getJson('/api/messages')->assertStatus(200)->json('data');
        $this->assertSame(MessageController::LOCAL_ID_PREFIX.$security->id, $list[0]['id'], json_encode($list));
        $this->assertSame('security', $list[0]['type']);
        $this->assertCount(1, $list, 'read messages older than 30 days are not listed');

        $this->patchJson('/api/messages/'.MessageController::LOCAL_ID_PREFIX.$security->id.'/read')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'security');
        $this->assertNotNull($security->fresh()->read_at);
        $this->getJson('/api/messages/count')->assertJsonPath('data.security_unread', 0);

        // Another user's message id is not readable.
        $other = User::factory()->create();
        $this->actingAs($other)
            ->patchJson('/api/messages/'.MessageController::LOCAL_ID_PREFIX.$security->id.'/read')
            ->assertStatus(404);
    }

    #[Test]
    public function messages_without_a_btcpay_key_still_show_security_alerts(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => null]);
        UserMessage::createForUser($user->id, 'Wallet configuration changed outside Satflux - Shop', 'b', 'security');

        $this->actingAs($user)->getJson('/api/messages')
            ->assertJsonPath('available', true)
            ->assertJsonPath('data.0.type', 'security');
    }

    #[Test]
    public function admin_wallet_change_log_lists_wallet_actions_with_filters_and_drifts(): void
    {
        Notification::fake();
        $this->fakeBtcPay(self::SECRET);
        [$user, $store, $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);
        $integrity->baseline($connection, $user);
        AuditLog::log('business_document.issued', 'business_document', $connection->id, ['store_id' => $store->id], $user->id);
        $this->fakeBtcPay(self::OTHER_SECRET);
        $integrity->verify($connection->fresh());

        $admin = User::factory()->admin()->create();
        $support = User::factory()->support()->create();

        $this->actingAs($support)->getJson('/api/admin/wallet-changes')->assertStatus(403);

        $response = $this->actingAs($admin)->getJson('/api/admin/wallet-changes?store_id='.$store->id)->assertStatus(200);
        $actions = array_column($response->json('data'), 'action');
        $this->assertContains('wallet_connection.config_baselined', $actions);
        $this->assertContains('wallet_connection.drift_detected', $actions);
        $this->assertNotContains('business_document.issued', $actions);
        $this->assertSame($store->name, $response->json('data.0.store.name'));

        $this->getJson('/api/admin/wallet-changes?action=wallet_connection.drift_detected')
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/wallet-changes?q='.urlencode($user->email))
            ->assertJsonPath('data.0.user.email', $user->email);

        $this->getJson('/api/admin/wallet-changes/drifts')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.store.id', $store->id)
            ->assertJsonPath('data.0.drift_details.changed.0', 'BTC-LN');

        // Admin accepts the live config after investigating.
        $this->postJson("/api/admin/wallet-connections/{$connection->id}/rebaseline")->assertStatus(200);
        $this->assertNull($connection->fresh()->drift_detected_at);
        $this->getJson('/api/admin/wallet-changes/drifts')->assertJsonCount(0, 'data');
        $this->postJson("/api/admin/wallet-connections/{$connection->id}/verify-config")
            ->assertJsonPath('data.status', 'ok');
    }

    #[Test]
    public function merchant_text_shows_old_and_new_lightning_address(): void
    {
        $connection = new WalletConnection(['type' => 'lnaddress', 'encrypted_secret' => Crypt::encryptString('type=lnaddress;ln-address=shop@blink.sv;')]);

        $text = WalletSecurityNotifier::describeForMerchant($connection, [
            'changed' => ['BTC-LN'], 'added' => [], 'removed' => [],
            'details' => ['BTC-LN' => [
                'expected' => 'enabled connectionString=type=lnaddress;ln-address=shop@blink.sv;',
                'actual' => 'enabled connectionString=type=lnaddress;ln-address=thief@coinos.io;',
            ]],
        ]);
        $this->assertSame('Your Lightning address was changed from shop@blink.sv to thief@coinos.io.', $text);

        // Baseline predates snapshots: the expected side comes from the stored secret.
        $text = WalletSecurityNotifier::describeForMerchant($connection, [
            'changed' => ['BTC-LN'], 'added' => [], 'removed' => [],
            'details' => ['BTC-LN' => ['expected' => null, 'actual' => 'enabled connectionString=type=lnaddress;ln-address=thief@coinos.io;']],
        ]);
        $this->assertSame('Your Lightning address was changed from shop@blink.sv to thief@coinos.io.', $text);

        $text = WalletSecurityNotifier::describeForMerchant($connection, [
            'changed' => ['BTC-LN'], 'added' => ['BTC-CHAIN'], 'removed' => [],
            'details' => [
                'BTC-LN' => ['expected' => 'enabled connectionString=type=lnaddress;ln-address=shop@blink.sv;', 'actual' => 'disabled connectionString=type=lnaddress;ln-address=shop@blink.sv;'],
                'BTC-CHAIN' => ['expected' => null, 'actual' => 'enabled derivationScheme=xpub6****abcd'],
            ],
        ]);
        $this->assertSame('Payment method BTC-LN was disabled. Payment method BTC-CHAIN was added (enabled derivationScheme=xpub6****abcd).', $text);
    }

    #[Test]
    public function an_empty_baseline_is_still_a_baseline(): void
    {
        Notification::fake();
        [$user, , $connection] = $this->connectedStore();
        $integrity = app(WalletConfigIntegrityService::class);

        // Store without any payment method yet: baseline is an empty map.
        $this->fakeBtcPay();
        $this->noMethods = true;
        $this->assertTrue($integrity->baseline($connection, $user));
        $this->assertSame([], $connection->fresh()->config_fingerprint);
        $this->assertSame('ok', $integrity->verify($connection->fresh())['status']);

        // A method appearing later is drift, not a late baseline.
        $this->noMethods = false;
        $result = $integrity->verify($connection->fresh());
        $this->assertSame('drift', $result['status']);
        $this->assertSame(['BTC-CHAIN', 'BTC-LN'], $result['diff']['added']);
    }

    #[Test]
    public function samrock_completion_records_a_baseline(): void
    {
        config(['services.boltz.receiving_enabled' => true]);
        $this->fakeBtcPay();
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id, 'wallet_type' => 'aqua_boltz']);

        $connection = app(WalletConnectionService::class)->markSamRockConnected($store, $user, null);

        $this->assertSame(['BTC-CHAIN', 'BTC-LN'], array_keys($connection->fresh()->config_fingerprint));
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.config_baselined', 'target_id' => $connection->id]);
    }

    private function seedGrant(User $user, Store $store): void
    {
        EmailVerificationChallenge::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationChallenge::PURPOSE_WALLET_CONNECTION_CHANGE,
            'email' => (string) $user->email,
            'code_hash' => str_repeat('0', 64),
            'payload' => ['store_id' => $store->id],
            'send_count' => 1,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);
    }
}
