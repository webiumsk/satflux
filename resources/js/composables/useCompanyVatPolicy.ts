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
  vat_id?: string | null;
} | null;

// Full-string normalization (mirrors the server CompanyVatPolicy): a free
// text like "Slovensko" must never truncate to a different ISO2 code, and
// the Greek VIES alias 'EL' canonicalizes to 'GR' so it never misclassifies
// a domestic supply as cross-border.
function normalizeCountry(country: string | null | undefined): string {
  const code = (country || '').trim().toUpperCase();
  return code === 'EL' ? 'GR' : code;
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

  /**
   * §4 full payer invoicing a VAT-registered business in another EU state
   * (counterparty has IČ DPH): the tax liability transfers to the buyer,
   * so no VAT is charged and the invoice carries the reverse-charge note.
   * B2C EU supplies (no vat_id) keep normal VAT.
   */
  function euB2bReverseCharge(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    return (
      isFullPayer(company)
      && supplyRegion(company, contact) === 'eu'
      && (contact?.vat_id || '').trim() !== ''
    );
  }

  function calculatesVatAmounts(company: VatPolicyCompany, contact: VatPolicyContact = null): boolean {
    if (company?.jurisdiction === 'us') {
      return true;
    }
    return isFullPayer(company) && !euB2bReverseCharge(company, contact);
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

  /**
   * The invoice carries the reverse-charge note: §7a with any EU
   * counterparty, or §4 full payer with an EU VAT-registered business.
   */
  function reverseChargeApplies(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
    return (
      (isPartialPayer(company) && supplyRegion(company, contact) === 'eu')
      || euB2bReverseCharge(company, contact)
    );
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
    if (!calculatesVatAmounts(company, contact)) {
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
    euB2bReverseCharge,
    calculatesVatAmounts,
    showsVatRateColumn,
    showsVatSummary,
    reverseChargeApplies,
    defaultTaxRate,
    resolveLineTaxRate,
  };
}
