export type CompanyJurisdictionValue =
  | 'eu_sk'
  | 'eu_cz'
  | 'eu_other'
  | 'us'
  | 'uk'
  | 'offshore'
  | 'asia';

export const COMPANY_JURISDICTION_VALUES: CompanyJurisdictionValue[] = [
  'eu_sk',
  'eu_cz',
  'eu_other',
  'uk',
  'offshore',
  'asia',
  'us',
];

const UK_COUNTRIES = new Set(['GB', 'UK']);
const ASIA_COUNTRIES = new Set(['HK', 'SG', 'AE']);
const OFFSHORE_COUNTRIES = new Set(['GI', 'KY', 'PA', 'VG', 'BM', 'LU', 'MT', 'EE', 'LV', 'LT']);

const DEFAULT_COUNTRY_BY_JURISDICTION: Record<CompanyJurisdictionValue, string> = {
  eu_sk: 'SK',
  eu_cz: 'CZ',
  eu_other: 'DE',
  us: 'US',
  uk: 'GB',
  offshore: 'GI',
  asia: 'HK',
};

/** i18n keys under invoicing.jurisdiction_* */
export const JURISDICTION_I18N_KEYS: Record<CompanyJurisdictionValue, string> = {
  eu_sk: 'jurisdiction_sk',
  eu_cz: 'jurisdiction_cz',
  eu_other: 'jurisdiction_eu',
  us: 'jurisdiction_us',
  uk: 'jurisdiction_uk',
  offshore: 'jurisdiction_offshore',
  asia: 'jurisdiction_asia',
};

export function isUsJurisdiction(jurisdiction: string): boolean {
  return jurisdiction === 'us';
}

export function jurisdictionFromCountry(country: string | null | undefined): CompanyJurisdictionValue {
  const c = (country || '').trim().toUpperCase();
  if (c === 'SK') return 'eu_sk';
  if (c === 'CZ') return 'eu_cz';
  if (c === 'US' || c === 'USA') return 'us';
  if (UK_COUNTRIES.has(c)) return 'uk';
  if (ASIA_COUNTRIES.has(c)) return 'asia';
  if (OFFSHORE_COUNTRIES.has(c)) return 'offshore';
  return 'eu_other';
}

export function defaultCountryForJurisdiction(jurisdiction: string): string {
  return DEFAULT_COUNTRY_BY_JURISDICTION[jurisdiction as CompanyJurisdictionValue] ?? 'SK';
}

export function countriesForJurisdiction(jurisdiction: string): string[] {
  switch (jurisdiction) {
    case 'eu_sk':
      return ['SK'];
    case 'eu_cz':
      return ['CZ'];
    case 'us':
      return ['US'];
    case 'uk':
      return ['GB'];
    case 'asia':
      return ['HK', 'SG', 'AE'];
    case 'offshore':
      return ['GI', 'KY', 'PA', 'VG', 'BM', 'LU', 'MT', 'EE', 'LV', 'LT'];
    default:
      return [
        'SK',
        'CZ',
        'PL',
        'AT',
        'DE',
        'FR',
        'IT',
        'ES',
        'NL',
        'BE',
        'CH',
        'IE',
        'FI',
        'CY',
        'HU',
        'PT',
      ];
  }
}

export function countryAllowedForJurisdiction(country: string, jurisdiction: string): boolean {
  const allowed = countriesForJurisdiction(jurisdiction);
  return allowed.includes((country || '').trim().toUpperCase());
}
