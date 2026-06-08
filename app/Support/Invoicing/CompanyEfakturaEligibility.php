<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;

/**
 * Slovak e-faktura applies to full VAT payers only (not non-payers or §7/§7a partial payers).
 */
final class CompanyEfakturaEligibility
{
    public function __construct(
        protected CompanyVatPolicy $vatPolicy,
    ) {}

    public function supportsCompany(Company $company): bool
    {
        if ($company->jurisdiction !== CompanyJurisdiction::EuSk) {
            return false;
        }

        return $this->vatPolicy->isFullPayer($company);
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return list<string>
     */
    public function efakturaSettingKeys(array $incoming): array
    {
        return array_values(array_filter(array_keys($incoming), static fn (string $key): bool => str_starts_with($key, 'efaktura_')));
    }
}
