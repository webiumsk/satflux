<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Support\Invoicing\SkUblProfile;
use PHPUnit\Framework\TestCase;

class SkUblProfileTest extends TestCase
{
    public function test_resolves_sk_supplier_endpoint_from_dic(): void
    {
        $company = new Company([
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'country' => 'SK',
            'registration_number' => '47615681',
            'tax_id' => '2023980035',
        ]);

        $endpoint = SkUblProfile::resolveEndpoint($company);

        $this->assertSame(SkUblProfile::SCHEME_DIC, $endpoint['scheme']);
        $this->assertSame('2023980035', $endpoint['id']);
    }

    public function test_resolves_contact_peppol_participant_id(): void
    {
        $contact = new CompanyContact([
            'country' => 'SK',
            'peppol_participant_id' => '0245:1234567890',
        ]);

        $endpoint = SkUblProfile::resolveEndpoint($contact);

        $this->assertSame('0245', $endpoint['scheme']);
        $this->assertSame('1234567890', $endpoint['id']);
    }

    public function test_maps_slovak_unit_aliases_to_un_ece(): void
    {
        $this->assertSame('C62', SkUblProfile::resolveUnitCode('ks.'));
        $this->assertSame('HUR', SkUblProfile::resolveUnitCode('hod.'));
    }
}
