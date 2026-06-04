<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function proUser(): User
    {
        $proPlan = SubscriptionPlan::create([
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
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        return $user;
    }

    #[Test]
    public function user_can_upload_company_logo_and_signature(): void
    {
        Storage::fake('local');
        $user = $this->proUser();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Test s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
        ]);

        $logo = UploadedFile::fake()->image('logo.png', 120, 40);
        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/branding/logo", ['image' => $logo])
            ->assertOk()
            ->assertJsonPath('data.has_logo', true);

        $company->refresh();
        $this->assertNotNull($company->logo_path);
        $this->assertStringEndsWith('.png', $company->logo_path);
        Storage::disk('local')->assertExists($company->logo_path);

        $stamp = UploadedFile::fake()->image('stamp.png', 200, 80);
        $this->actingAs($user)
            ->postJson("/api/invoicing/companies/{$company->id}/branding/signature-stamp", ['image' => $stamp])
            ->assertOk()
            ->assertJsonPath('data.has_signature_stamp', true);

        $this->actingAs($user)
            ->get("/api/invoicing/companies/{$company->id}/branding/logo")
            ->assertOk();
    }

    #[Test]
    public function user_can_patch_bank_fields_without_resending_profile(): void
    {
        $user = $this->proUser();
        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Test s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'iban' => 'SK3112000000198747547509',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/invoicing/companies/{$company->id}", [
                'bank_name' => 'Tatra banka',
                'bank_account' => '1234567890',
                'bank_code' => '1100',
                'bic' => 'TATRSKBX',
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'Tatra banka')
            ->assertJsonPath('data.bank_code', '1100')
            ->assertJsonPath('data.legal_name', 'Test s.r.o.');
    }
}
