import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';

type CompanySummaryCache = {
  name: string;
  hasBankAccount: boolean;
  bankAccountLabel: string;
  defaultCurrency: string;
};

const summaryByCompany = new Map<string, CompanySummaryCache>();

export function useInvoicingCompanySummary() {
  const route = useRoute();
  const companyId = computed(() => (route.params.companyId as string) || '');
  const companyName = ref('');
  const hasBankAccount = ref(false);
  const bankAccountLabel = ref('');
  const defaultCurrency = ref('EUR');
  const summaryLoaded = ref(false);

  async function loadSummary(id?: string) {
    const cid = id || companyId.value;
    if (!cid) {
      companyName.value = '';
      hasBankAccount.value = false;
      bankAccountLabel.value = '';
      defaultCurrency.value = 'EUR';
      summaryLoaded.value = false;
      return;
    }

    const cached = summaryByCompany.get(cid);
    if (cached) {
      companyName.value = cached.name;
      hasBankAccount.value = cached.hasBankAccount;
      bankAccountLabel.value = cached.bankAccountLabel;
      defaultCurrency.value = cached.defaultCurrency;
      summaryLoaded.value = true;
      return;
    }

    const { data } = await api.get(`/invoicing/companies/${cid}/summary`);
    const name = data.data?.trade_name || data.data?.legal_name || '';
    const bank = !!data.data?.has_bank_account;
    const label = data.data?.bank_account_label || '';
    const currency = data.data?.default_currency || 'EUR';
    summaryByCompany.set(cid, { name, hasBankAccount: bank, bankAccountLabel: label, defaultCurrency: currency });
    companyName.value = name;
    hasBankAccount.value = bank;
    bankAccountLabel.value = label;
    defaultCurrency.value = currency;
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
    bankAccountLabel,
    defaultCurrency,
    summaryLoaded,
    loadSummary,
    invalidateSummary,
  };
}
