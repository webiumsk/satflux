<?php

namespace Tests\Feature;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentPaymentIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentIntentControllerReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private Company $company;

    private BusinessDocumentPaymentIntentService $intents;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.public_url' => 'https://btcpay.example']);
        $plan = SubscriptionPlan::create(['code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3, 'max_api_keys' => 3,
            'features' => ['business_invoicing'], 'is_active' => true]);
        $this->owner = User::factory()->create(['btcpay_api_key' => 'synthetic-merchant-key']);
        Subscription::create(['user_id' => $this->owner->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addYear()]);
        $this->company = Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Synthetic issuer',
            'jurisdiction' => 'eu_sk', 'default_currency' => 'EUR']);
        $this->store = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
        $this->intents = app(BusinessDocumentPaymentIntentService::class);
    }

    private function document(float $amountPaid = 0): BusinessDocument
    {
        $document = new BusinessDocument(['company_id' => $this->company->id, 'store_id' => $this->store->id,
            'type' => 'invoice', 'status' => 'issued', 'number' => 'REVIEW-1', 'total' => '100.00',
            'currency' => 'EUR', 'amount_paid' => $amountPaid, 'payment_btc_enabled' => true]);
        $document->setRelation('company', $this->company);
        $document->setRelation('store', $this->store);

        return $document;
    }

    private function invoice(string $id = 'review-invoice', string $status = 'New', string $amount = '100.00'): array
    {
        return ['id' => $id, 'status' => $status, 'amount' => $amount, 'currency' => 'EUR',
            'paidAmount' => in_array($status, ['Settled', 'Processing'], true) ? $amount : '0',
            'additionalStatus' => 'None', 'checkoutLink' => 'https://btcpay.example/i/'.$id];
    }

    private function payload(float $total = 100, string $currency = 'EUR', float $amountPaid = 0): array
    {
        return ['company' => ['legal_name' => 'Synthetic issuer', 'country' => 'SK',
            'default_currency' => 'EUR', 'jurisdiction' => 'eu_sk'],
            'contact' => ['name' => 'Synthetic customer'],
            'document' => ['type' => 'invoice', 'status' => 'issued', 'number' => 'REVIEW-1',
                'issue_date' => now()->toDateString(), 'currency' => $currency, 'discount_percent' => 0,
                'amount_paid' => $amountPaid, 'payment_btc_enabled' => true, 'pdf_locale' => 'sk'],
            'lines' => [['name' => 'Service', 'quantity' => 1, 'unit' => 'h', 'unit_price' => $total, 'tax_rate' => 0]],
            'store_id' => $this->store->id, 'evolu_document_id' => 'review-document'];
    }

    #[Test]
    public function an_active_offer_can_retry_a_transient_provider_read_without_a_second_creation(): void
    {
        $outage = false;
        $posts = 0;
        Http::fake(function ($request) use (&$outage, &$posts) {
            if ($request->method() === 'POST') {
                $posts++;
            } elseif ($outage) {
                return Http::response([], 503);
            }

            return Http::response($this->invoice());
        });
        $intent = $this->intents->prepare($this->document(), $this->store, 'review-document');
        $this->intents->activate($intent);
        $outage = true;
        $url = '/pay/e/'.$intent->token;
        $this->post($url)->assertOk()->assertViewHas('state', 'ready')->assertSee('method="POST"', false);
        $this->assertSame('active', $intent->fresh()->state);
        $this->assertSame(1, $posts);
        $outage = false;
        $this->post($url)->assertStatus(303)->assertRedirect('https://btcpay.example/i/review-invoice');
        $this->assertSame(1, $posts);
    }

    #[Test]
    public function an_uncertain_post_keeps_the_offer_unavailable_without_retrying_creation(): void
    {
        $posts = 0;
        Http::fake(function () use (&$posts) {
            $posts++;
            throw new ConnectionException('Synthetic ambiguous creation.');
        });
        $intent = $this->intents->prepare($this->document(), $this->store, 'review-document');
        $this->post('/pay/e/'.$intent->token)->assertOk()->assertViewHas('state', 'unavailable')
            ->assertDontSee('method="POST"', false);
        $this->post('/pay/e/'.$intent->token)->assertOk()->assertViewHas('state', 'unavailable');
        $this->assertSame('unknown', $intent->fresh()->state);
        $this->assertSame(1, $posts);
    }

    #[Test]
    public function existing_lookup_returns_the_actual_historical_settlement_id(): void
    {
        $settled = false;
        $posts = 0;
        Http::fake(function ($request) use (&$settled, &$posts) {
            if ($request->method() === 'POST') {
                return Http::response($this->invoice('attempt-'.++$posts));
            }
            if (str_ends_with($request->url(), '/attempt-1')) {
                return Http::response($this->invoice('attempt-1', $settled ? 'Settled' : 'Expired'));
            }

            return Http::response($this->invoice('attempt-2'));
        });
        $intent = $this->intents->prepare($this->document(), $this->store, 'review-document');
        $this->intents->activate($intent);
        $this->intents->activate($intent->fresh());
        $settled = true;
        $this->actingAs($this->owner)->postJson('/api/invoicing/ephemeral/btcpay-checkout/existing', $this->payload())
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.btcpay_invoice_id', 'attempt-1');
        $this->assertSame('attempt-2', $intent->fresh()->btcpay_invoice_id);
        $this->assertSame('attempt-1', $intent->fresh()->settled_invoice_id);
        $this->assertSame(2, $posts);
    }

    public static function checkoutLookupCases(): array
    {
        return [
            'matching unpaid offer' => [100, 'EUR', 0, 'New', true],
            'matching remaining bank balance' => [100, 'EUR', 25, 'New', true],
            'processing remains reusable' => [100, 'EUR', 0, 'Processing', true],
            'changed amount' => [200, 'EUR', 0, 'New', false],
            'changed currency' => [100, 'USD', 0, 'New', false],
            'unknown provider state' => [100, 'EUR', 0, 'Mystery', false],
            'expired offer' => [100, 'EUR', 0, 'Expired', false],
        ];
    }

    #[Test]
    #[DataProvider('checkoutLookupCases')]
    public function existing_lookup_requires_matching_outstanding_currency_and_payability(
        float $total,
        string $currency,
        float $amountPaid,
        string $providerState,
        bool $expectedLink,
    ): void {
        $due = number_format(100 - $amountPaid, 2, '.', '');
        $posts = 0;
        Http::fake(function ($request) use ($providerState, $due, &$posts) {
            if ($request->method() === 'POST') {
                $posts++;
            }

            return Http::response($this->invoice('review-invoice', $providerState, $due));
        });
        $intent = $this->intents->prepare($this->document($amountPaid), $this->store, 'review-document');
        $this->intents->activate($intent);
        $response = $this->actingAs($this->owner)->postJson('/api/invoicing/ephemeral/btcpay-checkout/existing',
            $this->payload($total, $currency, $amountPaid))->assertOk();
        if ($expectedLink) {
            $response->assertJsonPath('data.checkout_link', 'https://btcpay.example/i/review-invoice');
        } else {
            $response->assertJsonPath('data', null);
        }
        $this->assertSame(1, $posts);
    }
}
