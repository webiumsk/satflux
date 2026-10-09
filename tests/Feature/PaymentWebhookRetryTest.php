<?php

namespace Tests\Feature;

use App\Jobs\ProcessBtcPayWebhook;
use App\Jobs\SyncInvoiceSettlements;
use App\Jobs\VerifyWalletConfig;
use App\Models\CompanySlotPurchase;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\ExpenseIsdocPackPurchase;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\SubscriptionService;
use App\Services\Invoicing\BusinessDocumentPaymentWebhookService;
use App\Services\Invoicing\CompanySlotService;
use App\Services\Invoicing\SubscriptionBillingInvoiceService;
use App\Services\StoreEmailRuleDispatcher;
use Fiber;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentWebhookRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.btcpay.base_url' => 'https://btcpay.example.test',
            'services.btcpay.api_key' => 'synthetic-server-key',
            'services.btcpay.subscription_store_id' => 'billing-store',
            'services.btcpay.subscription_webhook_secret' => 'synthetic-webhook-secret',
        ]);
        $this->app->forgetInstance(BtcPayClient::class);
        Queue::fake();
    }

    public static function packTypes(): array
    {
        return [['company_slot_pack'], ['expense_isdoc_pack']];
    }

    public static function earlyPaymentEvents(): array
    {
        return [['InvoiceReceivedPayment'], ['invoice.paid']];
    }

    public static function concurrentLookupStatuses(): array
    {
        return [['Settled'], ['Processing']];
    }

    #[Test]
    #[DataProvider('packTypes')]
    public function a_settled_delivery_retries_when_an_earlier_read_repopulates_the_cache(string $purpose): void
    {
        $user = User::factory()->create();
        $purchase = $purpose === 'company_slot_pack'
            ? CompanySlotPurchase::create([
                'user_id' => $user->id, 'slots' => 5, 'price_sats' => 120000,
                'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
            ])
            : ExpenseIsdocPackPurchase::create([
                'user_id' => $user->id, 'credits' => 25, 'price_eur' => 10,
                'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
            ]);
        $this->deliver([
            'storeId' => 'billing-store', 'invoiceId' => 'pack-invoice',
            'type' => 'InvoiceSettled', 'deliveryId' => 'payment-delivery',
        ])->assertOk();
        $event = WebhookEvent::where('delivery_id', 'payment-delivery')->firstOrFail();
        $store = new class extends ArrayStore
        {
            public bool $pauseAfterEviction = false;

            public function forget($key)
            {
                $result = parent::forget($key);
                if ($this->pauseAfterEviction && $key === 'btcpay:invoice:billing-store:pack-invoice:server') {
                    $this->pauseAfterEviction = false;
                    Fiber::suspend('settlement-worker-evicted-cache');
                }

                return $result;
            }
        };
        Cache::swap(new Repository($store));
        $reads = 0;
        Http::fake(function () use (&$reads, $purpose, $user) {
            // Capture an earlier provider response, then delay its cache write.
            $status = ++$reads === 1 ? 'Processing' : 'Settled';
            $response = Http::response([
                'id' => 'pack-invoice', 'status' => $status,
                'metadata' => ['purpose' => $purpose, 'userId' => (string) $user->id],
            ]);
            if ($reads === 1) {
                Fiber::suspend('earlier-processing-response-ready');
            }

            return $response;
        });
        $earlierRead = new Fiber(fn () => app(SubscriptionService::class)->fetchSettledInvoice('billing-store', 'pack-invoice'));
        $this->assertSame('earlier-processing-response-ready', $earlierRead->start());
        $store->pauseAfterEviction = true;
        $job = new ProcessBtcPayWebhook($event);
        $settlementWorker = new Fiber(fn () => $job->handle());
        $this->assertSame('settlement-worker-evicted-cache', $settlementWorker->start());
        $earlierRead->resume();
        $this->assertNull($earlierRead->getReturn());

        try {
            $settlementWorker->resume();
            $this->fail('An unconfirmed InvoiceSettled delivery must remain retryable.');
        } catch (\RuntimeException $e) {
            $this->assertSame('InvoiceSettled delivery did not return a settled invoice.', $e->getMessage());
        }
        $this->assertSame(1, $reads);
        $this->assertNull($event->fresh()->processed_at);
        $this->assertSame('pending', $purchase->fresh()->status);

        $job->handle();
        $this->assertSame(2, $reads);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertPurchasedQuantity($purpose, $user);
        $job->handle();
        $this->assertSame(2, $reads);
        $this->assertPurchasedQuantity($purpose, $user);
    }

    #[Test]
    #[DataProvider('earlyPaymentEvents')]
    public function early_unsettled_payment_events_are_processed_without_fulfillment(string $eventType): void
    {
        $user = User::factory()->create();
        $purchase = CompanySlotPurchase::create([
            'user_id' => $user->id, 'slots' => 5, 'price_sats' => 120000,
            'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
        ]);
        $this->deliver([
            'storeId' => 'billing-store', 'invoiceId' => 'pack-invoice',
            'type' => $eventType, 'deliveryId' => 'payment-delivery',
        ])->assertOk();
        Http::fake(['*' => Http::response([
            'id' => 'pack-invoice', 'status' => 'Processing',
            'metadata' => ['purpose' => 'company_slot_pack'],
        ])]);
        $event = WebhookEvent::where('delivery_id', 'payment-delivery')->firstOrFail();
        (new ProcessBtcPayWebhook($event))->handle();
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('pending', $purchase->fresh()->status);
        $this->assertSame(0, app(CompanySlotService::class)->paidSlotCount($user));
        (new ProcessBtcPayWebhook($event->fresh()))->handle();
        Http::assertSentCount(1);
    }

    #[Test]
    #[DataProvider('packTypes')]
    public function acknowledged_payments_can_retry_a_transient_failure_without_duplicate_fulfillment(string $purpose): void
    {
        $user = User::factory()->create();
        $purchase = $purpose === 'company_slot_pack'
            ? CompanySlotPurchase::create([
                'user_id' => $user->id, 'slots' => 5, 'price_sats' => 120000,
                'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
            ])
            : ExpenseIsdocPackPurchase::create([
                'user_id' => $user->id, 'credits' => 25, 'price_eur' => 10,
                'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
            ]);
        $metadata = ['purpose' => $purpose, 'userId' => (string) $user->id, 'purchaseId' => $purchase->id];
        $payload = [
            'storeId' => 'billing-store', 'invoiceId' => 'pack-invoice',
            'type' => 'InvoiceSettled', 'deliveryId' => 'payment-delivery',
        ];
        $this->deliver($payload)->assertOk()->assertJsonPath('status', 'received');
        $event = WebhookEvent::where('delivery_id', 'payment-delivery')->firstOrFail();
        $job = new ProcessBtcPayWebhook($event);

        Http::fakeSequence()
            ->push([], 503)
            ->push(['id' => 'pack-invoice', 'status' => 'Settled', 'metadata' => $metadata])
            ->push(['id' => 'pack-invoice', 'status' => 'Settled', 'metadata' => $metadata]);

        try {
            $job->handle();
            $this->fail('A failed invoice lookup must fail the job so the queue can retry it.');
        } catch (BtcPayException) {
            $this->assertNull($event->fresh()->processed_at);
            $this->assertSame('pending', $purchase->fresh()->status);
        }
        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff);

        // Ingress duplicates do not create extra jobs while the original is retryable.
        // PostgreSQL aborts a transaction on unique violations. Isolate this
        // expected rejection from RefreshDatabase's surrounding test transaction.
        DB::beginTransaction();
        try {
            $this->deliver($payload)->assertOk()->assertJsonPath('status', 'duplicate');
        } finally {
            DB::rollBack();
        }
        Queue::assertPushed(ProcessBtcPayWebhook::class, 1);

        $job->handle();
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertPurchasedQuantity($purpose, $user);
        Http::assertSentCount(2);

        // Another worker holding the original serialized event is a no-op.
        (new ProcessBtcPayWebhook($event))->handle();
        Http::assertSentCount(2);

        // BTCPay redelivery uses a different delivery ID for the same invoice.
        $payload['deliveryId'] = 'payment-redelivery';
        $this->deliver($payload)->assertOk()->assertJsonPath('status', 'received');
        (new ProcessBtcPayWebhook(WebhookEvent::where('delivery_id', 'payment-redelivery')->firstOrFail()))->handle();
        $this->assertPurchasedQuantity($purpose, $user);
        Http::assertSentCount(3);
    }

    #[Test]
    #[DataProvider('concurrentLookupStatuses')]
    public function a_worker_finishing_during_the_unlocked_lookup_does_not_grant_credits_twice(string $firstResponseStatus): void
    {
        $user = User::factory()->create();
        $purchase = ExpenseIsdocPackPurchase::create([
            'user_id' => $user->id, 'credits' => 25, 'price_eur' => 10,
            'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
        ]);
        $this->deliver([
            'storeId' => 'billing-store', 'invoiceId' => 'pack-invoice',
            'type' => 'InvoiceSettled', 'deliveryId' => 'payment-delivery',
        ])->assertOk();
        $event = WebhookEvent::where('delivery_id', 'payment-delivery')->firstOrFail();
        $transactionLevel = DB::transactionLevel();
        $reads = 0;
        Http::fake(function () use ($event, $transactionLevel, $firstResponseStatus, &$reads) {
            $this->assertSame($transactionLevel, DB::transactionLevel(), 'Provider reads must precede the payment transaction.');
            $status = ++$reads === 1 ? $firstResponseStatus : 'Settled';
            if ($reads === 1) {
                // Another worker completes this event while the first worker is
                // still waiting for its provider response, before it can lock.
                (new ProcessBtcPayWebhook($event->fresh()))->handle();
                $this->assertNotNull($event->fresh()->processed_at);
            }

            return Http::response([
                'id' => 'pack-invoice', 'status' => $status,
                'metadata' => ['purpose' => 'expense_isdoc_pack'],
            ]);
        });

        (new ProcessBtcPayWebhook($event))->handle();
        $this->assertSame(2, $reads);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertPurchasedQuantity('expense_isdoc_pack', $user);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    #[Test]
    public function settlement_dispatch_failure_does_not_skip_subscription_billing_after_commit(): void
    {
        config(['services.btcpay.subscription_plans.pro' => 'pro-plan']);
        SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year',
            'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => 'free']);
        Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'billing-store']);
        $this->deliver([
            'storeId' => 'billing-store', 'invoiceId' => 'subscription-invoice',
            'type' => 'InvoiceSettled', 'deliveryId' => 'payment-delivery',
        ])->assertOk();
        Http::fake(['*' => Http::response([
            'id' => 'subscription-invoice', 'status' => 'Settled',
            'metadata' => ['customerEmail' => $user->email, 'planId' => 'pro-plan'],
        ])]);
        $this->mock(StoreEmailRuleDispatcher::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatchForWebhook')->once();
        });
        $this->mock(BusinessDocumentPaymentWebhookService::class, function (MockInterface $mock) {
            $mock->shouldReceive('handleInvoicePayment')->once()->andReturnFalse();
        });
        $settlementAttempted = false;
        $this->mock(Dispatcher::class, function (MockInterface $mock) use (&$settlementAttempted) {
            $mock->shouldReceive('dispatch')->with(\Mockery::type(VerifyWalletConfig::class))->once()->andReturn(0);
            $mock->shouldReceive('dispatch')->with(\Mockery::type(SyncInvoiceSettlements::class))->once()
                ->andReturnUsing(function () use (&$settlementAttempted) {
                    $settlementAttempted = true;
                    throw new \RuntimeException('Synthetic settlement queue failure');
                });
        });
        $event = WebhookEvent::where('delivery_id', 'payment-delivery')->firstOrFail();
        $this->mock(SubscriptionBillingInvoiceService::class, function (MockInterface $mock) use ($event, &$settlementAttempted) {
            $mock->shouldReceive('fulfillPaidInvoice')->once()->andReturnUsing(function () use ($event, &$settlementAttempted) {
                $this->assertTrue($settlementAttempted);
                $this->assertNotNull($event->fresh()->processed_at);

                return null;
            });
        });

        (new ProcessBtcPayWebhook($event))->handle();
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('pro', $user->fresh()->role);
        // A retry of the completed event must not repeat either side effect.
        (new ProcessBtcPayWebhook($event->fresh()))->handle();
    }

    #[Test]
    public function a_failure_after_fulfillment_rolls_back_the_purchase_and_allows_retry(): void
    {
        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'billing-store']);
        $this->mock(StoreEmailRuleDispatcher::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatchForWebhook')->once();
        });
        $this->mock(BusinessDocumentPaymentWebhookService::class, function (MockInterface $mock) {
            $mock->shouldReceive('handleInvoicePayment')->once()->andReturnFalse();
        });
        $purchase = CompanySlotPurchase::create([
            'user_id' => $user->id, 'slots' => 5, 'price_sats' => 120000,
            'btcpay_invoice_id' => 'pack-invoice', 'status' => 'pending',
        ]);
        $this->deliver([
            'storeId' => 'billing-store', 'invoiceId' => 'pack-invoice',
            'type' => 'InvoiceSettled', 'deliveryId' => 'payment-delivery',
        ])->assertOk();
        Http::fake([
            '*' => Http::response([
                'id' => 'pack-invoice', 'status' => 'Settled',
                'metadata' => ['purpose' => 'company_slot_pack', 'userId' => (string) $user->id],
            ]),
        ]);
        $service = app(CompanySlotService::class);
        $fail = true;
        $this->mock(CompanySlotService::class, function (MockInterface $mock) use ($service, &$fail) {
            $mock->shouldReceive('fulfillPaidInvoice')->twice()->andReturnUsing(function (...$args) use ($service, &$fail) {
                $result = $service->fulfillPaidInvoice(...$args);
                if ($fail) {
                    $fail = false;
                    throw new \RuntimeException('Synthetic failure after purchase update');
                }

                return $result;
            });
        });
        $event = WebhookEvent::where('delivery_id', 'payment-delivery')->firstOrFail();
        $job = new ProcessBtcPayWebhook($event);
        try {
            $job->handle();
            $this->fail('Fulfillment failures must remain retryable.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic failure after purchase update', $e->getMessage());
        }
        $this->assertNull($event->fresh()->processed_at);
        $this->assertSame('pending', $purchase->fresh()->status);
        $this->assertSame(0, $service->paidSlotCount($user));
        Queue::assertNotPushed(VerifyWalletConfig::class);
        Queue::assertNotPushed(SyncInvoiceSettlements::class);

        $job->handle();
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertSame(5, $service->paidSlotCount($user));
        Queue::assertPushed(VerifyWalletConfig::class, 1);
        Queue::assertPushed(SyncInvoiceSettlements::class, 1);
    }

    private function deliver(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/webhooks/btcpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BTCPAY-SIG' => 'sha256='.hash_hmac('sha256', $body, 'synthetic-webhook-secret'),
        ], $body);
    }

    private function assertPurchasedQuantity(string $purpose, User $user): void
    {
        if ($purpose === 'company_slot_pack') {
            $this->assertSame(5, app(CompanySlotService::class)->paidSlotCount($user));
        } else {
            $this->assertSame(25, (int) ExpenseIsdocCreditBalance::where('user_id', $user->id)->value('balance'));
        }
    }
}
