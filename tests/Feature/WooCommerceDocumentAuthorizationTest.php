<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\IntegrationDocumentInboxStatus;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\User;
use App\Services\Integrations\WooCommerceDocumentService;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WooCommerceDocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Store $store;

    private Store $otherStore;

    private StoreIntegration $integration;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $owner = User::factory()->create(['role' => 'enterprise']);
        $this->company = Company::create([
            'user_id' => $owner->id,
            'legal_name' => 'Store Isolation Co',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);
        $this->store = Store::factory()->create(['user_id' => $owner->id, 'company_id' => $this->company->id]);
        $this->otherStore = Store::factory()->create(['user_id' => $owner->id, 'company_id' => $this->company->id]);
        $credentials = StoreIntegration::createForStore($this->store);
        $this->integration = $credentials['integration'];
        $this->token = $credentials['token'];
        app(DocumentSequenceService::class)->seedDefaultsForCompany($this->company);
    }

    /** @return iterable<string, array{string}> */
    public static function foreignDocuments(): iterable
    {
        yield 'another store in the same company' => ['other-store'];
        yield 'document without a store' => ['no-store'];
        yield 'same store but different company' => ['other-company'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function documentEndpoints(): iterable
    {
        foreach (self::foreignDocuments() as $name => [$target]) {
            foreach (['read', 'issue'] as $operation) {
                yield "$name / $operation" => [$target, $operation];
            }
        }
    }

    #[Test]
    #[DataProvider('documentEndpoints')]
    public function token_cannot_read_or_issue_a_document_outside_its_store_and_company(string $target, string $operation): void
    {
        $document = $this->foreignDocument($target);
        $before = $document->getAttributes();
        $counters = $this->company->documentSequences()->pluck('last_number', 'id')->all();
        $logs = AuditLog::count();
        $base = '/api/integrations/woocommerce/documents/'.$document->id;

        $this->withHeader('Authorization', 'Bearer '.$this->token);
        $response = $operation === 'read' ? $this->getJson($base) : $this->postJson($base.'/issue');
        $response->assertNotFound();

        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertSame($counters, $this->company->documentSequences()->pluck('last_number', 'id')->all());
        $this->assertSame($logs, AuditLog::count());
        Http::assertNothingSent();
    }

    #[Test]
    #[DataProvider('foreignDocuments')]
    public function service_rejects_a_foreign_document_before_issuing_it(string $target): void
    {
        $document = $this->foreignDocument($target);
        $before = $document->getAttributes();

        try {
            app(WooCommerceDocumentService::class)->issueDocument($this->integration, $document);
            $this->fail('The integration must not issue a foreign document.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->assertSame($before, $document->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    #[Test]
    public function token_can_read_issue_and_retry_its_own_store_document(): void
    {
        $document = $this->document($this->store->id, $this->company->id);
        $base = '/api/integrations/woocommerce/documents/'.$document->id;

        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson($base)
            ->assertOk()->assertJsonPath('data.id', $document->id)->assertJsonPath('data.status', 'draft');
        $number = $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson($base.'/issue')
            ->assertOk()->assertJsonPath('data.status', 'issued')->json('data.number');
        $this->assertNotEmpty($number);
        $this->assertSame(BusinessDocumentStatus::Issued, $document->fresh()->status);

        $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson($base.'/issue')
            ->assertOk()->assertJsonPath('data.number', $number);
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson($base)
            ->assertOk()->assertJsonPath('data.number', $number);
        $this->assertSame(1, AuditLog::where('action', 'business_document.issued')->count());
        Http::assertNothingSent();
    }

    #[Test]
    public function service_cannot_return_an_already_issued_foreign_store_document(): void
    {
        $document = $this->foreignDocument('other-store');
        $document->update(['status' => BusinessDocumentStatus::Issued, 'number' => 'OTHER-001']);
        $this->expectException(HttpException::class);
        app(WooCommerceDocumentService::class)->issueDocument($this->integration, $document);
    }

    #[Test]
    public function inbox_read_issue_and_pdf_remain_isolated_to_the_integration(): void
    {
        $otherIntegration = StoreIntegration::createForStore($this->otherStore)['integration'];
        $entry = IntegrationDocumentInbox::create([
            'store_integration_id' => $otherIntegration->id,
            'evolu_document_id' => (string) Str::uuid(),
            'document_type' => 'invoice',
            'status' => IntegrationDocumentInboxStatus::Pending,
            'payload_json' => ['number' => 'PRIVATE-001', 'buyer' => ['email' => 'private@example.test']],
        ]);
        $entry->refresh();
        $before = $entry->getAttributes();
        $base = '/api/integrations/woocommerce/documents/'.$entry->id;

        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson($base)->assertNotFound();
        $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson($base.'/issue')->assertNotFound();
        foreach ([$entry->id, $entry->evolu_document_id] as $id) {
            $this->withHeader('Authorization', 'Bearer '.$this->token)
                ->getJson('/api/integrations/woocommerce/documents/'.$id.'/pdf')->assertNotFound();
        }
        $this->assertSame($before, $entry->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    private function foreignDocument(string $target): BusinessDocument
    {
        $companyId = $this->company->id;
        if ($target === 'other-company') {
            $other = Company::create([
                'user_id' => $this->company->user_id,
                'legal_name' => 'Other Company',
                'jurisdiction' => CompanyJurisdiction::EuSk,
                'default_currency' => 'EUR',
            ]);
            $companyId = $other->id;
        }
        $storeId = match ($target) {
            'other-store' => $this->otherStore->id,
            'no-store' => null,
            default => $this->store->id,
        };

        return $this->document($storeId, $companyId);
    }

    private function document(?string $storeId, string $companyId): BusinessDocument
    {
        return BusinessDocument::create([
            'company_id' => $companyId,
            'store_id' => $storeId,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
            'payment_btc_enabled' => false,
        ])->refresh();
    }
}
