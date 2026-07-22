<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\User;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\CompanyVatPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyVatPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function skPartialCompany(): Company
    {
        $user = User::factory()->create();

        return Company::create([
            'user_id' => $user->id,
            'legal_name' => 'Webium s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'country' => 'SK',
            'vat_payer' => true,
            'vat_status' => 'partial',
            'vat_rate_default' => 23,
            'default_currency' => 'EUR',
        ]);
    }

    #[Test]
    public function partial_payer_domestic_supply_hides_vat_and_calculates_no_tax(): void
    {
        $company = $this->skPartialCompany();
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Domáci',
            'country' => 'SK',
        ]);

        $policy = app(CompanyVatPolicy::class);

        $this->assertTrue($policy->isPartialPayer($company));
        $this->assertTrue($policy->isDomesticSupply($company, $contact));
        $this->assertFalse($policy->showsVatRateColumn($company, $contact));
        $this->assertFalse($policy->showsVatBreakdown($company, $contact));
        $this->assertFalse($policy->calculatesVatAmounts($company, $contact));

        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($company, [
            ['name' => 'Služba', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 23],
        ], 0, null, $contact);

        $this->assertSame('0.00', $canonical->taxTotal);
        $this->assertSame('100.00', $canonical->total);
        $this->assertFalse($canonical->vatApplicable());
    }

    #[Test]
    public function partial_payer_foreign_supply_shows_zero_vat_and_reverse_charge_note(): void
    {
        $company = $this->skPartialCompany();
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Zahraničný',
            'country' => 'DE',
        ]);

        $policy = app(CompanyVatPolicy::class);

        $this->assertTrue($policy->isForeignSupply($company, $contact));
        $this->assertSame('eu', $policy->supplyRegion($company, $contact));
        $this->assertTrue($policy->showsVatRateColumn($company, $contact));
        // EU reverse charge shows the summary with VAT 0.
        $this->assertTrue($policy->showsVatBreakdown($company, $contact));
        $this->assertFalse($policy->calculatesVatAmounts($company, $contact));

        $canonical = app(CanonicalInvoiceBuilder::class)->fromLinePayloads($company, [
            ['name' => 'Služba', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 23],
        ], 0, null, $contact);

        $this->assertSame(0.0, $canonical->lines[0]->taxRate);
        $this->assertSame('0.00', $canonical->taxTotal);
        $this->assertSame('100.00', $canonical->total);

        $note = $policy->reverseChargeNote($company, $contact, CompanyAppSettings::from([]));
        $this->assertSame(
            __(CompanyVatPolicy::PARTIAL_REVERSE_CHARGE_NOTE),
            $note,
        );
    }

    #[Test]
    public function partial_payer_non_eu_supply_hides_vat_and_carries_no_note(): void
    {
        $company = $this->skPartialCompany();
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'US klient',
            'country' => 'US',
        ]);

        $policy = app(CompanyVatPolicy::class);

        $this->assertSame('non_eu', $policy->supplyRegion($company, $contact));
        $this->assertFalse($policy->showsVatRateColumn($company, $contact));
        $this->assertFalse($policy->showsVatBreakdown($company, $contact));
        $this->assertNull($policy->reverseChargeNote($company, $contact, CompanyAppSettings::from([])));
    }

    #[Test]
    public function partial_payer_unrecognized_country_never_triggers_eu_reverse_charge(): void
    {
        $company = $this->skPartialCompany();
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Voľný text',
            'country' => 'Nemecká spolková republika',
        ]);

        $policy = app(CompanyVatPolicy::class);

        // A country that is not a recognizable ISO2 code must never trigger
        // the EU reverse-charge path.
        $this->assertSame('non_eu', $policy->supplyRegion($company, $contact));
        $this->assertFalse($policy->showsVatBreakdown($company, $contact));
        $this->assertNull($policy->reverseChargeNote($company, $contact, CompanyAppSettings::from([])));
    }

    #[Test]
    public function full_payer_always_shows_vat_regardless_of_counterparty(): void
    {
        $company = $this->skPartialCompany();
        $company->vat_status = 'payer';
        $contact = CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'US klient',
            'country' => 'US',
        ]);

        $policy = app(CompanyVatPolicy::class);

        $this->assertTrue($policy->showsVatBreakdown($company, $contact));
        $this->assertTrue($policy->showsVatBreakdown($company, null));
        $this->assertTrue($policy->calculatesVatAmounts($company, $contact));
        $this->assertNull($policy->reverseChargeNote($company, $contact, CompanyAppSettings::from([])));
    }
}
