import { describe, expect, it } from 'vitest';
import {
  COMPANY_JURISDICTION_VALUES,
  ENABLED_COMPANY_JURISDICTIONS,
  isCompanyJurisdictionEnabled,
} from '../config/companyJurisdiction';

describe('jurisdiction gating', () => {
  it('only SK, CZ, DE and US are enabled', () => {
    expect([...ENABLED_COMPANY_JURISDICTIONS]).toEqual(['eu_sk', 'eu_cz', 'eu_de', 'us']);
    for (const value of COMPANY_JURISDICTION_VALUES) {
      expect(isCompanyJurisdictionEnabled(value)).toBe(
        (ENABLED_COMPANY_JURISDICTIONS as readonly string[]).includes(value),
      );
    }
  });

  it('unknown or empty values are not enabled', () => {
    expect(isCompanyJurisdictionEnabled('')).toBe(false);
    expect(isCompanyJurisdictionEnabled(null)).toBe(false);
    expect(isCompanyJurisdictionEnabled(undefined)).toBe(false);
    expect(isCompanyJurisdictionEnabled('mars')).toBe(false);
  });
});
