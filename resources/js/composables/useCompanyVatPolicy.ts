import { countrySupportsVatPayer } from '@/config/euVatCountries';

export type VatPolicyCompany = {
  country?: string | null;
  jurisdiction?: string | null;
  vat_payer?: boolean;
  vat_status?: 'none' | 'payer' | 'partial' | string | null;
  vat_rate_default?: number | string | null;
} | null;

export type VatPolicyContact = {
  country?: string | null;
} | null;

// Full-string normalization (mirrors the server CompanyVatPolicy): a free
// text like "Slovensko" must never truncate to a different ISO2 code.
function normalizeCountry(country: string | null | undefined): string {
  return (country || '').trim().toUpperCase();
}

export function resolveCompanyVatStatus(company: VatPolicyCompany): 'none' | 'payer' | 'partial' {
  const status = company?.vat_status;
  if (status === 'payer' || status === 'partial') {
    return status;
  }
  if (!status || status === 'none') {
    return company?.vat_payer ? 'payer' : 'none';
  }
  return company?.vat_payer ? 'payer' : 'none';
}

export function isFullVatPayer(company: VatPolicyCompany): boolean {
  return resolveCompanyVatStatus(company) === 'payer';
}

export function useCompanyVatPolicy() {
  function isPartialPayer(company: VatPolicyCompany): boolean {
    return resolveCompanyVatStatus(company) === 'partial';
  }

  function isFullPayer(company: VatPolicyCompany): boolean {
    return isFullVatPayer(company);
  }

  function isDomesticSupply(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    if (!contact?.country) {
      return true;
    }
    const seller = normalizeCountry(company?.country || 'SK');
    const buyer = normalizeCountry(contact.country);
    if (!seller || !buyer) {
      return true;
    }
    return seller === buyer;
  }

  function isForeignSupply(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    return !isDomesticSupply(company, contact);
  }

  /**
   * Counterparty tier for VAT display (mirrors the server CompanyVatPolicy):
   * 'domestic' (same country or empty), 'eu' (EU member other than the
   * supplier's country) or 'non_eu'. An unrecognized country falls to
   * 'non_eu' - it is never treated as an EU reverse-charge case.
   */
  function supplyRegion(
    company: VatPolicyCompany,
    contact: VatPolicyContact,
  ): 'domestic' | 'eu' | 'non_eu' {
    if (isDomesticSupply(company, contact)) {
      return 'domestic';
    }
    const buyer = normalizeCountry(contact?.country);
    if (buyer.length === 2 && countrySupportsVatPayer(buyer)) {
      return 'eu';
    }
    return 'non_eu';
  }

  function calculatesVatAmounts(company: VatPolicyCompany): boolean {
    if (company?.jurisdiction === 'us') {
      return true;
    }
    return isFullPayer(company);
  }

  function showsVatRateColumn(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    if (company?.jurisdiction === 'us') {
      return true;
    }
    if (isFullPayer(company)) {
      return true;
    }
    // §7a: the rate column only appears on EU reverse-charge invoices.
    return isPartialPayer(company) && supplyRegion(company, contact) === 'eu';
  }

  /**
   * Whether the invoice shows the VAT summary block (subtotal + VAT rows).
   * §4 payer: always. Non-payer: never. §7a: only for EU (non-domestic)
   * counterparties, where VAT 0 is shown next to the reverse-charge note.
   */
  function showsVatSummary(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    if (company?.jurisdiction === 'us') {
      return true;
    }
    if (isFullPayer(company)) {
      return true;
    }
    return isPartialPayer(company) && supplyRegion(company, contact) === 'eu';
  }

  /** §7a EU supply: the invoice carries the reverse-charge note. */
  function reverseChargeApplies(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    return isPartialPayer(company) && supplyRegion(company, contact) === 'eu';
  }

  function defaultTaxRate(company: VatPolicyCompany): number {
    if (company?.jurisdiction === 'us') {
      return Number(company?.vat_rate_default ?? 0);
    }
    if (isFullPayer(company)) {
      return Number(company?.vat_rate_default ?? 0);
    }
    return 0;
  }

  function resolveLineTaxRate(
    company: VatPolicyCompany,
    contact: VatPolicyContact,
    requestedRate?: number | null,
  ): number {
    if (!calculatesVatAmounts(company)) {
      return 0;
    }
    return requestedRate ?? defaultTaxRate(company);
  }

  return {
    isPartialPayer,
    isFullPayer,
    isDomesticSupply,
    isForeignSupply,
    supplyRegion,
    calculatesVatAmounts,
    showsVatRateColumn,
    showsVatSummary,
    reverseChargeApplies,
    defaultTaxRate,
    resolveLineTaxRate,
  };
}
