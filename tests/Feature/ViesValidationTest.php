<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViesValidationTest extends TestCase
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
    public function pro_user_can_validate_vat_via_vies(): void
    {
        Http::fake([
            'ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number*' => Http::response([
                'countryCode' => 'SK',
                'vatNumber' => '2023980035',
                'requestDate' => '2026-06-02',
                'valid' => true,
                'name' => 'Webium s.r.o.',
                'address' => 'Bohunice 47',
            ]),
        ]);

        $user = $this->proUser();

        $this->actingAs($user)
            ->postJson('/api/invoicing/vies/validate', [
                'vat_number' => 'SK2023980035',
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.country_code', 'SK')
            ->assertJsonPath('data.vat_number', '2023980035');
    }
}
