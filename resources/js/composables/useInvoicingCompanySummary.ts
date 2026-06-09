import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';

type CompanySummaryCache = {
  name: string;
  hasBankAccount: boolean;
};

const summaryByCompany = new Map<string, CompanySummaryCache>();

export function useInvoicingCompanySummary() {
  const route = useRoute();
  const companyId = computed(() => (route.params.companyId as string) || '');
  const companyName = ref('');
  const hasBankAccount = ref(false);
  const summaryLoaded = ref(false);

  async function loadSummary(id?: string) {
    const cid = id || companyId.value;
    if (!cid) {
      companyName.value = '';
      hasBankAccount.value = false;
      summaryLoaded.value = false;
      return;
    }

    const cached = summaryByCompany.get(cid);
    if (cached) {
      companyName.value = cached.name;
      hasBankAccount.value = cached.hasBankAccount;
      summaryLoaded.value = true;
      return;
    }

    const { data } = await api.get(`/invoicing/companies/${cid}/summary`);
    const name = data.data?.trade_name || data.data?.legal_name || '';
    const bank = !!data.data?.has_bank_account;
    summaryByCompany.set(cid, { name, hasBankAccount: bank });
    companyName.value = name;
    hasBankAccount.value = bank;
    summaryLoaded.value = true;
  }

  function invalidateSummary(id?: string) {
    summaryByCompany.delete(id || companyId.value);
  }

  watch(
    companyId,
    (id) => {
      void loadSummary(id || undefined);
    },
    { immediate: true },
  );

  return {
    companyId,
    companyName,
    hasBankAccount,
    summaryLoaded,
    loadSummary,
    invalidateSummary,
  };
}
