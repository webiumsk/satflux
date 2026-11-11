<?php

namespace Tests\Feature;

use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\CompanyMemberRole;
use App\Enums\ComplianceProvider;
use App\Enums\ComplianceSubmissionStatus;
use App\Enums\IntegrationDocumentInboxStatus;
use App\Models\BankTransaction;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\EfakturaInboundReceipt;
use App\Models\EphemeralEfakturaSubmission;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentEmailService;
use App\Services\Invoicing\CompanyActionCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class CompanyActionCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3,
            'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->owner = User::factory()->create();
        Subscription::create([
            'user_id' => $this->owner->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        $this->company = Company::create([
            'user_id' => $this->owner->id, 'legal_name' => 'Synthetic action center company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function guests_and_other_companies_cannot_read_the_projection(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->url())->assertForbidden();
    }

    #[Test]
    public function an_empty_company_has_checked_sources_without_triggering_provider_requests(): void
    {
        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $response->assertJsonCount(4, 'data.categories')->assertJsonPath('data.restricted_sources', []);
        $this->assertNotEmpty($response->json('data.checked_at'));
        foreach ($response->json('data.categories') as $category) {
            $this->assertSame($category['key'] === 'synchronization' ? null : 0, $category['count']);
            $this->assertSame([], $category['items']);
            $this->assertFalse($category['has_more']);
            $this->assertSame($category['key'] === 'synchronization' ? 'not_configured' : 'ready', $category['state']);
        }
        $fixture = json_decode(file_get_contents(resource_path('js/__tests__/fixtures/action-center-empty.json')), true, flags: JSON_THROW_ON_ERROR);
        $actual = $response->json('data');
        $actual['checked_at'] = $fixture['checked_at'];
        $this->assertSame($fixture, $actual);
        Http::assertNothingSent();
    }

    #[Test]
    public function unmatched_payments_preserve_signed_amounts_and_opaque_dedupe_without_bank_notes(): void
    {
        $negative = $this->bank(['amount' => '-19.25', 'direction' => BankTransactionDirection::Debit,
            'counterparty_name' => 'private-counterparty', 'reference' => 'private-bank-note']);
        $this->bank(['match_status' => BankTransactionMatchStatus::Matched]);
        $this->bank(['match_status' => BankTransactionMatchStatus::Ignored]);
        $this->bank(['reference' => 'Stav na účte']);
        $foreign = $this->otherCompany();
        $this->bank(['company_id' => $foreign->id]);

        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $category = $this->category($response->json('data'), 'unmatched_payments');
        $this->assertSame(1, $category['count']);
        $this->assertSame('bank:'.$negative->id, $category['items'][0]['id']);
        $this->assertSame('-19.25', $category['items'][0]['amount']);
        $this->assertSame('EUR', $category['items'][0]['currency']);
        $this->assertSame($negative->dedupe_hash, $category['items'][0]['bank_dedupe_hash']);
        $this->assertNull($category['items'][0]['reference']);
        $this->assertStringNotContainsString('private-counterparty', $response->getContent());
        $this->assertStringNotContainsString('private-bank-note', $response->getContent());
        $this->assertSame(BankTransactionMatchStatus::Unmatched, $negative->fresh()->match_status);
    }

    #[Test]
    public function exact_counts_are_returned_while_items_are_bounded_to_twenty_five(): void
    {
        foreach (range(1, 32) as $i) {
            $this->bank(['booked_at' => now()->subMinutes($i)]);
        }
        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $category = $this->category($response->json('data'), 'unmatched_payments');
        $this->assertSame(32, $category['count']);
        $this->assertCount(25, $category['items']);
        $this->assertTrue($category['has_more']);
        $this->assertCount(25, array_unique(array_column($category['items'], 'id')));
    }

    #[Test]
    public function integration_items_require_active_current_company_links_and_hide_payloads(): void
    {
        $current = $this->integration();
        $pending = $this->inbox($current, ['number' => 'W-42', 'email_error' => 'private-provider-error',
            'buyer' => ['email' => 'private@example.invalid'], 'token' => 'private-fixture-token']);
        $sent = $this->inbox($current, ['number' => 'W-43', 'email_error' => 'old-error', 'emailed_at' => now()->toIso8601String()]);
        $this->inbox($current)->update(['status' => IntegrationDocumentInboxStatus::Dismissed]);
        $inactive = $this->integration();
        $this->inbox($inactive);
        $inactive->update(['is_active' => false]);
        $stale = $this->integration();
        $this->inbox($stale, ['email_error' => 'stale-error']);
        $stale->store->update(['company_id' => $this->otherCompany()->id]);

        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $incoming = $this->category($response->json('data'), 'incoming_documents');
        $failed = $this->category($response->json('data'), 'failed_sends');
        $this->assertSame(2, $incoming['count']);
        $this->assertSame(1, $failed['count']);
        $this->assertSame('email:integration:'.$pending->id, $failed['items'][0]['id']);
        $this->assertSame('W-42', $failed['items'][0]['reference']);
        foreach (['private-provider-error', 'private@example.invalid', 'private-fixture-token', 'stale-error'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->assertDatabaseHas('integration_document_inbox', ['id' => $pending->id, 'status' => 'pending']);
        $this->assertDatabaseHas('integration_document_inbox', ['id' => $sent->id, 'status' => 'pending']);
    }

    #[Test]
    public function incoming_efaktura_and_failed_submissions_are_scoped_without_originals_or_provider_text(): void
    {
        $receipt = EfakturaInboundReceipt::create([
            'company_id' => $this->company->id, 'external_document_id' => 'synthetic-receipt',
            'inbox_status' => EfakturaInboundReceipt::INBOX_PENDING,
            'external_number' => 'IN-42', 'total' => '85.20', 'currency' => 'EUR',
            'draft_json' => ['title' => 'private-received-payload'], 'ubl_encrypted' => 'private-original',
        ]);
        $document = $this->document();
        $compliance = BusinessDocumentCompliance::create([
            'business_document_id' => $document->id, 'provider' => ComplianceProvider::Peppol,
            'status' => ComplianceSubmissionStatus::Rejected, 'response_payload' => ['secret' => 'private-provider-payload'],
        ]);
        $local = EphemeralEfakturaSubmission::create([
            'user_id' => $this->owner->id, 'bridge_company_id' => $this->company->id,
            'evolu_document_id' => 'local-document-42', 'provider' => 'peppol', 'status' => 'failed',
            'message' => 'private-provider-message',
        ]);
        $foreign = EphemeralEfakturaSubmission::create([
            'user_id' => User::factory()->create()->id, 'bridge_company_id' => $this->company->id,
            'evolu_document_id' => 'foreign-local-document', 'provider' => 'peppol', 'status' => 'failed',
        ]);

        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $incoming = $this->category($response->json('data'), 'incoming_documents');
        $failed = $this->category($response->json('data'), 'failed_sends');
        $this->assertSame(1, $incoming['count']);
        $this->assertSame('incoming:efaktura:'.$receipt->id, $incoming['items'][0]['id']);
        $this->assertSame('85.20', $incoming['items'][0]['amount']);
        $this->assertSame(2, $failed['count']);
        $this->assertEqualsCanonicalizing(['efaktura:legacy:'.$compliance->id, 'efaktura:local:'.$local->id], array_column($failed['items'], 'id'));
        foreach (['private-received-payload', 'private-original', 'private-provider-payload', 'private-provider-message', $foreign->id] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->assertSame(EfakturaInboundReceipt::INBOX_PENDING, $receipt->fresh()->inbox_status);
    }

    #[Test]
    public function members_keep_ordinary_company_access_but_not_owner_only_efaktura_visibility(): void
    {
        $member = User::factory()->create();
        $membership = $this->member($member);
        $this->bank();
        EfakturaInboundReceipt::create([
            'company_id' => $this->company->id, 'external_document_id' => 'owner-only-receipt',
            'inbox_status' => EfakturaInboundReceipt::INBOX_PENDING, 'external_number' => 'owner-only-number',
        ]);
        $response = $this->actingAs($member)->getJson($this->url())->assertOk();
        $this->assertSame(1, $this->category($response->json('data'), 'unmatched_payments')['count']);
        $this->assertSame(0, $this->category($response->json('data'), 'incoming_documents')['count']);
        $response->assertJsonPath('data.restricted_sources', ['efaktura_owner_required']);
        $this->assertStringNotContainsString('owner-only-number', $response->getContent());
        $membership->update(['revoked_at' => now()]);
        $this->actingAs($member)->getJson($this->url())->assertForbidden();
    }

    #[Test]
    public function direct_projection_rechecks_stale_membership_and_preserves_support_access(): void
    {
        $member = User::factory()->create();
        $membership = $this->member($member);
        $this->company->load('members');
        $membership->update(['revoked_at' => now()]);
        try {
            app(CompanyActionCenterService::class)->forCompany($this->company, $member);
            $this->fail('A stale preloaded membership must not authorize this request');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->actingAs(User::factory()->create(['role' => 'support']))
            ->getJson($this->url())->assertOk()->assertJsonPath('data.restricted_sources', []);
    }

    #[Test]
    public function a_source_failure_is_unknown_instead_of_empty_and_does_not_hide_other_categories(): void
    {
        $service = \Mockery::mock(CompanyActionCenterService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('unmatchedPayments')->once()->andThrow(new \RuntimeException('private-query-diagnostic'));
        $this->instance(CompanyActionCenterService::class, $service);
        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $failed = $this->category($response->json('data'), 'unmatched_payments');
        $this->assertSame('unavailable', $failed['state']);
        $this->assertNull($failed['count']);
        $this->assertSame([], $failed['items']);
        $this->assertSame('ready', $this->category($response->json('data'), 'incoming_documents')['state']);
        $this->assertStringNotContainsString('private-query-diagnostic', $response->getContent());
    }

    #[Test]
    public function wise_reports_refresh_due_without_claiming_a_failure_and_unknown_credentials_are_unavailable(): void
    {
        $this->company->update(['app_settings' => ['wise_api_token_encrypted' => Crypt::encryptString('synthetic-test-token')]]);
        $response = $this->actingAs($this->owner)->getJson($this->url())->assertOk();
        $sync = $this->category($response->json('data'), 'synchronization');
        $this->assertSame('ready', $sync['state']);
        $this->assertSame(1, $sync['count']);
        $this->assertSame('wise_sync_due', $sync['items'][0]['kind']);
        $this->assertNull($sync['items'][0]['occurred_at']);
        $this->assertStringNotContainsString('synthetic-test-token', $response->getContent());

        $this->company->update(['app_settings' => [
            'wise_api_token_encrypted' => Crypt::encryptString('synthetic-test-token'),
            'wise_last_sync_at' => now()->subHour()->toIso8601String(),
        ]]);
        $response = $this->getJson($this->url())->assertOk();
        $this->assertSame(0, $this->category($response->json('data'), 'synchronization')['count']);
        $this->company->update(['app_settings' => [
            'wise_api_token_encrypted' => Crypt::encryptString('synthetic-test-token'),
            'wise_last_sync_at' => now()->subDays(2)->toIso8601String(),
        ]]);
        $response = $this->getJson($this->url())->assertOk();
        $this->assertSame(1, $this->category($response->json('data'), 'synchronization')['count']);

        $this->company->update(['app_settings' => ['wise_api_token_encrypted' => 'not-decryptable']]);
        $response = $this->getJson($this->url())->assertOk();
        $this->assertSame('unavailable', $this->category($response->json('data'), 'synchronization')['state']);
        $this->assertNull($this->category($response->json('data'), 'synchronization')['count']);
        Http::assertNothingSent();
    }

    #[Test]
    public function legacy_transport_failure_is_sanitized_persisted_and_cleared_only_after_success(): void
    {
        $document = $this->document();
        $email = \Mockery::mock(BusinessDocumentEmailService::class);
        $email->shouldReceive('send')->once()->ordered()->andThrow(new TransportException('private-smtp-credential diagnostic'));
        $email->shouldReceive('send')->once()->ordered()->andReturn(['sent_to' => ['customer@example.invalid']]);
        $this->instance(BusinessDocumentEmailService::class, $email);
        $response = $this->actingAs($this->owner)->postJson($this->emailUrl($document), ['to' => ['customer@example.invalid']]);
        $response->assertUnprocessable()->assertJsonPath('message', 'Email could not be sent.');
        $this->assertStringNotContainsString('private-smtp', $response->getContent());
        $this->assertNotNull($document->fresh()->email_failed_at);
        $this->assertSame('transport_error', $document->fresh()->email_failure_code);
        $this->assertArrayNotHasKey('email_failure_code', $document->fresh()->toArray());
        $projection = $this->getJson($this->url())->assertOk();
        $failed = $this->category($projection->json('data'), 'failed_sends');
        $this->assertSame(1, $failed['count']);
        $this->assertSame('legacy_email_failed', $failed['items'][0]['kind']);
        $this->assertSame($document->id, $failed['items'][0]['document_id']);

        $this->postJson($this->emailUrl($document), ['to' => ['customer@example.invalid']])->assertOk();
        $this->assertNull($document->fresh()->email_failed_at);
        $this->assertNull($document->fresh()->email_failure_code);
        $projection = $this->getJson($this->url())->assertOk();
        $this->assertSame(0, $this->category($projection->json('data'), 'failed_sends')['count']);
        $this->assertSame(BusinessDocumentStatus::Issued, $document->fresh()->status);
    }

    private function url(): string
    {
        return "/api/invoicing/companies/{$this->company->id}/action-center";
    }

    private function emailUrl(BusinessDocument $document): string
    {
        return "/api/invoicing/companies/{$this->company->id}/documents/{$document->id}/send-email";
    }

    private function otherCompany(): Company
    {
        return Company::create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Other synthetic company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
        ]);
    }

    private function bank(array $overrides = []): BankTransaction
    {
        return BankTransaction::create(array_merge([
            'company_id' => $this->company->id, 'booked_at' => now(), 'amount' => '25.00', 'currency' => 'EUR',
            'direction' => BankTransactionDirection::Credit, 'match_status' => BankTransactionMatchStatus::Unmatched,
            'source' => 'csv', 'dedupe_hash' => hash('sha256', (string) Str::uuid()),
        ], $overrides));
    }

    private function integration(): StoreIntegration
    {
        $store = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);

        return StoreIntegration::createForStore($store)['integration'];
    }

    private function inbox(StoreIntegration $integration, array $payload = []): IntegrationDocumentInbox
    {
        return IntegrationDocumentInbox::create([
            'store_integration_id' => $integration->id, 'evolu_document_id' => (string) Str::uuid(),
            'payload_json' => $payload, 'document_type' => 'invoice', 'status' => IntegrationDocumentInboxStatus::Pending,
        ]);
    }

    private function document(): BusinessDocument
    {
        return BusinessDocument::create([
            'company_id' => $this->company->id, 'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Issued, 'number' => 'TEST-42',
            'issue_date' => now(), 'total' => '125.00', 'currency' => 'EUR',
        ]);
    }

    private function member(User $user): CompanyMember
    {
        return CompanyMember::create([
            'company_id' => $this->company->id, 'user_id' => $user->id,
            'role' => CompanyMemberRole::Accountant, 'invited_by' => $this->owner->id, 'accepted_at' => now(),
        ]);
    }

    private function category(array $data, string $key): array
    {
        return collect($data['categories'])->firstWhere('key', $key);
    }
}
