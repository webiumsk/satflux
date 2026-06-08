/** Comparison matrix for landing invoicing section (Satflux vs Zaprite vs EU accounting SaaS). */
export type CompareValue = 'yes' | 'partial' | 'no' | 'pro';

export interface InvoicingCompareRow {
  id: string;
  labelKey: string;
  satflux: CompareValue;
  zaprite: CompareValue;
  euAccounting: CompareValue;
  noteKey?: string;
}

export const INVOICING_COMPARE_ROWS: InvoicingCompareRow[] = [
  {
    id: 'btc_ln_checkout',
    labelKey: 'landing.invoicing_section.compare.rows.btc_ln_checkout',
    satflux: 'yes',
    zaprite: 'yes',
    euAccounting: 'no',
  },
  {
    id: 'pay_by_square',
    labelKey: 'landing.invoicing_section.compare.rows.pay_by_square',
    satflux: 'yes',
    zaprite: 'no',
    euAccounting: 'yes',
    noteKey: 'landing.invoicing_section.compare.notes.pay_by_square',
  },
  {
    id: 'sk_cz_compliance',
    labelKey: 'landing.invoicing_section.compare.rows.sk_cz_compliance',
    satflux: 'partial',
    zaprite: 'no',
    euAccounting: 'yes',
    noteKey: 'landing.invoicing_section.compare.notes.sk_cz_compliance',
  },
  {
    id: 'isdoc_efaktura',
    labelKey: 'landing.invoicing_section.compare.rows.isdoc_efaktura',
    satflux: 'partial',
    zaprite: 'no',
    euAccounting: 'yes',
    noteKey: 'landing.invoicing_section.compare.notes.isdoc_efaktura',
  },
  {
    id: 'bank_matching',
    labelKey: 'landing.invoicing_section.compare.rows.bank_matching',
    satflux: 'yes',
    zaprite: 'partial',
    euAccounting: 'yes',
  },
  {
    id: 'expenses',
    labelKey: 'landing.invoicing_section.compare.rows.expenses',
    satflux: 'yes',
    zaprite: 'no',
    euAccounting: 'yes',
  },
  {
    id: 'recurring',
    labelKey: 'landing.invoicing_section.compare.rows.recurring',
    satflux: 'yes',
    zaprite: 'partial',
    euAccounting: 'yes',
  },
  {
    id: 'btcpay_stores',
    labelKey: 'landing.invoicing_section.compare.rows.btcpay_stores',
    satflux: 'yes',
    zaprite: 'no',
    euAccounting: 'no',
  },
  {
    id: 'us_llc_profile',
    labelKey: 'landing.invoicing_section.compare.rows.us_llc_profile',
    satflux: 'yes',
    zaprite: 'yes',
    euAccounting: 'no',
  },
  {
    id: 'self_custody',
    labelKey: 'landing.invoicing_section.compare.rows.self_custody',
    satflux: 'yes',
    zaprite: 'partial',
    euAccounting: 'no',
  },
];

export const INVOICING_FEATURE_BULLETS = [
  'landing.invoicing_section.bullets.companies',
  'landing.invoicing_section.bullets.pay_by_square',
  'landing.invoicing_section.bullets.structured_invoicing',
  'landing.invoicing_section.bullets.btc_pay_link',
  'landing.invoicing_section.bullets.bank_matching',
  'landing.invoicing_section.bullets.expenses',
  'landing.invoicing_section.bullets.recurring',
] as const;
