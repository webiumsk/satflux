<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Jobs\SendWooAutoInvoiceEmail;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\User;
use App\Services\CompanyPermanentDeleteService;
use App\Services\Integrations\IntegrationAutoIssueService;
use App\Services\Integrations\IntegrationDocumentInboxService;
use App\Services\Integrations\WooCommerceDocumentService;
use App\Services\Integrations\WooCommerceWebhookNotifier;
use App\Services\Invoicing\BusinessDocumentEmailService;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WooCommerceCompanyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private Store $store;

    private StoreIntegration $integration;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->owner = User::factory()->create(['role' => 'enterprise']);
        $this->company = $this->company();
        $this->store = Store::factory()->create(['user_id' => $this->owner->id, 'company_id' => $this->company->id]);
        $credentials = StoreIntegration::createForStore($this->store);
        $this->integration = $credentials['integration'];
        $this->token = $credentials['token'];
    }

    public static function linkChanges(): iterable
    {
        yield 'unlink' => ['unlink'];
        yield 'move to another company' => ['move'];
        yield 'new company creation' => ['create'];
        yield 'delete company' => ['delete'];
    }

    #[DataProvider('linkChanges')]
    public function test_link_changes_revoke_the_token_and_preserve_inbox_data(string $change): void
    {
        $entry = $this->entry();
        $this->actingAs($this->owner);
        match ($change) {
            'unlink' => $this->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => []])->assertOk(),
            'move' => $this->patchJson('/api/invoicing/companies/'.$this->company()->id.'/stores', ['store_ids' => [$this->store->id]])->assertOk(),
            'create' => $this->postJson('/api/invoicing/companies', ['legal_name' => 'New Co', 'jurisdiction' => 'eu_sk', 'store_id' => $this->store->id])->assertCreated(),
            'delete' => $this->deleteJson('/api/invoicing/companies/'.$this->company->id)->assertOk(),
        };
        $this->assertFalse($this->integration->fresh()->is_active);
        $this->assertDatabaseHas('integration_document_inbox', ['id' => $entry->id]);
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/integrations/woocommerce/connection')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_permanent_company_deletion_cannot_turn_an_old_token_into_companyless_access(): void
    {
        app(CompanyPermanentDeleteService::class)->forceDelete($this->company);
        $this->assertNull($this->store->fresh()->company_id);
        $this->assertNull($this->integration->fresh()->company_id);
        $this->assertFalse($this->integration->fresh()->is_active);
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/integrations/woocommerce/connection')->assertUnauthorized();
    }

    public function test_unchanged_link_keeps_token_working(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => [$this->store->id]])->assertOk();
        $this->assertTrue($this->integration->fresh()->is_active);
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/integrations/woocommerce/connection')->assertOk();
    }

    public function test_unlink_then_relink_does_not_resurrect_old_token(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => []])->assertOk();
        $this->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => [$this->store->id]])->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/integrations/woocommerce/connection')->assertUnauthorized();
        $new = StoreIntegration::createForStore($this->store);
        $this->withHeader('Authorization', 'Bearer '.$new['token'])->getJson('/api/integrations/woocommerce/connection')->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/integrations/woocommerce/connection')->assertUnauthorized();
    }

    public function test_preexisting_active_mismatch_is_rejected_by_every_token_route(): void
    {
        $this->store->update(['company_id' => $this->company()->id]);
        $this->withHeader('Authorization', 'Bearer '.$this->token);
        $this->getJson('/api/integrations/woocommerce/connection')->assertUnauthorized();
        $this->postJson('/api/integrations/woocommerce/documents', [])->assertUnauthorized();
        $this->postJson('/api/integrations/woocommerce/contacts/upsert', [])->assertUnauthorized();
        $id = Str::uuid();
        $this->getJson('/api/integrations/woocommerce/documents/'.$id)->assertUnauthorized();
        $this->postJson('/api/integrations/woocommerce/documents/'.$id.'/issue')->assertUnauthorized();
        $this->getJson('/api/integrations/woocommerce/documents/'.$id.'/pdf')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_reconnect_uses_current_link_even_with_a_stale_store_model(): void
    {
        $other = $this->company();
        Store::whereKey($this->store->id)->update(['company_id' => $other->id]);
        $new = StoreIntegration::createForStore($this->store);
        $this->assertSame($other->id, $new['integration']->company_id);
        $this->withHeader('Authorization', 'Bearer '.$new['token'])->getJson('/api/integrations/woocommerce/connection')->assertOk()->assertJsonPath('data.company.id', $other->id);
    }

    public function test_companyless_token_requires_reconnect_after_linking(): void
    {
        $this->store->update(['company_id' => null]);
        $new = StoreIntegration::createForStore($this->store);
        $this->withHeader('Authorization', 'Bearer '.$new['token'])->getJson('/api/integrations/woocommerce/connection')->assertOk();
        $this->actingAs($this->owner)->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => [$this->store->id]])->assertOk();
        $this->getJson('/api/integrations/woocommerce/connection')->assertUnauthorized();
    }

    public static function services(): iterable
    {
        yield 'server document' => [WooCommerceDocumentService::class, 'createDocument'];
        yield 'local-first inbox' => [IntegrationDocumentInboxService::class, 'enqueueFromWoo'];
    }

    #[DataProvider('services')]
    public function test_service_cannot_write_to_saved_company_after_unlinking(string $service, string $method): void
    {
        $this->store->update(['company_id' => null]);
        $this->expectException(HttpException::class);
        app($service)->$method($this->integration, []);
    }

    #[DataProvider('services')]
    public function test_stale_service_object_cannot_resume_after_unlink_and_relink(string $service, string $method): void
    {
        $this->actingAs($this->owner)->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => []])->assertOk();
        $this->patchJson('/api/invoicing/companies/'.$this->company->id.'/stores', ['store_ids' => [$this->store->id]])->assertOk();
        $this->expectException(HttpException::class);
        app($service)->$method($this->integration, []);
    }

    public static function staleJobs(): iterable
    {
        yield 'revoked integration' => [false, false];
        yield 'active but mismatched link' => [true, true];
    }

    #[DataProvider('staleJobs')]
    public function test_queued_email_cannot_use_revoked_or_mismatched_integration(bool $active, bool $mismatch): void
    {
        $entry = $this->entry();
        $this->integration->update(['is_active' => $active]);
        if ($mismatch) {
            $this->store->update(['company_id' => null]);
        }
        $autoIssue = $this->mock(IntegrationAutoIssueService::class);
        $autoIssue->shouldNotReceive('resolveProfileContext');
        $email = $this->mock(BusinessDocumentEmailService::class);
        $email->shouldNotReceive('sendEphemeral');
        (new SendWooAutoInvoiceEmail($entry->id))->handle($autoIssue, $email);
        $this->assertNull($entry->fresh()->payload_json['emailed_at'] ?? null);
        Http::assertNothingSent();
    }

    public function test_queued_email_cannot_follow_a_changed_integration_company(): void
    {
        $entry = $this->entry();
        $job = new SendWooAutoInvoiceEmail($entry->id);
        $this->store->update(['company_id' => $this->company()->id]);
        $this->integration->update(['company_id' => $this->store->company_id]);
        $autoIssue = $this->mock(IntegrationAutoIssueService::class);
        $autoIssue->shouldNotReceive('resolveProfileContext');
        $email = $this->mock(BusinessDocumentEmailService::class);
        $email->shouldNotReceive('sendEphemeral');
        $job->handle($autoIssue, $email);
        $this->assertNull($entry->fresh()->payload_json['emailed_at'] ?? null);
    }

    public function test_reconnect_cannot_reassign_existing_inbox_data_to_another_company(): void
    {
        $entry = $this->entry();
        $before = $this->integration->fresh()->getAttributes();
        $this->store->update(['company_id' => $this->company()->id]);
        try {
            StoreIntegration::createForStore($this->store);
            $this->fail('Existing inbox data must not change company on reconnect.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('store_id', $exception->errors());
        }
        $this->assertSame($before, $this->integration->fresh()->getAttributes());
        $this->assertDatabaseHas('integration_document_inbox', ['id' => $entry->id]);
        app(IntegrationDocumentInboxService::class)->dismiss($entry);
        $new = StoreIntegration::createForStore($this->store);
        $this->withHeader('Authorization', 'Bearer '.$new['token'])->getJson('/api/integrations/woocommerce/connection')->assertOk();
    }

    public static function staleWebhooks(): iterable
    {
        yield 'mismatched stored link' => [false];
        yield 'reconnected to another company' => [true];
    }

    #[DataProvider('staleWebhooks')]
    public function test_old_company_document_cannot_notify_a_changed_integration(bool $reconnect): void
    {
        $this->integration->update(['webhook_url' => 'https://shop.example.test/webhook']);
        $this->store->update(['company_id' => $this->company()->id]);
        if ($reconnect) {
            StoreIntegration::createForStore($this->store, 'https://shop.example.test/webhook');
        }
        $document = new BusinessDocument(['company_id' => $this->company->id, 'store_id' => $this->store->id, 'internal_note' => 'woocommerce_order_id=42']);
        $this->mock(OutboundUrlGuard::class)->shouldNotReceive('pinnedOptions');
        app(WooCommerceWebhookNotifier::class)->notifyDocumentPaid($document);
        Http::assertNothingSent();
    }

    public function test_legacy_job_without_dispatch_company_skips_delivery(): void
    {
        $entry = $this->entry();
        $job = new SendWooAutoInvoiceEmail($entry->id);
        $job->companyIdAtDispatch = null;
        $autoIssue = $this->mock(IntegrationAutoIssueService::class);
        $autoIssue->shouldNotReceive('resolveProfileContext');
        $email = $this->mock(BusinessDocumentEmailService::class);
        $email->shouldNotReceive('sendEphemeral');
        $job->handle($autoIssue, $email);
        $this->assertNull($entry->fresh()->payload_json['emailed_at'] ?? null);
    }

    private function company(): Company
    {
        return Company::create(['user_id' => $this->owner->id, 'legal_name' => 'Lifecycle Co', 'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR']);
    }

    private function entry(): IntegrationDocumentInbox
    {
        return IntegrationDocumentInbox::create(['store_integration_id' => $this->integration->id, 'evolu_document_id' => (string) Str::uuid(), 'document_type' => 'invoice', 'status' => 'pending', 'payload_json' => ['number' => 'OLD-001', 'buyer' => ['email' => 'buyer@example.test']]]);
    }
}
