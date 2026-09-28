<?php

namespace Tests\Feature;

use App\Mail\StoreInvoiceEmail;
use App\Models\Store;
use App\Models\StoreEmailRule;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\StoreEmailRuleDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StoreEmailRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_email_rules(): void
    {
        $store = Store::factory()->create();

        $this->getJson("/api/stores/{$store->id}/email-rules")->assertUnauthorized();
    }

    public function test_owner_can_crud_email_rules(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/email-rules/triggers")->assertOk();

        $create = $this->actingAs($user)->postJson("/api/stores/{$store->id}/email-rules", [
            'trigger' => 'InvoiceSettled',
            'condition' => null,
            'to_addresses' => 'merchant@example.com',
            'cc_addresses' => '',
            'bcc_addresses' => '',
            'send_to_buyer' => false,
            'subject' => 'Paid {Invoice.Id}',
            'body' => '<p>Done {Invoice.Id}</p>',
            'sort_order' => 0,
        ]);
        $create->assertCreated();
        $ruleId = $create->json('data.id');
        $this->assertNotEmpty($ruleId);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/email-rules")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/email-rules/{$ruleId}", [
            'trigger' => 'InvoiceSettled',
            'to_addresses' => 'a@example.com,b@example.com',
            'subject' => 'S',
            'body' => '<p>B</p>',
        ])->assertOk();

        $this->actingAs($user)->deleteJson("/api/stores/{$store->id}/email-rules/{$ruleId}")
            ->assertOk();

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/email-rules")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_trigger_must_be_invoice_event(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson("/api/stores/{$store->id}/email-rules", [
            'trigger' => 'PayoutCreated',
            'to_addresses' => 'x@example.com',
            'subject' => 'S',
            'body' => 'B',
        ])->assertStatus(422);
    }

    public function test_dispatcher_sends_mailable_on_matching_webhook(): void
    {
        Mail::fake();
        Cache::flush();

        config(['services.btcpay.base_url' => 'https://btcpay.test']);

        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'btcpay-store-x',
        ]);

        StoreEmailRule::query()->create([
            'store_id' => $store->id,
            'trigger' => 'InvoiceSettled',
            'condition' => null,
            'to_addresses' => 'notify@example.com',
            'cc_addresses' => null,
            'bcc_addresses' => null,
            'send_to_buyer' => true,
            'subject' => 'Invoice {Invoice.Id} settled',
            'body' => '<p>Order {Invoice.OrderId}</p>',
            'sort_order' => 0,
        ]);

        Http::fake([
            'https://btcpay.test/api/v1/stores/btcpay-store-x/invoices/inv-abc' => Http::response([
                'id' => 'inv-abc',
                'orderId' => 'ord-1',
                'status' => 'Settled',
                'amount' => '10',
                'currency' => 'EUR',
                'checkoutLink' => 'https://checkout.example/i/inv-abc',
                'metadata' => [
                    'buyerEmail' => 'buyer@example.com',
                ],
            ], 200),
        ]);

        $webhookEvent = WebhookEvent::create([
            'store_id' => $store->id,
            'event_type' => 'InvoiceSettled',
            'payload' => [
                'type' => 'InvoiceSettled',
                'storeId' => 'btcpay-store-x',
                'invoiceId' => 'inv-abc',
                'deliveryId' => 'del-1',
            ],
            'verified' => true,
        ]);

        app(StoreEmailRuleDispatcher::class)->dispatchForWebhook($webhookEvent, $store);

        Mail::assertSent(StoreInvoiceEmail::class, function (StoreInvoiceEmail $mail) {
            return str_contains($mail->subjectLine, 'inv-abc')
                && str_contains($mail->htmlBody, 'ord-1');
        });
    }

    public function test_idempotent_dispatch_key_prevents_duplicate_mail(): void
    {
        Mail::fake();
        Cache::flush();

        config(['services.btcpay.base_url' => 'https://btcpay.test']);

        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'btcpay_store_id' => 'btcpay-store-x',
        ]);

        StoreEmailRule::query()->create([
            'store_id' => $store->id,
            'trigger' => 'InvoiceSettled',
            'condition' => null,
            'to_addresses' => 'notify@example.com',
            'cc_addresses' => null,
            'bcc_addresses' => null,
            'send_to_buyer' => false,
            'subject' => 'Invoice {Invoice.Id} settled',
            'body' => '<p>Hi</p>',
            'sort_order' => 0,
        ]);

        Http::fake([
            'https://btcpay.test/api/v1/stores/btcpay-store-x/invoices/inv-abc' => Http::response([
                'id' => 'inv-abc',
                'status' => 'Settled',
                'checkoutLink' => 'https://checkout.example/i/inv-abc',
                'metadata' => [],
            ], 200),
        ]);

        $payload = [
            'storeId' => 'btcpay-store-x',
            'invoiceId' => 'inv-abc',
            'deliveryId' => 'del-same',
        ];

        $e1 = WebhookEvent::create([
            'store_id' => $store->id,
            'event_type' => 'InvoiceSettled',
            'payload' => $payload,
            'verified' => true,
        ]);
        $e2 = WebhookEvent::create([
            'store_id' => $store->id,
            'event_type' => 'InvoiceSettled',
            'payload' => $payload,
            'verified' => true,
        ]);

        app(StoreEmailRuleDispatcher::class)->dispatchForWebhook($e1, $store);
        app(StoreEmailRuleDispatcher::class)->dispatchForWebhook($e2, $store);

        Mail::assertSent(StoreInvoiceEmail::class, 1);
    }

    public function test_rule_rejects_more_than_ten_recipients_per_field(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $many = implode(',', array_map(fn (int $i) => "r{$i}@example.com", range(1, 11)));

        $this->actingAs($user)->postJson("/api/stores/{$store->id}/email-rules", [
            'trigger' => 'InvoiceSettled',
            'to_addresses' => 'merchant@example.com',
            'bcc_addresses' => $many,
            'subject' => 'S',
            'body' => 'B',
        ])->assertStatus(422)->assertJsonValidationErrors('bcc_addresses');
    }

    public function test_buyer_controlled_values_are_escaped_and_cannot_fan_out(): void
    {
        Mail::fake();
        Cache::flush();
        config(['services.btcpay.base_url' => 'https://btcpay.test']);

        $user = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create(['user_id' => $user->id, 'btcpay_store_id' => 'btcpay-store-x']);

        StoreEmailRule::query()->create([
            'store_id' => $store->id,
            'trigger' => 'InvoiceSettled',
            'to_addresses' => 'notify@example.com',
            'cc_addresses' => '{Invoice.Metadata.ccList}',
            'send_to_buyer' => false,
            'subject' => 'Order {Invoice.OrderId}',
            'body' => '<p>Order {Invoice.OrderId}</p>',
            'sort_order' => 0,
        ]);
        StoreEmailRule::query()->create([
            'store_id' => $store->id,
            'trigger' => 'InvoiceSettled',
            'to_addresses' => 'notify@example.com',
            'send_to_buyer' => false,
            'subject' => 'Plain',
            'body' => '<p>Order {Invoice.OrderId}</p>',
            'sort_order' => 1,
        ]);

        $fanOut = implode(',', array_map(fn (int $i) => "victim{$i}@example.com", range(1, 50)));
        Http::fake([
            'https://btcpay.test/api/v1/stores/btcpay-store-x/invoices/inv-abc' => Http::response([
                'id' => 'inv-abc',
                'orderId' => "<a href=\"https://phish.example\">Claim</a>\r\nBcc: x@evil.example",
                'status' => 'Settled',
                'metadata' => ['ccList' => $fanOut],
            ], 200),
        ]);

        $webhookEvent = WebhookEvent::create([
            'store_id' => $store->id,
            'event_type' => 'InvoiceSettled',
            'payload' => ['type' => 'InvoiceSettled', 'storeId' => 'btcpay-store-x', 'invoiceId' => 'inv-abc', 'deliveryId' => 'del-9'],
            'verified' => true,
        ]);

        app(StoreEmailRuleDispatcher::class)->dispatchForWebhook($webhookEvent, $store);

        // The fan-out rule is skipped entirely; the plain rule is sent escaped.
        Mail::assertSent(StoreInvoiceEmail::class, 1);
        Mail::assertSent(StoreInvoiceEmail::class, function (StoreInvoiceEmail $mail) {
            return $mail->subjectLine === 'Plain'
                && ! str_contains($mail->htmlBody, '<a href')
                && str_contains($mail->htmlBody, '&lt;a href');
        });
    }
}
