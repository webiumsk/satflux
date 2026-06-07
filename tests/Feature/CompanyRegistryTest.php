<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected User $proUser;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->proUser = User::factory()->create();
        Subscription::create([
            'user_id' => $this->proUser->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    #[Test]
    public function pro_user_can_load_registry_coverage(): void
    {
        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/coverage')
            ->assertOk()
            ->assertJsonPath('data.meta.subjekt', ['sk', 'cz'])
            ->assertJsonPath('data.meta.openregistry', fn ($v) => is_array($v) && in_array('pl', $v, true))
            ->assertJsonPath('data.options', fn ($opts) => is_array($opts) && count($opts) >= 10);
    }

    #[Test]
    public function pro_user_can_search_registry_sk(): void
    {
        Http::fake([
            'https://api.subjekt.sk/v1/search*' => Http::response([
                'results' => [
                    [
                        'ico' => '12345678',
                        'name' => 'Test s.r.o.',
                        'dic' => '2023456789',
                        'ic_dph' => 'SK2023456789',
                        'address' => [
                            'street' => 'Hlavná',
                            'building_no' => '1',
                            'zip' => '81101',
                            'city' => 'Bratislava',
                            'country' => 'SK',
                        ],
                    ],
                ],
                'count' => 1,
            ]),
        ]);

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/search?q=Test&country=sk')
            ->assertOk()
            ->assertJsonPath('data.results.0.ico', '12345678')
            ->assertJsonPath('data.results.0.name', 'Test s.r.o.');
    }

    #[Test]
    public function pro_user_can_search_openregistry_pl(): void
    {
        Http::fake([
            'https://openregistry.sophymarine.com/api/v1/search*' => Http::response([
                'jurisdiction' => 'PL',
                'count' => 1,
                'results' => [
                    [
                        'jurisdiction' => 'PL',
                        'company_id' => '0000814511',
                        'company_name' => 'ALLEGRO FINANCE SP. Z O.O.',
                        'status' => 'active',
                        'registered_address' => 'POZNAŃ',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/search?q=Allegro&country=pl')
            ->assertOk()
            ->assertJsonPath('data.results.0.ico', '0000814511')
            ->assertJsonPath('data.results.0.registry_jurisdiction', 'PL');
    }

    #[Test]
    public function manual_country_search_returns_empty(): void
    {
        Http::fake();

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/search?q=Test&country=us')
            ->assertOk()
            ->assertJsonPath('data.results', [])
            ->assertJsonPath('data.count', 0);

        Http::assertNothingSent();
    }

    #[Test]
    public function pro_user_can_fetch_entity_by_ico_sk(): void
    {
        Http::fake([
            'https://api.subjekt.sk/v1/entity/12345678*' => Http::response([
                'ico' => '12345678',
                'name' => 'Test s.r.o.',
                'dic' => '2023456789',
                'ic_dph' => 'SK2023456789',
                'address' => [
                    'street' => 'Hlavná',
                    'building_no' => '1',
                    'zip' => '81101',
                    'city' => 'Bratislava',
                    'country' => 'SK',
                ],
                'registration' => [
                    'office' => 'Obchodný register Okresného súdu Bratislava I',
                    'number' => 'Sro 12345/B',
                ],
            ]),
        ]);

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/entities/12345678?country=sk')
            ->assertOk()
            ->assertJsonPath('data.ico', '12345678')
            ->assertJsonPath('data.country', 'Slovensko');
    }

    #[Test]
    public function openregistry_entity_uses_search_fallback_without_token(): void
    {
        Http::fake([
            'https://openregistry.sophymarine.com/api/v1/companies/PL/0000814511*' => Http::response([
                'error' => 'access_denied',
            ], 403),
            'https://openregistry.sophymarine.com/api/v1/search*' => Http::response([
                'jurisdiction' => 'PL',
                'count' => 1,
                'results' => [
                    [
                        'jurisdiction' => 'PL',
                        'company_id' => '0000814511',
                        'company_name' => 'ALLEGRO FINANCE SP. Z O.O.',
                        'registered_address' => 'POZNAŃ',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/entities/0000814511?country=pl')
            ->assertOk()
            ->assertJsonPath('data.name', 'ALLEGRO FINANCE SP. Z O.O.')
            ->assertJsonPath('data.city', 'POZNAŃ')
            ->assertJsonPath('data.country_code', 'PL');
    }

    #[Test]
    public function entity_lookup_returns_404_when_not_found(): void
    {
        Http::fake([
            'https://api.subjekt.sk/v1/entity/99999999*' => Http::response([], 404),
        ]);

        $this->actingAs($this->proUser)
            ->getJson('/api/invoicing/company-registry/entities/99999999?country=sk')
            ->assertNotFound();
    }
}
