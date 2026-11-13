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
        $this->assertSame('C62', SkUblProfile::resolveUnitCode(' c62 '));
        $this->assertSame('C62', SkUblProfile::resolveUnitCode('c62'));
        $this->assertSame('HUR', SkUblProfile::resolveUnitCode(' hur '));
        $this->assertSame('HUR', SkUblProfile::resolveUnitCode('hur'));
    }

    public function test_preserves_the_opaque_explicit_participant_identifier(): void
    {
        foreach (['9950:sk1234567890', '9950:SK1234567890', '0088:abc-123_456.789'] as $value) {
            $contact = new CompanyContact(['country' => 'SK', 'peppol_participant_id' => $value]);
            [$scheme, $id] = explode(':', $value, 2);
            $this->assertSame(['scheme' => $scheme, 'id' => $id], SkUblProfile::resolveEndpoint($contact));
        }
    }

    public function test_invalid_explicit_schemes_never_fall_back_to_a_different_address(): void
    {
        foreach (['bad:12345', '245:12345', '0245:', '9950:sk12:34', '9950:sk12 34', "9950:sk12\t34", '9950:sk12/34', '9950:'.str_repeat('a', 129)] as $value) {
            $contact = new CompanyContact(['country' => 'SK', 'peppol_participant_id' => $value, 'tax_id' => '9999999999']);
            $this->assertNull(SkUblProfile::resolveEndpoint($contact));
        }
    }

    public function test_slovak_ico_uses_its_iso_6523_scheme(): void
    {
        $company = new Company(['country' => 'SK', 'registration_number' => '12345679']);
        $this->assertSame(['scheme' => '0158', 'id' => '12345679'], SkUblProfile::resolveEndpoint($company));
        $this->assertSame(['scheme' => '0158', 'id' => '12345679'], SkUblProfile::resolveLegalEntityId($company));
        $company->country = 'CZ';
        $this->assertSame(['scheme' => '0208', 'id' => '12345679'], SkUblProfile::resolveEndpoint($company));
        $this->assertSame(['scheme' => '0208', 'id' => '12345679'], SkUblProfile::resolveLegalEntityId($company));
    }
}
