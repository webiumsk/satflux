<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\BankInboundEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankInboundEmailTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected BankInboundAddressService $addressService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bank_inbound.enabled' => true,
            'bank_inbound.webhook_secret' => 'test-inbound-secret',
            'bank_inbound.domain' => 'payments.satflux.io',
            'bank_inbound.address_prefix' => 'pay',
            'bank_inbound.max_address_length' => 50,
            'bank_inbound.reject_forwarded' => true,
        ]);

        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'bank_inbound_token' => 'abc123def456',
        ]);
        $this->addressService = app(BankInboundAddressService::class);
    }

    #[Test]
    public function inbound_email_address_is_at_most_fifty_characters_and_stable(): void
    {
        $first = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/inbound-email",
        );
        $first->assertOk();
        $address = $first->json('data.address');
        $this->assertSame('payabc123def456@payments.satflux.io', $address);
        $this->assertLessThanOrEqual(50, strlen($address));
        $first->assertJsonPath('data.length', strlen($address));
        $first->assertJsonPath('data.max_length', 50);
        $first->assertJsonPath('data.enabled', true);

        $second = $this->actingAs($this->user)->getJson(
            "/api/invoicing/companies/{$this->company->id}/bank-transactions/inbound-email",
        );
        $second->assertOk();
        $this->assertSame($address, $second->json('data.address'));
    }

    #[Test]
    public function webhook_imports_tatra_bank_notification_and_auto_matches_invoice(): void
    {
        BusinessDocument::create([
            'company_id' => $this->company->id,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260042',
            'variable_symbol' => '20260042',
            'currency' => 'EUR',
            'total' => 150.50,
            'issue_date' => now(),
        ]);

        $address = $this->addressService->buildAddress($this->company);
        $body = 'Obrat na ucte. Suma: 150,50 EUR. VS: 20260042. Protistrana: Client s.r.o. 01.06.2026';

        $response = $this->postJson('/api/webhooks/bank-inbound', [
            'to' => $address,
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat na ucte',
            'body' => $body,
        ], [
            'X-Bank-Inbound-Secret' => 'test-inbound-secret',
        ]);

        $response->assertOk();
        $response->assertJsonPath('accepted', true);

        $doc = BusinessDocument::first();
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->status);
        $this->assertDatabaseCount('bank_transactions', 1);
    }

    #[Test]
    public function unknown_inbound_address_is_rejected(): void
    {
        $service = app(BankInboundEmailService::class);

        $this->expectException(ValidationException::class);

        $service->handle([
            'to' => 'payunknown0000@payments.satflux.io',
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat',
            'body' => 'VS: 123',
        ]);
    }

    #[Test]
    public function forwarded_bank_notification_is_rejected(): void
    {
        $service = app(BankInboundEmailService::class);
        $address = $this->addressService->buildAddress($this->company);

        $this->expectException(ValidationException::class);

        $service->handle([
            'to' => $address,
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat',
            'body' => 'VS: 123',
            'headers' => "X-Forwarded-For: 1.2.3.4\r\n",
        ]);
    }

    #[Test]
    public function webhook_requires_valid_secret_when_configured(): void
    {
        $address = $this->addressService->buildAddress($this->company);

        $this->postJson('/api/webhooks/bank-inbound', [
            'to' => $address,
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat',
            'body' => 'VS: 123',
        ])->assertForbidden();
    }

    #[Test]
    public function webhook_requires_secret_to_be_configured_for_native_payloads(): void
    {
        config(['bank_inbound.webhook_secret' => null]);

        $address = $this->addressService->buildAddress($this->company);

        $this->postJson('/api/webhooks/bank-inbound', [
            'to' => $address,
            'from' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat',
            'body' => 'Suma: 150,50 EUR. VS: 20260042.',
        ])->assertStatus(503);

        $this->assertDatabaseCount('bank_transactions', 0);
    }

    #[Test]
    public function mailgun_webhook_imports_tatra_bank_notification_and_auto_matches_invoice(): void
    {
        config(['bank_inbound.mailgun_webhook_signing_key' => 'mailgun-test-signing-key']);

        BusinessDocument::create([
            'company_id' => $this->company->id,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260042',
            'variable_symbol' => '20260042',
            'currency' => 'EUR',
            'total' => 150.50,
            'issue_date' => now(),
        ]);

        $address = $this->addressService->buildAddress($this->company);
        $body = 'Obrat na ucte. Suma: 150,50 EUR. VS: 20260042. Protistrana: Client s.r.o. 01.06.2026';

        $response = $this->post('/api/webhooks/bank-inbound', $this->mailgunSignedPayload([
            'recipient' => $address,
            'sender' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat na ucte',
            'stripped-text' => $body,
        ]));

        $response->assertOk();
        $response->assertJsonPath('accepted', true);

        $doc = BusinessDocument::first();
        $this->assertSame(BusinessDocumentStatus::Paid, $doc->status);
        $this->assertDatabaseCount('bank_transactions', 1);
    }

    #[Test]
    public function mailgun_webhook_rejects_invalid_signature(): void
    {
        config(['bank_inbound.mailgun_webhook_signing_key' => 'mailgun-test-signing-key']);

        $address = $this->addressService->buildAddress($this->company);

        $this->post('/api/webhooks/bank-inbound', $this->mailgunSignedPayload([
            'recipient' => $address,
            'sender' => 'notify@tatrabanka.sk',
            'subject' => 'Obrat',
            'stripped-text' => 'VS: 123',
        ], signature: 'invalid'))->assertForbidden();
    }

    #[Test]
    public function mailgun_webhook_rejects_stale_timestamp(): void
    {
        config(['bank_inbound.mailgun_webhook_signing_key' => 'mailgun-test-signing-key']);

        $address = $this->addressService->buildAddress($this->company);
        $timestamp = (string) (time() - 600);
        $token = 'mailgun-stale-token';
        $signature = hash_hmac('sha256', $timestamp.$token, 'mailgun-test-signing-key');

        $this->post('/api/webhooks/bank-inbound', [
            'recipient' => $address,
            'sender' => 'notify@tatrabanka.sk',
            'stripped-text' => 'VS: 123',
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
        ])->assertForbidden();
    }

    #[Test]
    public function mailgun_webhook_rejects_replayed_token(): void
    {
        config(['bank_inbound.mailgun_webhook_signing_key' => 'mailgun-test-signing-key']);

        $address = $this->addressService->buildAddress($this->company);
        $payload = $this->mailgunSignedPayload([
            'recipient' => $address,
            'sender' => 'notify@tatrabanka.sk',
            'stripped-text' => 'VS: 123',
        ], token: 'mailgun-replay-token');

        $this->post('/api/webhooks/bank-inbound', $payload)->assertOk();
        $this->post('/api/webhooks/bank-inbound', $payload)->assertForbidden();
    }

    #[Test]
    public function mailgun_webhook_requires_signing_key_when_configured(): void
    {
        config(['bank_inbound.mailgun_webhook_signing_key' => null]);

        $address = $this->addressService->buildAddress($this->company);

        $this->post('/api/webhooks/bank-inbound', $this->mailgunSignedPayload([
            'recipient' => $address,
            'sender' => 'notify@tatrabanka.sk',
            'stripped-text' => 'VS: 123',
        ], signingKey: 'unused'))->assertStatus(503);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mailgunSignedPayload(
        array $payload,
        string $signingKey = 'mailgun-test-signing-key',
        ?string $token = null,
        int|string|null $timestamp = null,
        ?string $signature = null,
    ): array {
        $timestamp = (string) ($timestamp ?? time());
        $token ??= 'mailgun-test-token-'.uniqid('', true);
        $signature ??= hash_hmac('sha256', $timestamp.$token, $signingKey);

        return array_merge($payload, [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
        ]);
    }
}
