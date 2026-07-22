import { describe, expect, it } from 'vitest';
import { useCompanyVatPolicy, type VatPolicyCompany } from '../composables/useCompanyVatPolicy';

const policy = useCompanyVatPolicy();

function skCompany(vatStatus: 'none' | 'payer' | 'partial'): VatPolicyCompany {
  return {
    country: 'SK',
    jurisdiction: 'eu_sk',
    vat_payer: vatStatus !== 'none',
    vat_status: vatStatus,
    vat_rate_default: 23,
  };
}

const sk = { country: 'SK' };
const cz = { country: 'CZ' };
const us = { country: 'US' };
const freeText = { country: 'Nemecká spolková republika' };

describe('supplyRegion', () => {
  it('classifies domestic, EU and non-EU counterparties', () => {
    const company = skCompany('partial');
    expect(policy.supplyRegion(company, sk)).toBe('domestic');
    expect(policy.supplyRegion(company, null)).toBe('domestic');
    expect(policy.supplyRegion(company, cz)).toBe('eu');
    expect(policy.supplyRegion(company, us)).toBe('non_eu');
  });

  it('never treats an unrecognized country as EU', () => {
    expect(policy.supplyRegion(skCompany('partial'), freeText)).toBe('non_eu');
  });
});

describe('showsVatSummary - the invoice VAT display matrix', () => {
  it('full payer (§4) always shows VAT', () => {
    const company = skCompany('payer');
    expect(policy.showsVatSummary(company, sk)).toBe(true);
    expect(policy.showsVatSummary(company, cz)).toBe(true);
    expect(policy.showsVatSummary(company, us)).toBe(true);
    expect(policy.showsVatSummary(company, null)).toBe(true);
  });

  it('non-payer never shows VAT', () => {
    const company = skCompany('none');
    expect(policy.showsVatSummary(company, sk)).toBe(false);
    expect(policy.showsVatSummary(company, cz)).toBe(false);
    expect(policy.showsVatSummary(company, us)).toBe(false);
  });

  it('§7a shows VAT 0 only for EU counterparties', () => {
    const company = skCompany('partial');
    expect(policy.showsVatSummary(company, sk)).toBe(false);
    expect(policy.showsVatSummary(company, us)).toBe(false);
    expect(policy.showsVatSummary(company, cz)).toBe(true);
  });
});

describe('reverseChargeApplies', () => {
  it('applies only for §7a with an EU counterparty', () => {
    expect(policy.reverseChargeApplies(skCompany('partial'), cz)).toBe(true);
    expect(policy.reverseChargeApplies(skCompany('partial'), sk)).toBe(false);
    expect(policy.reverseChargeApplies(skCompany('partial'), us)).toBe(false);
    expect(policy.reverseChargeApplies(skCompany('partial'), freeText)).toBe(false);
    expect(policy.reverseChargeApplies(skCompany('payer'), cz)).toBe(false);
    expect(policy.reverseChargeApplies(skCompany('none'), cz)).toBe(false);
  });
});

describe('rates stay untouched by the display mode', () => {
  it('§7a never charges VAT, even on the EU reverse-charge invoice', () => {
    const company = skCompany('partial');
    expect(policy.resolveLineTaxRate(company, cz, 23)).toBe(0);
    expect(policy.resolveLineTaxRate(company, sk, 23)).toBe(0);
  });

  it('§4 payer charges the requested or default rate', () => {
    const company = skCompany('payer');
    expect(policy.resolveLineTaxRate(company, cz, 10)).toBe(10);
    expect(policy.resolveLineTaxRate(company, cz, null)).toBe(23);
  });

  it('the VAT rate column follows the same tiering for §7a', () => {
    const company = skCompany('partial');
    expect(policy.showsVatRateColumn(company, cz)).toBe(true);
    expect(policy.showsVatRateColumn(company, sk)).toBe(false);
    expect(policy.showsVatRateColumn(company, us)).toBe(false);
  });
});
