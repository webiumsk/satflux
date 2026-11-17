import { computed, onScopeDispose, ref, watch, type Ref } from 'vue';
import { createQuery } from '@evolu/common/local-first';
import { sqliteTrue } from '@evolu/common';
import type { CompanyContactRow } from './useCompanyContact';
import { invoicingApi } from '../services/api';
import { useInvoicingEvolu } from '../evolu/client';
import { isInvoicingLocalFirst } from '../evolu/flags';
import { evoluContactToApi, type EvoluContactRow } from '../evolu/contactMap';
import { companyShareRoutingKey, ownerIdForCompany, waitCompanyShareRegistryReady } from '../evolu/companyShareRegistry';
import { loadEvoluQueryWithTimeout, toAppRows } from '../evolu/queryLoad';
import { withTimeout } from '../evolu/asyncTimeout';
import type { CompanyId } from '../evolu/schema';

/** Load this company's optional supplier choices without changing an expense snapshot. */
export function useExpenseSupplierContacts(companyId: Ref<string>) {
  const localFirst = isInvoicingLocalFirst();
  const evolu = localFirst ? useInvoicingEvolu() : null;
  const contacts = ref<CompanyContactRow[]>([]);
  const loading = ref(false);
  const failed = ref(false);
  let generation = 0;
  let disposed = false;
  let controller: AbortController | null = null;
  const routing = computed(() => localFirst ? companyShareRoutingKey(companyId.value) : 'server');

  async function refresh() {
    const company = companyId.value;
    const requested = ++generation;
    controller?.abort();
    controller = new AbortController();
    const signal = controller.signal;
    const current = () => !disposed && requested === generation && company === companyId.value;
    contacts.value = [];
    failed.value = false;
    loading.value = Boolean(company);
    if (!company) return;
    try {
      if (evolu) {
        await withTimeout(waitCompanyShareRegistryReady(signal), 30_000, 'supplier_contacts_timeout');
        if (!current()) return;
        const owner = ownerIdForCompany(company) ?? (await withTimeout(evolu.appOwner, 30_000, 'supplier_contacts_timeout')).id;
        if (!current()) return;
        const query = createQuery(db => db.selectFrom('contact').selectAll()
          .where('companyId', '=', company as CompanyId).where('ownerId', '=', owner)
          .where('isDeleted', 'is not', sqliteTrue));
        const rows = await loadEvoluQueryWithTimeout(evolu, query);
        if (current()) contacts.value = toAppRows<EvoluContactRow>(rows)
          .filter(row => row.companyId === company && row.isActive !== 0)
          .map(evoluContactToApi).sort((a, b) => a.name.localeCompare(b.name));
      } else {
        const result: CompanyContactRow[] = [];
        let page = 1;
        let lastPage = 1;
        do {
          const response = await invoicingApi.contacts.list<CompanyContactRow>(company, { page, per_page: 100 });
          if (!current()) return;
          result.push(...response.data);
          lastPage = response.meta?.last_page ?? page;
          page++;
        } while (page <= lastPage);
        if (current()) contacts.value = result.filter(contact => contact.is_active !== false);
      }
    } catch {
      if (current()) failed.value = true;
    } finally {
      if (controller?.signal === signal) controller.abort();
      if (current()) loading.value = false;
    }
  }

  watch([companyId, routing], () => { void refresh(); }, { immediate: true, flush: 'sync' });
  onScopeDispose(() => { disposed = true; generation++; controller?.abort(); });
  return { contacts, loading, failed, refresh };
}
