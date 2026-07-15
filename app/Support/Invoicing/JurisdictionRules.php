<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;

/**
 * Legal invoicing rules per jurisdiction - the code-level equivalent of a
 * jurisdiction_rules table (local-first: rules must exist on both sides, so
 * a DB row would never reach the offline client).
 *
 * MIRROR: resources/js/config/jurisdictionRules.ts carries the same data for
 * the browser. Both sides are locked to shared expectations by tests
 * (tests/Unit/JurisdictionRulesTest.php + jurisdictionRules vitest) - update
 * all three together.
 *
 * Rates and e-invoicing mandates are a snapshot (verified July 2026);
 * re-verify any mandate within ~6 months of its effective date.
 */
final class JurisdictionRules
{
    /**
     * @return array{
     *     has_vat: bool,
     *     vat_name: string,
     *     tax_id_label: string,
     *     vat_rates: list<float>,
     *     eu_member: bool,
     *     sequential_numbering: bool,
     *     archive_years: int|null,
     *     e_invoicing: array{network: string, receive_from: string|null, issue_from: string|null, issue_from_all: string|null, b2g_only: bool}|null,
     *     pdf_label_override: bool,
     *     is_generic_bucket: bool,
     *     requires_manual_review: bool,
     *     legal_basis_note: string,
     * }
     */
    public static function for(CompanyJurisdiction $jurisdiction): array
    {
        return match ($jurisdiction) {
            CompanyJurisdiction::EuSk => self::rules([
                'vat_name' => 'DPH',
                'tax_id_label' => 'IČ DPH',
                'vat_rates' => [0.0, 5.0, 19.0, 23.0],
                'archive_years' => 10,
                'e_invoicing' => self::eInvoicing('peppol', receiveFrom: '2027-01-01', issueFrom: '2027-01-01'),
                'legal_basis_note' => 'zákon č. 385/2025 Z.z.; §76a zákona č. 222/2004 Z.z. o DPH',
            ]),
            CompanyJurisdiction::EuCz => self::rules([
                'vat_name' => 'DPH',
                'tax_id_label' => 'DIČ',
                'vat_rates' => [0.0, 12.0, 21.0],
                'archive_years' => 5,
                'legal_basis_note' => 'zákon č. 235/2004 Sb. §29; občanský zákoník §435',
            ]),
            CompanyJurisdiction::EuDe => self::rules([
                'vat_name' => 'USt.',
                'tax_id_label' => 'USt-IdNr.',
                'pdf_label_override' => true,
                'vat_rates' => [0.0, 7.0, 19.0],
                'archive_years' => 8,
                'e_invoicing' => self::eInvoicing(
                    'xrechnung_zugferd',
                    receiveFrom: '2025-01-01',
                    issueFrom: '2027-01-01',
                    issueFromAll: '2028-01-01',
                ),
                'legal_basis_note' => 'Wachstumschancengesetz; §14, §14a UStG; GoBD (8 Jahre - BEG IV)',
            ]),
            CompanyJurisdiction::EuAt => self::rules([
                'vat_name' => 'USt.',
                'tax_id_label' => 'UID-Nr.',
                'pdf_label_override' => true,
                'vat_rates' => [0.0, 10.0, 13.0, 20.0],
                'archive_years' => 7,
                'e_invoicing' => self::eInvoicing('peppol', b2gOnly: true),
                'legal_basis_note' => '§11 UStG (AT); §132 BAO; IKT-Konsolidierungsgesetz (B2G)',
            ]),
            CompanyJurisdiction::Ch => self::rules([
                'vat_name' => 'MWST',
                'tax_id_label' => 'UID (MWST)',
                'pdf_label_override' => true,
                'vat_rates' => [0.0, 2.6, 3.8, 8.1],
                'eu_member' => false,
                'archive_years' => 10,
                'legal_basis_note' => 'MWSTG Art. 26; OR Art. 958f (Aufbewahrung)',
            ]),
            CompanyJurisdiction::Uk => self::rules([
                'vat_name' => 'VAT',
                'tax_id_label' => 'VAT Reg No',
                'vat_rates' => [0.0, 5.0, 20.0],
                'eu_member' => false,
                'archive_years' => 6,
                'e_invoicing' => self::eInvoicing('peppol', issueFrom: '2029-04-01'),
                'legal_basis_note' => 'VAT Regulations 1995 (HMRC); B2B e-invoicing mandate confirmed for 1 Apr 2029',
            ]),
            CompanyJurisdiction::Us => self::rules([
                'has_vat' => false,
                'vat_name' => 'Sales Tax',
                'tax_id_label' => 'EIN',
                'vat_rates' => [],
                'eu_member' => false,
                'sequential_numbering' => false,
                'archive_years' => null,
                'legal_basis_note' => 'No federal VAT/e-invoicing; state sales tax applies only with US nexus',
            ]),
            CompanyJurisdiction::EuOther => self::rules([
                'vat_name' => 'VAT',
                'tax_id_label' => 'VAT ID',
                'vat_rates' => [],
                'is_generic_bucket' => true,
                'requires_manual_review' => true,
                'archive_years' => null,
                'legal_basis_note' => 'Generic EU bucket - country-specific rules not configured; merchant must verify local obligations',
            ]),
            CompanyJurisdiction::Offshore => self::rules([
                'has_vat' => false,
                'vat_name' => 'VAT',
                'tax_id_label' => 'Tax ID',
                'vat_rates' => [0.0],
                'eu_member' => false,
                'is_generic_bucket' => true,
                'requires_manual_review' => true,
                'archive_years' => null,
                'legal_basis_note' => 'Generic offshore bucket - jurisdictions vary too much to default any VAT rules',
            ]),
            CompanyJurisdiction::Asia => self::rules([
                'has_vat' => false,
                'vat_name' => 'VAT',
                'tax_id_label' => 'Tax ID',
                'vat_rates' => [0.0],
                'eu_member' => false,
                'is_generic_bucket' => true,
                'requires_manual_review' => true,
                'archive_years' => null,
                'legal_basis_note' => 'Generic Asia bucket (SG GST 9%, JP 10%, AE 5%, ...) - split into real countries as demand appears',
            ]),
        };
    }

    public static function vatLabel(CompanyJurisdiction $jurisdiction): string
    {
        return self::for($jurisdiction)['vat_name'];
    }

    public static function taxIdLabel(CompanyJurisdiction $jurisdiction): string
    {
        return self::for($jurisdiction)['tax_id_label'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function rules(array $overrides): array
    {
        return array_merge([
            'has_vat' => true,
            'vat_name' => 'VAT',
            'tax_id_label' => 'VAT ID',
            'vat_rates' => [],
            'eu_member' => true,
            'sequential_numbering' => true,
            'archive_years' => null,
            'e_invoicing' => null,
            // PDF prints vat_name/tax_id_label instead of the localized
            // generic terms - needed where one language covers several
            // statutory vocabularies (DE/AT/CH German).
            'pdf_label_override' => false,
            'is_generic_bucket' => false,
            'requires_manual_review' => false,
            'legal_basis_note' => '',
        ], $overrides);
    }

    /**
     * @return array{network: string, receive_from: string|null, issue_from: string|null, issue_from_all: string|null, b2g_only: bool}
     */
    private static function eInvoicing(
        string $network,
        ?string $receiveFrom = null,
        ?string $issueFrom = null,
        ?string $issueFromAll = null,
        bool $b2gOnly = false,
    ): array {
        return [
            'network' => $network,
            'receive_from' => $receiveFrom,
            'issue_from' => $issueFrom,
            'issue_from_all' => $issueFromAll,
            'b2g_only' => $b2gOnly,
        ];
    }
}
