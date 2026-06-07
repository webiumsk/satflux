/** ISO 3166-1 alpha-2 → typical invoicing currency for company defaults. */
const DEFAULT_CURRENCY_BY_COUNTRY: Record<string, string> = {
  SK: 'EUR',
  CZ: 'CZK',
  PL: 'PLN',
  AT: 'EUR',
  DE: 'EUR',
  FR: 'EUR',
  IT: 'EUR',
  ES: 'EUR',
  NL: 'EUR',
  BE: 'EUR',
  IE: 'EUR',
  PT: 'EUR',
  FI: 'EUR',
  CY: 'EUR',
  CH: 'CHF',
  GB: 'GBP',
  HU: 'HUF',
  US: 'USD',
  HK: 'HKD',
  GI: 'GBP',
  KY: 'USD',
  PA: 'USD',
};

/** Fiat codes offered when creating or editing a company (invoicing). */
export const COMPANY_CURRENCY_CODES = [
  'EUR',
  'CZK',
  'PLN',
  'HUF',
  'CHF',
  'GBP',
  'USD',
  'HKD',
] as const;

export type CompanyCurrencyCode = (typeof COMPANY_CURRENCY_CODES)[number];

export function defaultCurrencyForCountry(country: string | null | undefined): CompanyCurrencyCode {
  const code = (country || 'SK').trim().toUpperCase();
  const mapped = DEFAULT_CURRENCY_BY_COUNTRY[code];
  if (mapped && isCompanyCurrency(mapped)) {
    return mapped;
  }
  return 'EUR';
}

export function defaultCurrencyForJurisdiction(
  jurisdiction: string,
  country?: string | null
): CompanyCurrencyCode {
  if (jurisdiction === 'us' || jurisdiction === 'offshore') return 'USD';
  if (jurisdiction === 'eu_cz') return 'CZK';
  if (jurisdiction === 'eu_sk') return 'EUR';
  if (jurisdiction === 'uk') return 'GBP';
  if (jurisdiction === 'asia') return defaultCurrencyForCountry(country || 'HK');
  return defaultCurrencyForCountry(country);
}

export function isCompanyCurrency(code: string): code is CompanyCurrencyCode {
  return (COMPANY_CURRENCY_CODES as readonly string[]).includes(code);
}

/**
 * Update default currency when country/jurisdiction changes, unless the user already picked another code.
 */
export function syncCompanyDefaultCurrency(
  current: string,
  country: string,
  jurisdiction: string,
  previousCountry?: string
): string {
  const next = defaultCurrencyForJurisdiction(jurisdiction, country);
  if (!current || !isCompanyCurrency(current)) {
    return next;
  }
  if (previousCountry) {
    const prevDefault = defaultCurrencyForCountry(previousCountry);
    if (current === prevDefault) {
      return next;
    }
    const prevJurisdictionDefault = defaultCurrencyForJurisdiction(
      jurisdiction === 'us' ? 'eu_other' : jurisdiction,
      previousCountry
    );
    if (current === prevJurisdictionDefault) {
      return next;
    }
  }
  return current;
}

/** Options for &lt;select&gt;, keeping a custom stored value if needed. */
export function companyCurrencyOptions(current?: string | null): string[] {
  const codes = new Set<string>(COMPANY_CURRENCY_CODES);
  const cur = (current || '').trim().toUpperCase();
  if (cur && !codes.has(cur)) {
    codes.add(cur);
  }
  return [...codes].sort((a, b) => a.localeCompare(b));
}
