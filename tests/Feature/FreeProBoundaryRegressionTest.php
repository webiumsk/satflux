<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Enums\CompanyMemberRole;
use App\Enums\IntegrationDocumentInboxStatus;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Models\StoreIntegration;
use App\Models\User;
use App\Services\Integrations\WooCommerceDocumentService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FreeProBoundaryRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.free_pro_enabled' => true]);
        $this->seed(SubscriptionPlanSeeder::class);
        Http::fake();
    }

    public function test_promoted_member_cannot_use_their_entitlement_for_an_ineligible_owner(): void
    {
        $owner = User::factory()->create(['role' => 'free', 'email_verified_at' => null]);
        $member = User::factory()->create(['role' => 'free']);
        $company = $this->company($owner);
        $this->member($company, $member);
        $this->actingAs($member)
            ->postJson("/api/invoicing/companies/{$company->id}/number-allocator/reserve", [
                'document_type' => 'invoice', 'issue_request_id' => 'synthetic-member-denied',
            ])->assertForbidden();
        $this->assertDatabaseCount('business_documents', 0);
        Http::assertNothingSent();
    }

    public function test_promoted_owner_sponsors_members_without_granting_owner_or_outsider_access(): void
    {
        $owner = User::factory()->create(['role' => 'free']);
        $member = User::factory()->create(['role' => 'free']);
        $company = $this->company($owner);
        $this->member($company, $member);
        $this->actingAs($member)->getJson("/api/invoicing/companies/{$company->id}")
            ->assertOk()->assertJsonPath('data.role', 'accountant');
        $this->actingAs($member)->deleteJson("/api/invoicing/companies/{$company->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'free']))
            ->getJson("/api/invoicing/companies/{$company->id}")->assertForbidden();
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_guest_verification_and_admin_guards_remain_independent_of_promotion(): void
    {
        $this->actingAs(User::factory()->guest()->create(['email_verified_at' => now()]))
            ->getJson('/api/user/api-keys')->assertForbidden()->assertJsonPath('code', 'guest_feature_locked');
        $this->actingAs(User::factory()->create(['email_verified_at' => null]))
            ->getJson('/api/user/limits')->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->actingAs(User::factory()->create(['role' => 'free']))
            ->getJson('/api/admin/users')->assertForbidden();
        Http::assertNothingSent();
    }

    public static function documentModes(): iterable
    {
        yield 'server document' => [false];
        yield 'local-first inbox' => [true];
    }

    #[DataProvider('documentModes')]
    public function test_numbered_order_retry_completes_without_new_work_after_promotion_ends(bool $inboxMode): void
    {
        config(['invoicing.woocommerce_inbox_mode' => $inboxMode, 'invoicing.local_first' => false]);
        $owner = User::factory()->create(['role' => 'free']);
        $company = $this->company($owner);
        $store = Store::factory()->create(['user_id' => $owner->id, 'company_id' => $company->id]);
        $integration = StoreIntegration::createForStore($store)['integration'];
        if ($inboxMode) {
            $existing = IntegrationDocumentInbox::create([
                'store_integration_id' => $integration->id, 'woocommerce_order_id' => 42,
                'evolu_document_id' => (string) Str::uuid(), 'document_type' => 'invoice',
                'status' => IntegrationDocumentInboxStatus::Pending,
                'payload_json' => ['type' => 'invoice', 'number' => 'SYNTHETIC-042'],
            ]);
        } else {
            $existing = BusinessDocument::create([
                'company_id' => $company->id, 'store_id' => $store->id,
                'type' => BusinessDocumentType::Invoice, 'status' => BusinessDocumentStatus::Issued,
                'number' => 'SYNTHETIC-042', 'currency' => 'EUR', 'total' => 0,
                'tags' => ['wc_order:42'],
            ]);
        }
        config(['access.free_pro_enabled' => false]);
        $service = app(WooCommerceDocumentService::class);
        $retry = $service->createDocument($integration, ['woocommerce_order_id' => 42, 'type' => 'invoice']);
        $this->assertSame($existing->id, $retry->id);
        $this->assertSame('SYNTHETIC-042', $inboxMode ? $retry->payload_json['number'] : $retry->number);
        $this->assertDatabaseCount($inboxMode ? 'integration_document_inbox' : 'business_documents', 1);
        Http::assertNothingSent();
        // A different order may not use the completion exception to create work.
        $this->expectException(ValidationException::class);
        $service->createDocument($integration, ['woocommerce_order_id' => 43, 'type' => 'invoice']);
    }

    private function company(User $owner): Company
    {
        return Company::create([
            'user_id' => $owner->id, 'legal_name' => 'Synthetic Boundary Company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
        ]);
    }

    private function member(Company $company, User $member): void
    {
        CompanyMember::create([
            'company_id' => $company->id, 'user_id' => $member->id,
            'role' => CompanyMemberRole::Accountant, 'invited_by' => $company->user_id,
            'accepted_at' => now(),
        ]);
    }
}
