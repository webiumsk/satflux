import type { InvoicingCompanyListItem } from './useInvoicingCompanies';

export function invoicingCompanyLabel(company: Pick<InvoicingCompanyListItem, 'legal_name' | 'trade_name'>): string {
  return company.trade_name || company.legal_name;
}
