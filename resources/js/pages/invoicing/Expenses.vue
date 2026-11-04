<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader
        :company-label="companyName"
        :show-filter-bar="false"
        show-mobile-filters
        :mobile-filter-active-count="mobileFilterActiveCount"
        @mobile-filter-apply="load"
        @mobile-filter-clear="onMobileFilterClear"
      >
        <template #mobile-filters>
          <div class="invoicing-mobile-filter-stack">
            <button
              v-for="f in statusFilters"
              :key="f.id"
              type="button"
              class="invoicing-filter"
              :class="activeFilter === f.id ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
              @click="setFilter(f.id)"
            >
              {{ t(f.labelKey) }}
            </button>
            <select
              v-model="year"
              class="invoicing-sf-input w-full"
              @change="onYearChange"
            >
              <option v-for="y in yearOptions" :key="y" :value="y">{{ yearLabel(y) }}</option>
            </select>
          </div>
        </template>
        <template #mobile-actions>
          <button type="button" class="invoicing-btn-secondary w-full" @click="openImport('excel')">
            {{ t('invoicing.expense_import_action') }}
          </button>
          <button type="button" class="invoicing-btn-secondary w-full" @click="openImport('pdf')">
            {{ t('invoicing.expense_attach_import_action') }}
          </button>
          <RouterLink :to="expenseNewTo()" class="invoicing-btn-primary w-full text-center">
            + {{ t('invoicing.expenses_new') }}
          </RouterLink>
        </template>
        <template #mobile-primary-action>
          <RouterLink :to="expenseNewTo()" class="invoicing-mobile-icon-btn" :title="t('invoicing.mobile_new_expense')">
            <InvoicingIcons name="plus" />
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <template #subheader>
      <div class="invoicing-filter-bar bg-white border-b border-gray-200 hidden md:block">
        <div :class="[INVOICING_CONTAINER_CLASS, 'flex flex-wrap items-center gap-2 py-3']">
          <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
            <button
              v-for="f in statusFilters"
              :key="f.id"
              type="button"
              class="invoicing-filter"
              :class="activeFilter === f.id ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
              @click="setFilter(f.id)"
            >
              {{ t(f.labelKey) }}
            </button>

            <div class="relative">
              <select
                v-model="year"
                class="invoicing-filter invoicing-filter--idle appearance-none pr-7 cursor-pointer"
                @change="onYearChange"
              >
                <option v-for="y in yearOptions" :key="y" :value="y">{{ yearLabel(y) }}</option>
              </select>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <div class="relative">
              <button
                type="button"
                class="invoicing-btn-secondary shrink-0"
                @click.stop="showImportMenu = !showImportMenu"
              >
                {{ t('invoicing.expense_import_menu') }} ▾
              </button>
              <div v-if="showImportMenu" class="invoicing-dropdown right-0 left-auto min-w-[200px]">
                <button type="button" class="invoicing-dropdown-item" @click="openImport('excel')">
                  {{ t('invoicing.expense_import_action') }}
                </button>
                <button type="button" class="invoicing-dropdown-item" @click="openImport('pdf')">
                  {{ t('invoicing.expense_attach_import_action') }}
                </button>
              </div>
            </div>
            <RouterLink :to="expenseNewTo()" class="invoicing-btn-primary shrink-0">
              + {{ t('invoicing.expenses_new') }}
            </RouterLink>
          </div>
        </div>
      </div>
    </template>

    <LocalFirstBridgeNotice
      v-if="localFirst && bridgeNoticeKey"
      class="mb-4"
      :detail="t(`invoicing.${bridgeNoticeKey}`)"
    />

    <EfakturaInboxPanel v-if="localFirst" :company-id="companyId" @imported="load" />

    <p v-if="success" class="text-sm text-green-700 mb-4">{{ success }}</p>
    <div v-if="error" role="alert" class="mb-4 text-sm text-red-700">
      <p>{{ error }}</p>
      <button type="button" class="invoicing-btn-secondary mt-2" @click="load">
        {{ t('common.retry') }}
      </button>
    </div>

    <InvoicingMobileBulkBar
      :open="selectionCount > 0"
      :selection-count="selectionCount"
      @clear="clearSelection"
    >
      <button type="button" class="invoicing-btn-secondary text-sm shrink-0" @click="runBulk('mark_paid')">
        {{ t('invoicing.expense_bulk_mark_paid') }}
      </button>
      <button type="button" class="invoicing-btn-secondary text-sm shrink-0" @click="runBulk('export_xlsx')">
        XLSX
      </button>
    </InvoicingMobileBulkBar>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="!error && expenses.length === 0" class="invoicing-card-pad text-center text-gray-600 my-8">
      <p>{{ t('invoicing.expenses_empty') }}</p>
      <p class="text-sm text-gray-500 mt-2">{{ t('invoicing.expenses_empty_hint') }}</p>
    </div>

    <div v-else-if="!error" class="invoicing-card overflow-hidden my-8">
      <ExpensesTable
        :rows="expenses"
        :company-id="companyId"
        :selected-ids="Array.from(selectedIds)"
        :select-all-mode="selectAllMode"
        :selection-count="selectionCount"
        :total-count="totalCount"
        :show-header-menu="showHeaderMenu"
        :action-id="actionId"
        @open="goShow"
        @toggle-row="toggleRow"
        @toggle-header-menu="showHeaderMenu = !showHeaderMenu"
        @select-page="selectPage"
        @select-all="selectAllFiltered"
        @clear-selection="clearSelection"
        @run-bulk="runBulk"
        @mark-paid="markPaidRow"
        @duplicate="duplicateRow"
        @cancel="cancelRow"
        @open-attachment="openAttachment"
      />

      <div v-if="lastPage > 1" class="flex justify-center gap-2 p-3 border-t text-sm bg-gray-50">
        <button type="button" class="invoicing-btn-secondary py-1" :disabled="page <= 1" @click="changePage(page - 1)">
          ‹
        </button>
        <span class="text-gray-600 self-center">{{ page }} / {{ lastPage }}</span>
        <button
          type="button"
          class="invoicing-btn-secondary py-1"
          :disabled="page >= lastPage"
          @click="changePage(page + 1)"
        >
          ›
        </button>
      </div>
    </div>

    <ExpenseListSummary
      v-if="!loading && !error && expenses.length > 0 && summary"
      class="mb-8"
      :summary="summary"
    />

    <ExpenseImportModal
      :open="showImportModal"
      :company-id="companyId"
      :local-first="localFirst"
      @close="showImportModal = false"
      @imported="onImported"
    />
    <ExpenseAttachmentImportModal
      :open="showAttachImportModal"
      :company-id="companyId"
      @close="showAttachImportModal = false"
      @imported="onImported"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { expenseMutationErrorKey } from "../../utils/expenseError";
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import EfakturaInboxPanel from '../../components/invoicing/EfakturaInboxPanel.vue';
import ExpenseAttachmentImportModal from '../../components/invoicing/ExpenseAttachmentImportModal.vue';
import ExpenseImportModal from '../../components/invoicing/ExpenseImportModal.vue';
import ExpenseListSummary from '../../components/invoicing/ExpenseListSummary.vue';
import ExpensesTable from '../../components/invoicing/ExpensesTable.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import LocalFirstBridgeNotice from '../../components/invoicing/LocalFirstBridgeNotice.vue';
import InvoicingMobileBulkBar from '../../components/invoicing/InvoicingMobileBulkBar.vue';
import InvoicingIcons from '../../components/invoicing/icons/InvoicingIcons.vue';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import {
  bulkCancelLocalExpenses,
  bulkMarkPaidLocalExpenses,
  resolveBulkExpenseTargets,
} from '../../evolu/expenseBulkLocal';
import {
  cancelLocalExpense,
  duplicateLocalExpense,
  markLocalExpensePaid,
} from '../../evolu/expenseCrud';
import type { CompanyId, ExpenseId } from '../../evolu/schema';
import { expenseOverdueDays, type ExpenseListRow } from '../../composables/useExpenseRowMeta';
import { useInvoicingCompanySummary } from '../../composables/useInvoicingCompanySummary';
import { useInvoicingExpenses } from '../../composables/useInvoicingExpenses';
import { INVOICING_CONTAINER_CLASS, useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { invoicingApi } from '../../services/api';
import { summarizeExpenses, type ExpenseSummary } from '../../utils/expenseSummary';

const { t } = useI18n();
const localFirst = isInvoicingLocalFirst();
const route = useRoute();
const router = useRouter();
const { rememberCompany } = useInvoicingLayout();
const { companyName } = useInvoicingCompanySummary();
const companyId = computed(() => route.params.companyId as string);
const invoicingExpenses = useInvoicingExpenses(companyId);
const bridgeNoticeKey = ref<string | null>(null);
const showImportModal = ref(false);
const showAttachImportModal = ref(false);
const showImportMenu = ref(false);
const showHeaderMenu = ref(false);
const loading = ref(true);
const error = ref('');
const success = ref('');
const actionId = ref<string | null>(null);
const expenses = ref<ExpenseListRow[]>([]);
const page = ref(1);
const lastPage = ref(1);
const totalCount = ref(0);
const summary = ref<ExpenseSummary | null>(null);
let loadEpoch = 0;
const activeFilter = ref('all');
const year = ref(route.query.year === 'all' ? 0 : new Date().getFullYear());
const selectedIds = ref<Set<string>>(new Set());
const selectAllMode = ref(false);

const statusFilters = [
  { id: 'all', labelKey: 'invoicing.adv_all' },
  { id: 'paid', labelKey: 'invoicing.adv_paid' },
  { id: 'unpaid', labelKey: 'invoicing.expense_filter_unpaid' },
  { id: 'overdue', labelKey: 'invoicing.expense_filter_overdue' },
];

const yearOptions = computed(() => {
  const current = new Date().getFullYear();

  return [0, current, current - 1, current - 2];
});

const selectionCount = computed(() => (selectAllMode.value ? totalCount.value : selectedIds.value.size));

const mobileFilterActiveCount = computed(() => {
  let count = 0;
  if (activeFilter.value !== 'all') count++;
  if (year.value !== new Date().getFullYear()) count++;
  return count;
});

function onMobileFilterClear() {
  activeFilter.value = 'all';
  year.value = new Date().getFullYear();
  page.value = 1;
  clearSelection();
  load();
}

const fileActions = new Set(['export_xlsx', 'attachments_zip']);

function yearLabel(y: number) {
  if (y === 0) return t('invoicing.expense_year_all');
  if (y === new Date().getFullYear()) {
    return t('invoicing.expense_year_this', { year: y });
  }

  return String(y);
}

function expenseNewTo() {
  return { name: 'invoicing-expense-new', params: { companyId: companyId.value } };
}

function goShow(expenseId: string) {
  router.push({ name: 'invoicing-expense-show', params: { companyId: companyId.value, expenseId } });
}

function setFilter(id: string) {
  activeFilter.value = id;
  page.value = 1;
  clearSelection();
  load();
}

function onYearChange() {
  page.value = 1;
  clearSelection();
  void load();
}

function changePage(p: number) {
  page.value = p;
  load();
}

function openImport(kind: 'excel' | 'pdf') {
  showImportMenu.value = false;
  if (kind === 'pdf' && localFirst) {
    bridgeNoticeKey.value = 'local_first_expenses_pdf_import_bridge';
    return;
  }
  if (kind === 'excel') {
    showImportModal.value = true;
  } else {
    showAttachImportModal.value = true;
  }
}

function onImported() {
  showAttachImportModal.value = false;
  year.value = 0;
  activeFilter.value = 'all';
  page.value = 1;
  clearSelection();
  void load();
}

watch(() => route.query.year, (value) => {
  if (value === 'all') {
    year.value = 0;
    page.value = 1;
    void load();
  }
});

watch(companyId, (id) => {
  expenses.value = [];
  summary.value = null;
  totalCount.value = 0;
  lastPage.value = 1;
  page.value = 1;
  error.value = '';
  success.value = '';
  actionId.value = null;
  showImportModal.value = false;
  showAttachImportModal.value = false;
  showHeaderMenu.value = false;
  clearSelection();
  if (id) rememberCompany(id);
  void load();
}, { flush: 'sync' });

function toggleRow(id: string) {
  selectAllMode.value = false;
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function selectPage() {
  selectAllMode.value = false;
  const next = new Set(selectedIds.value);
  expenses.value.forEach((e) => next.add(e.id));
  selectedIds.value = next;
  showHeaderMenu.value = true;
}

function selectAllFiltered() {
  selectAllMode.value = true;
  selectedIds.value = new Set();
  showHeaderMenu.value = true;
}

function clearSelection() {
  selectAllMode.value = false;
  selectedIds.value = new Set();
}

function listFilterParams() {
  return {
    filter: activeFilter.value === 'all' ? undefined : activeFilter.value,
    year: year.value || undefined,
  };
}

function bulkPayload(action: string) {
  const base: Record<string, unknown> = {
    action,
    ...listFilterParams(),
  };
  if (selectAllMode.value) {
    base.select_all = true;
  } else {
    base.expense_ids = Array.from(selectedIds.value);
  }

  return base;
}

async function runBulk(action: string) {
  if (selectionCount.value === 0) return;
  const actionCompany = companyId.value;
  const actionEpoch = loadEpoch;
  showHeaderMenu.value = false;

  if (action === 'cancel' && !window.confirm(t('invoicing.expense_bulk_delete_confirm'))) return;

  error.value = '';
  success.value = '';
  loading.value = true;

  try {
    if (localFirst && invoicingExpenses.evolu) {
      if (action === 'export_xlsx' || action === 'attachments_zip') {
        bridgeNoticeKey.value = 'local_first_expenses_bulk_bridge';
        return;
      }

      const targets = resolveBulkExpenseTargets(
        companyId.value as CompanyId,
        selectAllMode.value,
        selectedIds.value,
        invoicingExpenses.expenseRows.value,
        listFilterParams(),
      );

      if (targets.length === 0) {
        error.value = t('common.error');
        return;
      }

      const result =
        action === 'mark_paid'
          ? await bulkMarkPaidLocalExpenses(invoicingExpenses.evolu, targets)
          : action === 'cancel'
            ? await bulkCancelLocalExpenses(invoicingExpenses.evolu, targets)
            : null;
      if (actionCompany !== companyId.value) return;

      if (result) {
        success.value = t('invoicing.bulk_result', {
          processed: result.processed,
          skipped: result.skipped,
        });
        await load();
        if (actionCompany !== companyId.value) return;
        clearSelection();
      }
      return;
    }

    const isFile = fileActions.has(action);
    const res = isFile
      ? await invoicingApi.expenses.bulkExport(companyId.value, bulkPayload(action))
      : await invoicingApi.expenses.bulk(companyId.value, bulkPayload(action));
    if (actionCompany !== companyId.value) return;

    if (isFile) {
      const names: Record<string, string> = {
        export_xlsx: 'expenses.xlsx',
        attachments_zip: 'expense-attachments.zip',
      };
      const blob = res as Blob;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = names[action] || 'export';
      a.click();
      URL.revokeObjectURL(url);
    } else {
      const data = res as { processed?: number; skipped?: number };
      success.value = t('invoicing.bulk_result', {
        processed: data.processed ?? 0,
        skipped: data.skipped ?? 0,
      });
      await load();
      if (actionCompany !== companyId.value) return;
      clearSelection();
    }
  } catch (rawError) {
    if (actionCompany !== companyId.value) return;
    const e = asApiError(rawError);
    const mutationErrorKey = expenseMutationErrorKey(rawError);
    if (mutationErrorKey) {
      error.value = t(mutationErrorKey);
    } else if (e?.response?.data instanceof Blob) {
      const text = await e.response.data.text();
      if (actionCompany !== companyId.value) return;
      try {
        const json = JSON.parse(text);
        error.value = json.message || t('common.error');
      } catch {
        error.value = t('common.error');
      }
    } else {
      error.value = e?.response?.data?.message || t('common.error');
    }
  } finally {
    if (actionCompany === companyId.value && actionEpoch === loadEpoch) loading.value = false;
  }
}

async function markPaidRow(expenseId: string) {
  const actionCompany = companyId.value;
  actionId.value = expenseId;
  error.value = '';
  try {
    if (localFirst && invoicingExpenses.evolu) {
      const result = await markLocalExpensePaid(invoicingExpenses.evolu, expenseId as ExpenseId);
      if (actionCompany !== companyId.value) return;
      if (!result.ok) throw new Error(t('common.error'));
      await load();
      return;
    }
    await invoicingApi.expenses.action(companyId.value, expenseId, 'mark-paid');
    if (actionCompany !== companyId.value) return;
    await load();
  } catch (rawError) {
    if (actionCompany !== companyId.value) return;
    const e = asApiError(rawError);
    const mutationErrorKey = expenseMutationErrorKey(rawError);
    error.value = mutationErrorKey ? t(mutationErrorKey) : e?.response?.data?.message || t('common.error');
  } finally {
    if (actionCompany === companyId.value) actionId.value = null;
  }
}

async function duplicateRow(expenseId: string) {
  const actionCompany = companyId.value;
  actionId.value = expenseId;
  error.value = '';
  try {
    if (localFirst && invoicingExpenses.evolu) {
      const source = invoicingExpenses.expenseRows.value.find((row) => row.id === expenseId);
      if (!source) return;
      const result = await duplicateLocalExpense(
        invoicingExpenses.evolu,
        source,
        invoicingExpenses.expenseRows.value,
      );
      if (actionCompany !== companyId.value) return;
      if (!result.ok) return;
      await router.push({
        name: 'invoicing-expense-edit',
        params: { companyId: companyId.value, expenseId: result.value.id },
        query: { duplicate: result.value.id },
      });
      return;
    }
    const res = await invoicingApi.expenses.action<{ id: string }>(companyId.value, expenseId, 'duplicate');
    if (actionCompany !== companyId.value) return;
    await router.push({
      name: 'invoicing-expense-edit',
      params: { companyId: companyId.value, expenseId: res.id },
      query: { duplicate: res.id },
    });
  } catch (rawError) {
    if (actionCompany !== companyId.value) return;
    const e = asApiError(rawError);
    const mutationErrorKey = expenseMutationErrorKey(rawError);
    error.value = mutationErrorKey ? t(mutationErrorKey) : e?.response?.data?.message || t('common.error');
    actionId.value = null;
  }
}

async function cancelRow(expenseId: string) {
  if (!window.confirm(t('invoicing.expense_cancel_confirm'))) return;
  const actionCompany = companyId.value;
  actionId.value = expenseId;
  error.value = '';
  try {
    if (localFirst && invoicingExpenses.evolu) {
      const result = await cancelLocalExpense(invoicingExpenses.evolu, expenseId as ExpenseId);
      if (actionCompany !== companyId.value) return;
      if (!result.ok) throw new Error(t('common.error'));
      await load();
      return;
    }
    await invoicingApi.expenses.delete(companyId.value, expenseId);
    if (actionCompany !== companyId.value) return;
    await load();
  } catch (rawError) {
    if (actionCompany !== companyId.value) return;
    const e = asApiError(rawError);
    const mutationErrorKey = expenseMutationErrorKey(rawError);
    error.value = mutationErrorKey ? t(mutationErrorKey) : e?.response?.data?.message || t('common.error');
  } finally {
    if (actionCompany === companyId.value) actionId.value = null;
  }
}

function openAttachment(expenseId: string) {
  if (localFirst) {
    goShow(expenseId);
    return;
  }
  window.open(`/api/invoicing/companies/${companyId.value}/expenses/${expenseId}/attachment`, '_blank');
}

function onDocumentClick() {
  showImportMenu.value = false;
  showHeaderMenu.value = false;
}

async function load() {
  const requestEpoch = ++loadEpoch;
  const requestedCompany = companyId.value;
  const requestedFilters = listFilterParams();
  const requestedPage = page.value;
  const isCurrent = () => requestEpoch === loadEpoch
    && requestedCompany === companyId.value
    && requestedFilters.filter === listFilterParams().filter
    && requestedFilters.year === listFilterParams().year
    && requestedPage === page.value;
  loading.value = true;
  error.value = '';
  summary.value = null;
  expenses.value = [];
  try {
    if (!requestedCompany) {
      totalCount.value = 0;
      lastPage.value = 1;
      return;
    }
    if (localFirst) {
      await invoicingExpenses.refresh(requestedFilters);
      if (!isCurrent()) return;
      const all = [...invoicingExpenses.expenses.value];
      const nextSummary = summarizeExpenses(all);
      totalCount.value = all.length;
      lastPage.value = Math.max(1, Math.ceil(all.length / 25));
      if (page.value > lastPage.value) page.value = lastPage.value;
      const start = (page.value - 1) * 25;
      expenses.value = all.slice(start, start + 25);
      summary.value = nextSummary;
      return;
    }

    const listRes = await invoicingApi.expenses.listPaged<ExpenseListRow & { attachment_path?: string | null; attachments_count?: number }>(requestedCompany, {
      ...requestedFilters,
      page: requestedPage,
    });
    if (!isCurrent()) return;
    const today = new Date().toISOString().slice(0, 10);
    const rows = listRes.data ?? [];
    expenses.value = rows.map((e: ExpenseListRow & { attachment_path?: string | null; attachments_count?: number }) => ({
      ...e,
      is_overdue:
        e.status === 'recorded'
        && Boolean(e.due_date)
        && e.due_date! < today
        && expenseOverdueDays(e.due_date) !== null,
      has_attachment: (e.attachments_count ?? 0) > 0 || Boolean(e.attachment_path),
    }));
    lastPage.value = listRes.last_page ?? 1;
    page.value = listRes.current_page ?? 1;
    totalCount.value = listRes.total ?? expenses.value.length;
    summary.value = listRes.summary ?? null;
  } catch {
    if (!isCurrent()) return;
    expenses.value = [];
    summary.value = null;
    totalCount.value = 0;
    lastPage.value = 1;
    error.value = t('invoicing.expenses_load_error');
  } finally {
    if (requestEpoch === loadEpoch) loading.value = false;
  }
}

onMounted(() => {
  rememberCompany(companyId.value);
  document.addEventListener('click', onDocumentClick);
  load();
});

onUnmounted(() => {
  loadEpoch += 1;
  document.removeEventListener('click', onDocumentClick);
});
</script>
