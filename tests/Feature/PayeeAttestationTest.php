<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Models\UserMessage;
use App\Models\WalletConnection;
use App\Notifications\WalletPayeeMismatchNotification;
use App\Services\Boltz\SettlementLedgerService;
use App\Services\WalletSecurity\PayeeAttestationService;
use App\Services\WalletSecurity\WalletConfigIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Bolt11Test;

class PayeeAttestationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'type=blink;server=https://api.blink.sv/graphql;api-key=blink_test123;wallet-id=wallet456';

    /** BOLT11 the fake BTCPay hands out for new (canary) invoices; null = no Lightning destination. */
    private ?string $canaryInvoice = Bolt11Test::SPEC_COFFEE;

    /** @var list<string> BOLT11s reported as settled payments on invoice "paid-1". */
    private array $paidInvoices = [Bolt11Test::SPEC_DONATION];

    /** receivedDate of those payments (default: just now, i.e. after any baseline in the test). */
    private ?string $paidAt = null;

    private string $paymentStatus = 'Settled';

    private bool $includeMethodDestination = true;

    private bool $includePaymentDestination = true;

    /** Distinct payment ids per sync so the ledger sees each payment once per test step. */
    private int $paymentSerial = 0;

    private bool $faked = false;

    /** @var list<string> */
    private array $archived = [];

    private function fakeBtcPay(): void
    {
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        Http::fake(function (Request $request) {
            $url = $request->url();
            if ($request->method() === 'POST' && preg_match('#/stores/[^/]+/invoices$#', $url)) {
                return Http::response(['id' => 'canary-1', 'status' => 'New'], 200);
            }
            if ($request->method() === 'DELETE' && preg_match('#/invoices/([^/]+)$#', $url, $m)) {
                $this->archived[] = $m[1];

                return Http::response([], 200);
            }
            if (preg_match('#/invoices/canary-1/payment-methods#', $url)) {
                return Http::response($this->canaryInvoice === null ? [] : [
                    ['paymentMethodId' => 'BTC-LN', 'destination' => $this->canaryInvoice, 'payments' => []],
                ], 200);
            }
            if (preg_match('#/invoices/paid-1/payment-methods#', $url)) {
                $payments = array_map(function ($b) {
                    $payment = [
                        'id' => 'p'.$this->paymentSerial.md5($b),
                        'value' => '0.00001',
                        'status' => $this->paymentStatus,
                        'receivedDate' => $this->paidAt ?? now()->addSecond()->toIso8601String(),
                    ];
                    if ($this->includePaymentDestination) {
                        $payment['destination'] = $b;
                    }

                    return $payment;
                }, $this->paidInvoices);

                $lightning = [
                    'paymentMethodId' => 'BTC-LN',
                    'rate' => '60000',
                    'payments' => $payments,
                ];
                if ($this->includeMethodDestination) {
                    $lightning['destination'] = $this->paidInvoices[0];
                }

                return Http::response([
                    ['paymentMethodId' => 'BTC-CHAIN', 'destination' => 'bc1qxyz', 'payments' => [], 'rate' => '60000'],
                    $lightning,
                ], 200);
            }
            if (preg_match('#/invoices/paid-1$#', $url)) {
                return Http::response(['id' => 'paid-1', 'status' => 'Settled', 'currency' => 'EUR', 'amount' => '1'], 200);
            }
            if (str_contains($url, '/payment-methods')) {
                return Http::response([
                    ['paymentMethodId' => 'BTC-LN', 'enabled' => true, 'config' => ['connectionString' => self::SECRET]],
                ], 200);
            }

            return Http::response([], 200);
        });
    }

    /** @return array{0: User, 1: Store, 2: WalletConnection} */
    private function connectedStore(): array
    {
        $user = User::factory()->create();
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
    public function baseline_learns_the_payee_from_a_canary_invoice_and_archives_it(): void
    {
        $this->fakeBtcPay();
        [$user, , $connection] = $this->connectedStore();

        $this->assertTrue(app(WalletConfigIntegrityService::class)->baseline($connection, $user));

        $fresh = $connection->fresh();
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $fresh->payee_pubkeys);
        $this->assertSame('canary', $fresh->payee_learn_source);
        $this->assertNotNull($fresh->payee_learned_at);
        $this->assertSame(['canary-1'], $this->archived, 'the canary invoice is archived right after reading');
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.payee_learned', 'target_id' => $connection->id]);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/invoices')
            && ($r['metadata']['satflux_canary'] ?? false) === true && $r['currency'] === 'BTC');
    }

    #[Test]
    public function without_a_canary_the_first_settled_payment_is_trusted(): void
    {
        $this->fakeBtcPay();
        $this->canaryInvoice = null;
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->assertNull($connection->fresh()->payee_pubkeys);

        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $fresh = $connection->fresh();
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $fresh->payee_pubkeys);
        $this->assertSame('first_payment', $fresh->payee_learn_source);
    }

    #[Test]
    public function a_payment_signed_by_another_node_raises_a_security_incident_once(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        $admin = User::factory()->admin()->create();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);

        // Attacker's invoice gets paid.
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $fresh = $connection->fresh();
        $this->assertNotNull($fresh->payee_mismatch_at);
        $this->assertSame(Bolt11Test::OTHER_PAYEE, $fresh->payee_mismatch_details['pubkey']);
        $this->assertSame('paid-1', $fresh->payee_mismatch_details['invoice_id']);
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $fresh->payee_mismatch_details['expected']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.payee_mismatch', 'target_id' => $connection->id]);

        $merchantMessage = UserMessage::where('user_id', $user->id)->where('type', 'security')->first();
        $this->assertNotNull($merchantMessage);
        $this->assertStringContainsString('received by node 029fc62178…883826', $merchantMessage->body);
        $this->assertStringContainsString('Check your wallet balance', $merchantMessage->body);
        $this->assertDatabaseHas('user_messages', ['user_id' => $admin->id, 'type' => 'security']);
        Notification::assertSentTo($user, WalletPayeeMismatchNotification::class);

        // Same invoice synced again (webhook retry, daily reconcile): the
        // payment is already in the ledger, nothing is re-judged.
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->where('type', 'security')->count());
        // A NEW payment to the foreign node while the incident is open: still one incident.
        $this->paymentSerial++;
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());

        // Payments to the known node keep passing while the incident is open.
        $this->paidInvoices = [Bolt11Test::SPEC_DONATION];
        $result = app(PayeeAttestationService::class)->attestInvoice($store, 'paid-1', [
            ['paymentMethodId' => 'BTC-LN', 'destination' => Bolt11Test::SPEC_DONATION, 'payments' => [['destination' => Bolt11Test::SPEC_DONATION]]],
        ]);
        $this->assertSame(['ok'], array_values($result));
        $this->assertNotNull($connection->fresh()->payee_mismatch_at);
    }

    #[Test]
    public function historical_payments_are_neither_judged_nor_learned_from(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);

        // The daily reconcile re-reads last week's invoices, paid when the store used another wallet.
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        $this->paidAt = now()->subDays(7)->toIso8601String();
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $this->assertNull($connection->fresh()->payee_mismatch_at);
        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());
        $this->assertDatabaseCount('store_settlements', 1);

        // Without any allow-list an old payment must not become the trusted node either.
        $connection->forceFill(['payee_pubkeys' => null, 'payee_learned_at' => null, 'payee_learn_source' => null])->save();
        $this->paymentSerial++;
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');
        $this->assertNull($connection->fresh()->payee_pubkeys);
    }

    #[Test]
    public function a_lightning_payment_is_attested_when_an_existing_row_first_settles(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        $admin = User::factory()->admin()->create();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);

        // BTCPay can expose a received payment before the settled row carries
        // a usable BOLT11 destination. The later Settled transition must still
        // run payee attestation for the same payment id.
        $this->paymentStatus = 'Processing';
        $this->includeMethodDestination = false;
        $this->includePaymentDestination = false;
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $this->assertNull($connection->fresh()->payee_mismatch_at);
        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());
        $this->assertSame(0, UserMessage::where('user_id', $user->id)->where('type', 'security')->count());
        $this->assertDatabaseHas('store_settlements', [
            'store_id' => $store->id,
            'btcpay_invoice_id' => 'paid-1',
            'payment_status' => 'Processing',
            'destination' => null,
        ]);

        $this->paymentStatus = 'Settled';
        $this->includePaymentDestination = true;
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1', forgetCache: true);

        $fresh = $connection->fresh();
        $this->assertNotNull($fresh->payee_mismatch_at);
        $this->assertSame(Bolt11Test::OTHER_PAYEE, $fresh->payee_mismatch_details['pubkey']);
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->where('type', 'security')->count());
        $this->assertSame(1, UserMessage::where('user_id', $admin->id)->where('type', 'security')->count());
        $this->assertDatabaseHas('store_settlements', [
            'store_id' => $store->id,
            'btcpay_invoice_id' => 'paid-1',
            'payment_status' => 'Settled',
            'destination' => Bolt11Test::OTHER_INVOICE,
        ]);
    }

    #[Test]
    public function a_settled_payment_is_attested_when_its_destination_appears_later(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        $admin = User::factory()->admin()->create();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);

        // A settled row without any BOLT11 cannot be judged yet.
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        $this->includeMethodDestination = false;
        $this->includePaymentDestination = false;
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $this->assertNull($connection->fresh()->payee_mismatch_at);
        $this->assertSame(0, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());
        $this->assertDatabaseHas('store_settlements', [
            'store_id' => $store->id,
            'btcpay_invoice_id' => 'paid-1',
            'payment_status' => 'Settled',
            'destination' => null,
        ]);

        // BTCPay can fill the invoice/method destination later without changing
        // the payment status; that first usable destination must still be judged.
        $this->includeMethodDestination = true;
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1', forgetCache: true);

        $fresh = $connection->fresh();
        $this->assertNotNull($fresh->payee_mismatch_at);
        $this->assertSame(Bolt11Test::OTHER_PAYEE, $fresh->payee_mismatch_details['pubkey']);
        $this->assertSame(1, AuditLog::where('action', 'wallet_connection.payee_mismatch')->count());
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->where('type', 'security')->count());
        $this->assertSame(1, UserMessage::where('user_id', $admin->id)->where('type', 'security')->count());
        $this->assertDatabaseHas('store_settlements', [
            'store_id' => $store->id,
            'btcpay_invoice_id' => 'paid-1',
            'payment_status' => 'Settled',
            'destination' => Bolt11Test::OTHER_INVOICE,
        ]);
    }

    #[Test]
    public function an_unsettled_lightning_payment_does_not_seed_the_first_payee_allow_list(): void
    {
        $this->fakeBtcPay();
        $this->canaryInvoice = null;
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->assertNull($connection->fresh()->payee_pubkeys);

        $this->paymentStatus = 'Processing';
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');
        $this->assertNull($connection->fresh()->payee_pubkeys);

        $this->paymentStatus = 'Settled';
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1', forgetCache: true);

        $fresh = $connection->fresh();
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $fresh->payee_pubkeys);
        $this->assertSame('first_payment', $fresh->payee_learn_source);
    }

    #[Test]
    public function a_node_the_wallet_signs_with_right_now_is_learned_instead_of_flagged(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $connection->fresh()->payee_pubkeys);

        // Provider moved to another node (Blink lnd1 -> lnd2 style): fresh canaries come from it too.
        $this->canaryInvoice = Bolt11Test::OTHER_INVOICE;
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $fresh = $connection->fresh();
        $this->assertNull($fresh->payee_mismatch_at);
        $this->assertSame([Bolt11Test::SPEC_PAYEE, Bolt11Test::OTHER_PAYEE], $fresh->payee_pubkeys);
        $this->assertSame(0, UserMessage::where('user_id', $user->id)->where('type', 'security')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.payee_learned']);
        $this->assertSame('canary_reconfirm', AuditLog::where('action', 'wallet_connection.payee_learned')->latest('id')->first()->metadata['reason']);
    }

    #[Test]
    public function reset_command_closes_incidents_and_purges_their_messages(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        $admin = User::factory()->admin()->create();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');
        $this->assertNotNull($connection->fresh()->payee_mismatch_at);
        $this->assertSame($connection->id, UserMessage::where('user_id', $user->id)->value('wallet_connection_id'));
        UserMessage::createForUser($admin->id, 'Wallet config drift: other', 'keep me', 'security');
        // A genuine incident of another store, already resolved by the admin: its message must survive.
        [$otherUser, , $otherConnection] = $this->connectedStore();
        $kept = UserMessage::createForUser($otherUser->id, 'Payment received by an unknown wallet - Other', 'real', 'security', null, null, $otherConnection->id);
        // A message from before the column existed (no id) inside the window is purged.
        $legacy = UserMessage::createForUser($admin->id, 'Payee mismatch: Legacy', 'old', 'security');
        $oldLegacy = UserMessage::createForUser($otherUser->id, 'Payee mismatch: Old legacy', 'keep', 'security');
        $oldLegacy->forceFill(['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)])->save();
        // An earlier, already resolved incident of the SAME connection: its message is outside the window.
        $earlier = UserMessage::createForUser($user->id, 'Payment received by an unknown wallet - Earlier', 'resolved', 'security', null, null, $connection->id);
        $earlier->forceFill(['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)])->save();

        $this->artisan('wallet-connections:reset-payee-incidents', ['--dry-run' => true, '--purge-messages' => true])
            ->expectsOutputToContain('Would reset 1 incident(s), 3 security message(s)')
            ->assertExitCode(0);
        $this->assertNotNull($connection->fresh()->payee_mismatch_at);

        $this->artisan('wallet-connections:reset-payee-incidents', ['--since' => now()->subMinute()->toDateTimeString(), '--purge-messages' => true])
            ->expectsOutputToContain('Reset 1 incident(s), 3 security message(s)')
            ->assertExitCode(0);

        $this->assertNull($connection->fresh()->payee_mismatch_at);
        $this->assertNotNull($earlier->fresh(), 'with --since a linked message outside the window stays');
        $this->assertSame(1, UserMessage::where('user_id', $user->id)->count());
        $this->assertSame(1, UserMessage::where('user_id', $admin->id)->count(), 'unrelated security messages stay');
        $this->assertNotNull($kept->fresh(), 'a resolved incident of another connection keeps its message');
        $this->assertNull($legacy->fresh(), 'legacy messages without an id fall back to the time window');
        $this->assertNotNull($oldLegacy->fresh(), 'legacy messages outside the explicit time window stay');
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.payee_incident_reset', 'target_id' => $connection->id]);
    }

    #[Test]
    public function reconnecting_the_wallet_relearns_the_payee_and_closes_the_incident(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');
        $this->assertNotNull($connection->fresh()->payee_mismatch_at);

        // Merchant moved to the other provider and reconnected: the canary now comes from that node.
        $this->canaryInvoice = Bolt11Test::OTHER_INVOICE;
        app(WalletConfigIntegrityService::class)->baseline($connection->fresh(), $user, 'connected');

        $fresh = $connection->fresh();
        $this->assertNull($fresh->payee_mismatch_at);
        $this->assertSame([Bolt11Test::OTHER_PAYEE], $fresh->payee_pubkeys);
    }

    #[Test]
    public function admin_can_accept_a_node_relearn_and_sees_payee_incidents(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson('/api/admin/wallet-changes/drifts')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payee_mismatch_details.pubkey', Bolt11Test::OTHER_PAYEE)
            ->assertJsonPath('data.0.payee_pubkeys.0', Bolt11Test::SPEC_PAYEE);

        $this->postJson("/api/admin/wallet-connections/{$connection->id}/accept-payee", ['pubkey' => 'nope'])
            ->assertStatus(422);
        $this->postJson("/api/admin/wallet-connections/{$connection->id}/accept-payee", ['pubkey' => Bolt11Test::OTHER_PAYEE])
            ->assertStatus(200)
            ->assertJsonPath('data.payee_pubkeys.1', Bolt11Test::OTHER_PAYEE);

        $fresh = $connection->fresh();
        $this->assertNull($fresh->payee_mismatch_at);
        $this->assertSame([Bolt11Test::SPEC_PAYEE, Bolt11Test::OTHER_PAYEE], $fresh->payee_pubkeys);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_connection.payee_accepted', 'user_id' => $admin->id]);
        $this->getJson('/api/admin/wallet-changes/drifts')->assertJsonCount(0, 'data');

        $this->postJson("/api/admin/wallet-connections/{$connection->id}/learn-payee")
            ->assertStatus(200)
            ->assertJsonPath('data.payee_pubkeys', [Bolt11Test::SPEC_PAYEE]);

        $actions = array_column($this->getJson('/api/admin/wallet-changes?store_id='.$store->id)->json('data'), 'action');
        $this->assertContains('wallet_connection.payee_mismatch', $actions);
        $this->assertContains('wallet_connection.payee_accepted', $actions);
        $this->assertContains('wallet_connection.payee_learned', $actions);

        // Support cannot.
        $this->actingAs(User::factory()->support()->create())
            ->postJson("/api/admin/wallet-connections/{$connection->id}/accept-payee", ['pubkey' => Bolt11Test::OTHER_PAYEE])
            ->assertStatus(403);
    }

    #[Test]
    public function learn_payees_command_fills_missing_allow_lists_only(): void
    {
        $this->fakeBtcPay();
        [, , $withList] = $this->connectedStore();
        $withList->forceFill(['payee_pubkeys' => [Bolt11Test::OTHER_PAYEE], 'payee_learn_source' => 'canary', 'payee_learned_at' => now()])->save();
        [, , $without] = $this->connectedStore();

        $this->artisan('wallet-connections:learn-payees')
            ->expectsOutputToContain('learned: 1, skipped: 0')
            ->assertExitCode(0);

        $this->assertSame([Bolt11Test::OTHER_PAYEE], $withList->fresh()->payee_pubkeys, 'existing lists are left alone');
        $this->assertSame([Bolt11Test::SPEC_PAYEE], $without->fresh()->payee_pubkeys);
    }

    #[Test]
    public function merchant_endpoint_exposes_the_payee_incident(): void
    {
        Notification::fake();
        $this->fakeBtcPay();
        [$user, $store, $connection] = $this->connectedStore();
        app(WalletConfigIntegrityService::class)->baseline($connection, $user);
        $this->paidInvoices = [Bolt11Test::OTHER_INVOICE];
        app(SettlementLedgerService::class)->syncInvoice($store, 'paid-1');

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/wallet-connection")
            ->assertStatus(200)
            ->assertJsonPath('data.payee_mismatch_details.pubkey', Bolt11Test::OTHER_PAYEE);
    }
}
