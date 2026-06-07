<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BuyerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function issue_captures_buyer_snapshot(): void
    {
        $user = $this->proUser();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'iban' => 'SK3112000000198747547509',
            'default_currency' => 'EUR',
        ]);
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client Ltd',
            'email' => 'client@example.com',
            'tax_id' => '12345678',
        ]);
        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'status' => BusinessDocumentStatus::Draft,
            'currency' => 'EUR',
            'total' => 100,
        ]);

        app(BusinessDocumentIssueService::class)->issue($document->fresh());

        $document->refresh();
        $this->assertIsArray($document->buyer_snapshot);
        $this->assertSame('Client Ltd', $document->buyer_snapshot['name']);
        $this->assertSame('client@example.com', $document->buyer_snapshot['email']);

        $resolved = $document->resolvedBuyer();
        $this->assertSame('Client Ltd', $resolved?->name);
    }

    #[Test]
    public function deleting_contact_with_issued_document_anonymizes_instead(): void
    {
        $user = $this->proUser();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Acme s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Client Ltd',
            'email' => 'secret@example.com',
        ]);
        $document = BusinessDocument::create([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260001',
            'buyer_snapshot' => [
                'name' => 'Client Ltd',
                'email' => 'secret@example.com',
                'captured_at' => now()->toIso8601String(),
            ],
        ]);

        $response = $this->actingAs($user)->deleteJson(
            "/api/invoicing/companies/{$company->id}/contacts/{$contact->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('message', fn ($m) => str_contains((string) $m, 'anonymized'));

        $contact->refresh();
        $this->assertNull($contact->email);
        $this->assertStringContainsString('Removed contact', $contact->name);

        $document->refresh();
        $this->assertSame('Client Ltd', $document->resolvedBuyer()?->name);
    }

    protected function proUser(): User
    {
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
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        return $user;
    }
}
