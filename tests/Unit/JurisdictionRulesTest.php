<?php

namespace Tests\Unit;

use App\Enums\CompanyJurisdiction;
use App\Support\Invoicing\JurisdictionRules;
use PHPUnit\Framework\TestCase;

/**
 * Locks the PHP side of the jurisdiction rules to the shared expectations -
 * resources/js/__tests__/jurisdictionRules.test.ts asserts the SAME values
 * against the TS mirror, so the two sources cannot drift silently.
 */
class JurisdictionRulesTest extends TestCase
{
    public function test_country_codes_map_to_the_new_jurisdictions(): void
    {
        $this->assertSame(CompanyJurisdiction::EuDe, CompanyJurisdiction::fromCountryCode('DE'));
        $this->assertSame(CompanyJurisdiction::EuAt, CompanyJurisdiction::fromCountryCode('AT'));
        $this->assertSame(CompanyJurisdiction::Ch, CompanyJurisdiction::fromCountryCode('CH'));
        // Liechtenstein belongs to the Swiss MWST area.
        $this->assertSame(CompanyJurisdiction::Ch, CompanyJurisdiction::fromCountryCode('LI'));
        // Untouched mappings.
        $this->assertSame(CompanyJurisdiction::EuSk, CompanyJurisdiction::fromCountryCode('SK'));
        $this->assertSame(CompanyJurisdiction::EuOther, CompanyJurisdiction::fromCountryCode('FR'));
    }

    public function test_pay_by_square_stays_a_sk_cz_standard(): void
    {
        $this->assertFalse(CompanyJurisdiction::EuDe->supportsPayBySquare());
        $this->assertFalse(CompanyJurisdiction::EuAt->supportsPayBySquare());
        $this->assertFalse(CompanyJurisdiction::Ch->supportsPayBySquare());
        $this->assertTrue(CompanyJurisdiction::EuSk->supportsPayBySquare());
        $this->assertTrue(CompanyJurisdiction::EuOther->supportsPayBySquare());
    }

    /**
     * @return array<string, array{CompanyJurisdiction, array<string, mixed>}>
     */
    public static function ruleExpectations(): array
    {
        return [
            'eu_sk' => [CompanyJurisdiction::EuSk, [
                'vat_name' => 'DPH', 'tax_id_label' => 'IČ DPH',
                'vat_rates' => [0.0, 5.0, 19.0, 23.0], 'archive_years' => 10,
                'eu_member' => true, 'issue_from' => '2027-01-01',
            ]],
            'eu_de' => [CompanyJurisdiction::EuDe, [
                'vat_name' => 'USt.', 'tax_id_label' => 'USt-IdNr.',
                'vat_rates' => [0.0, 7.0, 19.0], 'archive_years' => 8,
                'eu_member' => true, 'receive_from' => '2025-01-01',
                'issue_from' => '2027-01-01', 'issue_from_all' => '2028-01-01',
                'pdf_label_override' => true,
            ]],
            'eu_at' => [CompanyJurisdiction::EuAt, [
                'vat_name' => 'USt.', 'tax_id_label' => 'UID-Nr.',
                'vat_rates' => [0.0, 10.0, 13.0, 20.0], 'archive_years' => 7,
                'eu_member' => true, 'b2g_only' => true, 'pdf_label_override' => true,
            ]],
            'ch' => [CompanyJurisdiction::Ch, [
                'vat_name' => 'MWST', 'tax_id_label' => 'UID (MWST)',
                'vat_rates' => [0.0, 2.6, 3.8, 8.1], 'archive_years' => 10,
                'eu_member' => false, 'pdf_label_override' => true,
            ]],
        ];
    }

    public function test_jurisdiction_rules_match_the_shared_expectations(): void
    {
        foreach (self::ruleExpectations() as $name => [$jurisdiction, $expected]) {
            $rules = JurisdictionRules::for($jurisdiction);

            $this->assertSame($expected['vat_name'], $rules['vat_name'], $name);
            $this->assertSame($expected['tax_id_label'], $rules['tax_id_label'], $name);
            $this->assertSame($expected['vat_rates'], $rules['vat_rates'], $name);
            $this->assertSame($expected['archive_years'], $rules['archive_years'], $name);
            $this->assertSame($expected['eu_member'], $rules['eu_member'], $name);
            if (array_key_exists('pdf_label_override', $expected)) {
                $this->assertTrue($rules['pdf_label_override'], $name);
            }
            foreach (['receive_from', 'issue_from', 'issue_from_all', 'b2g_only'] as $eInvKey) {
                if (array_key_exists($eInvKey, $expected)) {
                    $this->assertSame($expected[$eInvKey], $rules['e_invoicing'][$eInvKey] ?? null, "$name.$eInvKey");
                }
            }
        }
    }

    public function test_generic_buckets_require_manual_review(): void
    {
        foreach ([CompanyJurisdiction::EuOther, CompanyJurisdiction::Offshore, CompanyJurisdiction::Asia] as $bucket) {
            $rules = JurisdictionRules::for($bucket);
            $this->assertTrue($rules['is_generic_bucket'], $bucket->value);
            $this->assertTrue($rules['requires_manual_review'], $bucket->value);
        }
        $this->assertFalse(JurisdictionRules::for(CompanyJurisdiction::Offshore)['has_vat']);
        $this->assertFalse(JurisdictionRules::for(CompanyJurisdiction::Asia)['has_vat']);
        $this->assertFalse(JurisdictionRules::for(CompanyJurisdiction::Us)['has_vat']);
    }

    public function test_every_jurisdiction_has_a_rules_row(): void
    {
        foreach (CompanyJurisdiction::cases() as $jurisdiction) {
            $rules = JurisdictionRules::for($jurisdiction);
            $this->assertNotSame('', $rules['vat_name'], $jurisdiction->value);
            $this->assertNotSame('', $rules['tax_id_label'], $jurisdiction->value);
        }
    }
}
