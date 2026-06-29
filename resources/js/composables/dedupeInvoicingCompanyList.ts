import { normalizeCompanyIdentityKey } from '@/evolu/duplicateCompanies';
import type { InvoicingCompanyListItem } from './useInvoicingCompanies';
import { invoicingCompanyLabel } from './invoicingCompanyLabel';

function pickPreferredCompany(
  group: InvoicingCompanyListItem[],
  preferredId?: string,
): InvoicingCompanyListItem {
  if (group.length === 1) {
    return group[0];
  }
  const preferred = preferredId ? group.find((c) => c.id === preferredId) : undefined;
  if (preferred) {
    return preferred;
  }
  return [...group].sort((a, b) => (b.documents_count ?? 0) - (a.documents_count ?? 0))[0];
}

/** Collapse duplicate identity rows (common after Evolu sync) for switcher UI. */
export function dedupeInvoicingCompanyList(
  companies: readonly InvoicingCompanyListItem[],
  preferredId?: string,
): InvoicingCompanyListItem[] {
  const byId = new Map<string, InvoicingCompanyListItem>();
  for (const company of companies) {
    if (!byId.has(company.id)) {
      byId.set(company.id, company);
    }
  }

  const groups = new Map<string, InvoicingCompanyListItem[]>();
  for (const company of byId.values()) {
    const key = normalizeCompanyIdentityKey(
      company.legal_name,
      company.registration_number ?? null,
    );
    const bucket = groups.get(key) ?? [];
    bucket.push(company);
    groups.set(key, bucket);
  }

  return [...groups.values()]
    .map((group) => pickPreferredCompany(group, preferredId))
    .sort((a, b) => invoicingCompanyLabel(a).localeCompare(invoicingCompanyLabel(b), undefined, { sensitivity: 'base' }));
}
