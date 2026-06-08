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

function normalizeCountry(country: string | null | undefined): string {
  return (country || '').trim().slice(0, 2).toUpperCase();
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

  function calculatesVatAmounts(company: VatPolicyCompany, contact: VatPolicyContact): boolean {
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
    return isPartialPayer(company) && isForeignSupply(company, contact);
  }

  function defaultTaxRate(company: VatPolicyCompany, _contact?: VatPolicyContact): number {
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
    return requestedRate ?? defaultTaxRate(company, contact);
  }

  return {
    isPartialPayer,
    isFullPayer,
    isDomesticSupply,
    isForeignSupply,
    calculatesVatAmounts,
    showsVatRateColumn,
    defaultTaxRate,
    resolveLineTaxRate,
  };
}
