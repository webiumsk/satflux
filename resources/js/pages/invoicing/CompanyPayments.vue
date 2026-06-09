<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :show-filter-bar="activeTab === 'transactions' && hasBankAccount">
        <template #filters>
          <select v-model="matchFilter" class="invoicing-sf-input max-w-[180px]" @change="load">
            <option value="unmatched">{{ t('invoicing.bank_filter_unmatched') }}</option>
            <option value="matched">{{ t('invoicing.bank_filter_matched') }}</option>
            <option value="ignored">{{ t('invoicing.bank_filter_ignored') }}</option>
          </select>
        </template>
        <template #actions>
          <button type="button" class="invoicing-btn-secondary" @click="activeTab = 'import'">
            {{ t('invoicing.bank_tab_import') }}
          </button>
        </template>
      </InvoicingAppHeader>
    </template>

    <div v-if="!summaryLoaded" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="!hasBankAccount" class="invoicing-card-pad max-w-xl">
      <h1 class="invoicing-title mb-2">{{ t('invoicing.main_nav_payments') }}</h1>
      <p class="text-sm text-gray-600 mb-4">{{ t('invoicing.bank_no_bank_account') }}</p>
      <RouterLink
        :to="{ name: 'invoicing-company', params: { companyId: companyId } }"
        class="invoicing-btn-primary inline-flex"
      >
        {{ t('invoicing.bank_setup_bank_account') }}
      </RouterLink>
    </div>

    <template v-else>
      <h1 class="invoicing-title mb-4">{{ t('invoicing.main_nav_payments') }}</h1>

      <div class="flex gap-2 mb-4 border-b border-gray-200">
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium"
          :class="activeTab === 'transactions' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-600'"
          @click="activeTab = 'transactions'"
        >
          {{ t('invoicing.bank_tab_transactions') }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium"
          :class="activeTab === 'import' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-600'"
          @click="activeTab = 'import'"
        >
          {{ t('invoicing.bank_tab_import') }}
        </button>
      </div>

      <div v-if="activeTab === 'import'" class="invoicing-card-pad space-y-4 max-w-xl">
        <p class="text-sm text-gray-600">{{ t('invoicing.bank_import_help') }}</p>
        <form class="space-y-3" @submit.prevent="upload">
          <input type="file" accept=".csv,.txt,.xml" class="block w-full text-sm" @change="onFile" />
          <select v-model="importFormat" class="invoicing-sf-input w-full">
            <option value="">{{ t('invoicing.bank_format_auto') }}</option>
            <option value="csv">CSV</option>
            <option value="camt053">CAMT.053 XML</option>
          </select>
          <button type="submit" class="invoicing-btn-primary" :disabled="!importFile || importing">
            {{ importing ? t('common.loading') : t('invoicing.bank_import_submit') }}
          </button>
        </form>
        <div v-if="inboundEmail" class="text-sm border-t pt-4">
          <p class="font-medium text-gray-800">{{ t('invoicing.bank_inbound_title') }}</p>
          <p class="text-gray-600 mt-1">{{ t('invoicing.bank_inbound_help') }}</p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <code class="flex-1 min-w-0 p-2 bg-gray-100 rounded text-xs break-all">{{ inboundEmail }}</code>
            <button type="button" class="invoicing-btn-secondary text-xs shrink-0" @click="copyInboundEmail">
              <span aria-live="polite" aria-atomic="true">
                {{ inboundCopied ? t('invoicing.bank_inbound_copied') : t('invoicing.bank_inbound_copy') }}
              </span>
            </button>
          </div>
          <p v-if="inboundMaxLength" class="text-gray-500 text-xs mt-1">
            {{ t('invoicing.bank_inbound_length', { current: inboundLength, max: inboundMaxLength }) }}
          </p>
          <p v-if="!inboundEnabled" class="text-amber-700 text-xs mt-1">{{ t('invoicing.bank_inbound_disabled') }}</p>
        </div>
        <ul v-if="batches.length" class="text-sm space-y-2 border-t pt-4">
          <li v-for="b in batches" :key="b.id" class="flex justify-between gap-2">
            <span>{{ b.filename || b.source }} · {{ b.imported_count }}/{{ b.row_count }}</span>
            <button
              v-if="b.imported_count > 0"
              type="button"
              class="text-indigo-600 hover:underline"
              @click="autoMatchBatch(b.id)"
            >
              {{ t('invoicing.bank_auto_match_batch') }}
            </button>
          </li>
        </ul>
        <p v-if="importResult" class="text-sm text-green-700">
          {{ t('invoicing.bank_import_result', importResult) }}
        </p>
      </div>

      <div v-else>
        <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>
        <div v-else-if="transactions.length === 0" class="invoicing-card-pad text-center text-gray-600">
          {{ t('invoicing.bank_no_transactions') }}
        </div>
        <div v-else class="invoicing-card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm bank-movements-table">
              <thead class="bg-gray-50 border-b text-gray-600 text-xs uppercase tracking-wide">
                <tr>
                  <th class="w-3 p-0"></th>
                  <th class="text-left p-3">{{ t('invoicing.bank_col_amount_date') }}</th>
                  <th class="text-left p-3">{{ t('invoicing.bank_col_vs') }}</th>
                  <th class="text-left p-3">{{ t('invoicing.bank_col_counterparty') }}</th>
                  <th class="text-left p-3">{{ t('invoicing.bank_col_document') }}</th>
                  <th class="p-3 text-right">{{ t('invoicing.bank_col_actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="tx in transactions"
                  :key="tx.id"
                  class="border-b hover:bg-gray-50 bank-movement-row"
                  :class="tx.direction === 'credit' ? 'bank-movement-row--credit' : 'bank-movement-row--debit'"
                >
                  <td class="relative w-3 p-0 align-stretch">
                    <span
                      class="bank-movement-corner"
                      :class="tx.direction === 'credit' ? 'bank-movement-corner--credit' : 'bank-movement-corner--debit'"
                    ></span>
                  </td>
                  <td class="p-3 whitespace-nowrap">
                    <div
                      class="font-semibold tabular-nums"
                      :class="tx.direction === 'credit' ? 'text-emerald-700' : 'text-red-600'"
                    >
                      {{ formatSignedAmount(tx) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ formatDate(tx.booked_at) }}</div>
                  </td>
                  <td class="p-3">{{ tx.variable_symbol || '—' }}</td>
                  <td class="p-3 max-w-[240px]">
                    <div class="truncate">{{ tx.counterparty_name || '—' }}</div>
                    <div v-if="tx.reference" class="text-xs text-gray-500 truncate mt-0.5">{{ tx.reference }}</div>
                  </td>
                  <td class="p-3">
                    <RouterLink
                      v-if="linkedDocument(tx)"
                      :to="documentShowTo(linkedDocument(tx)!)"
                      class="text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1"
                    >
                      {{ linkedDocument(tx)!.number }}
                      <span aria-hidden="true">↗</span>
                    </RouterLink>
                    <RouterLink
                      v-else-if="linkedExpense(tx)"
                      :to="{ name: 'invoicing-expense-show', params: { companyId, expenseId: linkedExpense(tx)!.id } }"
                      class="text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1"
                    >
                      {{ linkedExpense(tx)!.internal_number }}
                      <span aria-hidden="true">↗</span>
                    </RouterLink>
                    <span v-else-if="tx.match_status === 'ignored'" class="text-gray-500">
                      {{ t('invoicing.bank_status_ignored') }}
                    </span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="p-3 text-right space-x-2 whitespace-nowrap">
                    <template v-if="tx.match_status === 'unmatched'">
                      <button type="button" class="bank-action-link" @click="ignoreTx(tx)">
                        {{ t('invoicing.bank_ignore') }}
                      </button>
                      <button
                        v-if="tx.direction === 'debit'"
                        type="button"
                        class="bank-action-link"
                        @click="openCreateExpense(tx)"
                      >
                        {{ t('invoicing.bank_create_expense') }}
                      </button>
                      <button
                        v-if="tx.direction === 'credit'"
                        type="button"
                        class="bank-action-link bank-action-link--primary"
                        @click="openMatch(tx)"
                      >
                        {{ t('invoicing.bank_match') }}
                      </button>
                    </template>
                    <button
                      v-else-if="tx.match_status === 'matched'"
                      type="button"
                      class="bank-action-link"
                      @click="unmatchTx(tx)"
                    >
                      {{ t('invoicing.bank_unmatch') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <BankCreateExpenseModal
      :open="!!expenseModalTx"
      :currency="expenseModalTx?.currency || 'EUR'"
      :initial="expenseDraft"
      :saving="creatingExpense"
      :error="expenseError"
      @close="closeCreateExpense"
      @submit="submitCreateExpense"
    />

    <div
      v-if="matchModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="matchModal = null"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-4 space-y-3">
        <h3 class="font-semibold">{{ t('invoicing.bank_match_title') }}</h3>
        <p class="text-sm text-gray-600">
          VS {{ matchModal.variable_symbol || '—' }} · {{ formatSignedAmount(matchModal) }}
        </p>
        <div v-if="suggestionsLoading" class="text-sm">{{ t('common.loading') }}</div>
        <ul v-else class="max-h-60 overflow-y-auto space-y-2 text-sm">
          <li v-for="s in suggestions" :key="s.document.id" class="flex justify-between gap-2 border rounded p-2">
            <span>
              {{ s.document.number }} · {{ formatMoney(s.document.total, s.document.currency) }}
              <span v-if="s.reason !== 'variable_symbol'" class="text-amber-600 text-xs">({{ s.reason }})</span>
            </span>
            <button type="button" class="text-indigo-600 shrink-0" @click="confirmMatch(s.document.id)">
              {{ t('invoicing.bank_match_confirm') }}
            </button>
          </li>
        </ul>
        <p v-if="!suggestionsLoading && suggestions.length === 0" class="text-sm text-gray-500">
          {{ t('invoicing.bank_no_suggestions') }}
        </p>
        <button type="button" class="invoicing-btn-secondary w-full" @click="matchModal = null">
          {{ t('common.cancel') }}
        </button>
      </div>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import BankCreateExpenseModal, { type BankExpenseDraft } from '../../components/invoicing/BankCreateExpenseModal.vue';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { useInvoicingCompanySummary } from '../../composables/useInvoicingCompanySummary';
import { invoicingDocumentRoutesForType } from '../../composables/useInvoicingDocumentRoutes';
import { useFlashStore } from '../../store/flash';

type LinkedDocument = { id: string; number?: string; type?: string };
type LinkedExpense = { id: string; internal_number: string };

type BankTx = {
  id: string;
  booked_at: string;
  amount: string;
  currency: string;
  direction: string;
  variable_symbol?: string | null;
  counterparty_name?: string | null;
  reference?: string | null;
  match_status: string;
  match?: {
    document?: LinkedDocument | null;
  };
  expense?: LinkedExpense | null;
};

const { t } = useI18n();
const route = useRoute();
const { rememberCompany } = useInvoicingLayout();
const { hasBankAccount, summaryLoaded } = useInvoicingCompanySummary();
const flashStore = useFlashStore();

const companyId = computed(() => route.params.companyId as string);
const loading = ref(true);
const transactions = ref<BankTx[]>([]);
const matchFilter = ref('unmatched');
const activeTab = ref<'transactions' | 'import'>('transactions');
const importFile = ref<File | null>(null);
const importFormat = ref('');
const importing = ref(false);
const importResult = ref<{ imported: number; auto_matched: number } | null>(null);
const batches = ref<
  { id: string; filename?: string; source: string; imported_count: number; row_count: number }[]
>([]);
const inboundEmail = ref('');
const inboundEnabled = ref(false);
const inboundLength = ref(0);
const inboundMaxLength = ref(0);
const inboundCopied = ref(false);
const matchModal = ref<BankTx | null>(null);
const suggestions = ref<{ document: { id: string; number?: string; total: string; currency: string }; reason: string }[]>([]);
const suggestionsLoading = ref(false);
const expenseModalTx = ref<BankTx | null>(null);
const creatingExpense = ref(false);
const expenseError = ref('');
const expenseDraft = ref<BankExpenseDraft>({
  title: '',
  total: 0,
  variable_symbol: '',
  supplier: '',
  category: '',
  internal_note: '',
  issue_date: '',
});

onMounted(async () => {
  rememberCompany(companyId.value);
  await Promise.all([load(), loadBatches(), loadInbound()]);
});

watch(companyId, () => {
  load();
  loadBatches();
  loadInbound();
});

watch(activeTab, (tab) => {
  if (tab === 'transactions') load();
});

function linkedDocument(tx: BankTx): LinkedDocument | null {
  return tx.match?.document?.id ? tx.match.document : null;
}

function linkedExpense(tx: BankTx): LinkedExpense | null {
  return tx.expense?.id ? tx.expense : null;
}

function documentShowTo(doc: LinkedDocument) {
  const routes = invoicingDocumentRoutesForType(doc.type || 'invoice');
  return { name: routes.show, params: { companyId: companyId.value, documentId: doc.id } };
}

async function load() {
  if (!hasBankAccount.value) {
    loading.value = false;
    return;
  }

  loading.value = true;
  try {
    const { data } = await api.get(`/invoicing/companies/${companyId.value}/bank-transactions`, {
      params: { match_status: matchFilter.value, per_page: 50 },
    });
    transactions.value = data.data || [];
  } finally {
    loading.value = false;
  }
}

watch(hasBankAccount, (value) => {
  if (value) load();
});

async function loadBatches() {
  if (!hasBankAccount.value) return;
  const { data } = await api.get(`/invoicing/companies/${companyId.value}/bank-transactions/batches`);
  batches.value = data.data || [];
}

async function loadInbound() {
  if (!hasBankAccount.value) return;
  try {
    const { data } = await api.get(`/invoicing/companies/${companyId.value}/bank-transactions/inbound-email`);
    inboundEmail.value = data.data?.address || '';
    inboundEnabled.value = !!data.data?.enabled;
    inboundLength.value = data.data?.length ?? inboundEmail.value.length;
    inboundMaxLength.value = data.data?.max_length ?? 50;
    inboundCopied.value = false;
  } catch {
    inboundEmail.value = '';
    inboundLength.value = 0;
    inboundMaxLength.value = 0;
  }
}

async function copyInboundEmail() {
  if (!inboundEmail.value) return;
  try {
    await navigator.clipboard.writeText(inboundEmail.value);
    inboundCopied.value = true;
    window.setTimeout(() => {
      inboundCopied.value = false;
    }, 2000);
  } catch (err) {
    console.error('Failed to copy b-mail address:', err);
    flashStore.error(t('invoicing.bank_inbound_copy_failed'));
  }
}

function onFile(e: Event) {
  const input = e.target as HTMLInputElement;
  importFile.value = input.files?.[0] ?? null;
}

async function upload() {
  if (!importFile.value) return;
  importing.value = true;
  importResult.value = null;
  try {
    const form = new FormData();
    form.append('file', importFile.value);
    if (importFormat.value) form.append('format', importFormat.value);
    const { data } = await api.post(
      `/invoicing/companies/${companyId.value}/bank-transactions/import`,
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
    importResult.value = {
      imported: data.data.imported,
      auto_matched: data.data.auto_matched,
    };
    importFile.value = null;
    await loadBatches();
    activeTab.value = 'transactions';
    await load();
  } catch (e: any) {
    alert(e?.response?.data?.message || t('common.error'));
  } finally {
    importing.value = false;
  }
}

async function autoMatchBatch(batchId: string) {
  await api.post(
    `/invoicing/companies/${companyId.value}/bank-transactions/batches/${batchId}/auto-match`,
  );
  await load();
}

async function openMatch(tx: BankTx) {
  matchModal.value = tx;
  suggestionsLoading.value = true;
  suggestions.value = [];
  try {
    const { data } = await api.get(
      `/invoicing/companies/${companyId.value}/bank-transactions/${tx.id}/suggestions`,
    );
    suggestions.value = data.data || [];
  } finally {
    suggestionsLoading.value = false;
  }
}

async function confirmMatch(documentId: string) {
  if (!matchModal.value) return;
  await api.post(
    `/invoicing/companies/${companyId.value}/bank-transactions/${matchModal.value.id}/match`,
    { business_document_id: documentId },
  );
  matchModal.value = null;
  await load();
}

async function ignoreTx(tx: BankTx) {
  await api.post(`/invoicing/companies/${companyId.value}/bank-transactions/${tx.id}/ignore`);
  await load();
}

async function unmatchTx(tx: BankTx) {
  await api.post(`/invoicing/companies/${companyId.value}/bank-transactions/${tx.id}/unmatch`);
  await load();
}

function openCreateExpense(tx: BankTx) {
  const amount = Math.abs(parseFloat(tx.amount));
  const booked = tx.booked_at?.slice(0, 10) || new Date().toISOString().slice(0, 10);
  const supplier = tx.counterparty_name?.trim() || '';
  expenseDraft.value = {
    title: supplier || tx.reference?.trim() || '',
    total: Number.isFinite(amount) ? amount : 0,
    variable_symbol: tx.variable_symbol || '',
    supplier,
    category: '',
    internal_note: tx.reference?.trim() || '',
    issue_date: booked,
  };
  expenseError.value = '';
  expenseModalTx.value = tx;
}

function closeCreateExpense() {
  expenseModalTx.value = null;
  expenseError.value = '';
}

async function submitCreateExpense(draft: BankExpenseDraft) {
  if (!expenseModalTx.value) return;
  creatingExpense.value = true;
  expenseError.value = '';
  try {
    await api.post(
      `/invoicing/companies/${companyId.value}/bank-transactions/${expenseModalTx.value.id}/create-expense`,
      {
        title: draft.title,
        supplier: draft.supplier || undefined,
        category: draft.category || undefined,
        variable_symbol: draft.variable_symbol || undefined,
        total: draft.total,
        issue_date: draft.issue_date,
        internal_note: draft.internal_note || undefined,
        mark_paid: true,
      },
    );
    closeCreateExpense();
    await load();
  } catch (e: any) {
    expenseError.value = e?.response?.data?.message || t('common.error');
  } finally {
    creatingExpense.value = false;
  }
}

function formatDate(iso: string) {
  try {
    return new Date(iso).toLocaleDateString();
  } catch {
    return iso;
  }
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;
  return `${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

function formatSignedAmount(tx: BankTx) {
  const n = Math.abs(parseFloat(tx.amount));
  const formatted = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const sign = tx.direction === 'credit' ? '+' : '-';
  return `${sign}${formatted} ${tx.currency}`;
}
</script>

<style scoped>
.bank-movement-corner {
  position: absolute;
  top: 0;
  left: 0;
  width: 0;
  height: 0;
  border-style: solid;
  border-width: 10px 10px 0 0;
}

.bank-movement-corner--credit {
  border-color: rgb(16 185 129) transparent transparent transparent;
}

.bank-movement-corner--debit {
  border-color: rgb(220 38 38) transparent transparent transparent;
}

.bank-action-link {
  @apply text-sm text-gray-600 hover:text-indigo-700 hover:underline;
}

.bank-action-link--primary {
  @apply text-indigo-600 font-medium;
}
</style>
