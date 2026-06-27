<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class EphemeralBusinessDocumentBulkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_download_ephemeral_bulk_pdf_zip_without_persisting_documents(): void
    {
        [$user] = $this->createProUserWithCompany();
        $single = $this->singleDocumentPayload();
        $payload = [
            'company' => $single['company'],
            'documents' => [
                [
                    'contact' => $single['contact'],
                    'document' => array_merge($single['document'], ['number' => 'LOCAL-001']),
                    'lines' => $single['lines'],
                ],
                [
                    'contact' => $single['contact'],
                    'document' => array_merge($single['document'], ['number' => 'LOCAL-002']),
                    'lines' => $single['lines'],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/invoicing/ephemeral/bulk/pdf-zip', $payload);

        $response->assertOk();
        $this->assertStringContainsString('application/zip', (string) $response->headers->get('content-type'));

        $zipPath = tempnam(sys_get_temp_dir(), 'bulk-zip-');
        file_put_contents($zipPath, $response->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertGreaterThanOrEqual(2, $zip->numFiles);
        $zip->close();
        @unlink($zipPath);

        $this->assertDatabaseCount('business_documents', 0);
    }

    #[Test]
    public function authenticated_user_can_download_ephemeral_bulk_pdf_merge_without_persisting_documents(): void
    {
        [$user] = $this->createProUserWithCompany();
        $single = $this->singleDocumentPayload();
        $payload = [
            'company' => $single['company'],
            'documents' => [
                [
                    'contact' => $single['contact'],
                    'document' => array_merge($single['document'], ['number' => 'LOCAL-A']),
                    'lines' => $single['lines'],
                ],
                [
                    'contact' => $single['contact'],
                    'document' => array_merge($single['document'], ['number' => 'LOCAL-B']),
                    'lines' => $single['lines'],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/invoicing/ephemeral/bulk/pdf-merge', $payload);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseCount('business_documents', 0);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    protected function createProUserWithCompany(): array
    {
        $plan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Bulk Ephemeral s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'street' => 'Main 1',
            'city' => 'Bratislava',
            'postal_code' => '81101',
            'country' => 'SK',
        ]);

        return [$user, $company];
    }

    /**
     * @return array<string, mixed>
     */
    protected function singleDocumentPayload(): array
    {
        return [
            'company' => [
                'legal_name' => 'Local Studio s.r.o.',
                'street' => 'Main 1',
                'city' => 'Bratislava',
                'postal_code' => '81101',
                'country' => 'SK',
                'default_currency' => 'EUR',
                'jurisdiction' => 'eu_sk',
            ],
            'contact' => [
                'name' => 'Client Ltd',
                'email' => 'client@example.com',
            ],
            'document' => [
                'type' => 'invoice',
                'status' => 'issued',
                'number' => 'LOCAL-2026-001',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'EUR',
                'discount_percent' => 0,
                'pdf_locale' => 'sk',
            ],
            'lines' => [
                [
                    'name' => 'Consulting',
                    'quantity' => 1,
                    'unit' => 'h',
                    'unit_price' => 100,
                    'tax_rate' => 0,
                ],
            ],
        ];
    }
}
