<template>
  <InvoicingPageShell content-class="pt-4 pb-10">
    <ExpenseIsdocExtractModal
      :open="showExtractModal"
      :extracting="extracting"
      :purchasing="purchasing"
      :quota="quota"
      @close="onSkipExtract"
      @skip="onSkipExtract"
      @confirm="onConfirmExtract"
      @purchase="purchasePack"
    />
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <div class="expense-show-toolbar">
        <div class="expense-show-toolbar__start">
          <RouterLink :to="expenseListTo()" class="expense-show-toolbar__back">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            <span>{{ t('invoicing.main_nav_expenses') }}</span>
          </RouterLink>
          <h1 v-if="expense" class="expense-show-toolbar__title">
            {{ displayName }}
          </h1>
        </div>

        <div
          v-if="neighborIds.length > 0 && neighborIndex >= 0"
          class="expense-show-toolbar__pager"
        >
          <button
            type="button"
            class="expense-show-toolbar__pager-btn"
            :disabled="neighborIndex <= 0"
            :title="t('invoicing.expense_show_prev')"
            @click="goNeighbor(-1)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <span class="expense-show-toolbar__pager-count">{{ neighborIndex + 1 }} / {{ neighborIds.length }}</span>
          <button
            type="button"
            class="expense-show-toolbar__pager-btn"
            :disabled="neighborIndex >= neighborIds.length - 1"
            :title="t('invoicing.expense_show_next')"
            @click="goNeighbor(1)"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
    </template>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="expense" class="flex flex-col lg:flex-row gap-6 mt-2">
      <div class="flex-1 min-w-0 space-y-4">
        <div class="invoicing-card overflow-hidden">
          <div class="expense-show-banner flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-sky-50 border-b border-sky-100">
            <div class="text-2xl font-bold text-gray-900">
              {{ formatMoney(expense.total, expense.currency) }}
            </div>
            <div class="text-right min-w-0">
              <div class="font-semibold text-gray-900 truncate">{{ supplierName }}</div>
              <div v-if="categoryName" class="text-sm text-gray-600">{{ categoryName }}</div>
              <div class="text-xs text-gray-500 mt-0.5">{{ expense.internal_number }}</div>
            </div>
          </div>

          <div class="flex flex-col lg:flex-row lg:items-stretch expense-show-body">
            <div class="lg:w-1/2 p-6 flex flex-col min-h-0 border-b lg:border-b-0 border-gray-100">
              <div class="grid sm:grid-cols-2 gap-x-10 gap-y-4 text-sm">
                <div class="space-y-3">
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_show_issue_date') }}</div>
                    <div>{{ formatDate(expense.issue_date) }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_show_delivery_date') }}</div>
                    <div>{{ expense.delivery_date ? formatDate(expense.delivery_date) : '—' }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_col_due') }}</div>
                    <div :class="isOverdue ? 'text-red-600 font-medium' : ''">
                      {{ expense.due_date ? formatDate(expense.due_date) : '—' }}
                    </div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_col_external') }}</div>
                    <div>{{ expense.external_number || '—' }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_show_internal_number') }}</div>
                    <div>{{ expense.internal_number }}</div>
                  </div>
                </div>

                <div class="space-y-3">
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_show_type') }}</div>
                    <div>{{ t('invoicing.expense_show_type_received') }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.variable_symbol') }}</div>
                    <div>{{ expense.variable_symbol || '—' }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_col_ks') }}</div>
                    <div>{{ expense.constant_symbol || '—' }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">{{ t('invoicing.expense_col_ss') }}</div>
                    <div>{{ expense.specific_symbol || '—' }}</div>
                  </div>
                  <div v-if="tagsLine">
                    <div class="invoicing-sf-label">{{ t('invoicing.tags') }}</div>
                    <div class="text-indigo-700">{{ tagsLine }}</div>
                  </div>
                </div>
              </div>

              <div class="mt-5 pt-4 border-t border-gray-100 flex-1 flex flex-col min-h-[7rem]">
                <label class="invoicing-sf-label" for="expense-internal-note">{{ t('invoicing.expense_internal_note') }}</label>
                <textarea
                  id="expense-internal-note"
                  v-model="noteDraft"
                  class="invoicing-sf-input resize-y flex-1 min-h-[5.5rem] mt-1"
                  :placeholder="t('invoicing.expense_show_note_placeholder')"
                  :disabled="noteSaving || expense.status === 'cancelled'"
                  @blur="saveNote"
                />
              </div>
            </div>

            <div class="lg:w-1/2 border-t lg:border-t-0 lg:border-l border-gray-200 flex flex-col min-h-[420px] lg:min-h-0">
              <ExpenseAttachmentPanel
                v-if="!localFirst && (expense.status !== 'cancelled' || savedAttachments.length > 0)"
                embedded
                fill-height
                :file-name="panelFileName"
                :has-file="panelHasFile"
                :has-pending="pendingFiles.length > 0"
                :saved-attachments="savedAttachments"
                :selected-attachment-id="selectedAttachmentId"
                :preview-url="panelPreviewUrl"
                :preview-kind="panelPreviewKind"
                :detecting="detecting"
                :detect-error="detectErrorMessage"
                :has-isdoc="lastDetectHasIsdoc"
                @file-selected="onFileSelected"
                @files-selected="onFilesSelected"
                @clear="onPanelClear"
                @download="downloadAttachment"
                @select-attachment="onSelectAttachment"
                @remove-saved="onRemoveSavedAttachment"
              />
              <LocalFirstBridgeNotice
                v-else-if="localFirst"
                :detail="t('invoicing.local_first_expenses_attachments_bridge')"
              />
            </div>
          </div>

          <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex justify-between text-sm font-medium">
            <span class="text-gray-600">{{ t('invoicing.expense_show_total_footer') }}</span>
            <span class="text-gray-900">{{ formatMoney(expense.total, expense.currency) }}</span>
          </div>
        </div>

        <div class="invoicing-card-pad">
          <h2 class="invoicing-sf-label text-sm font-medium text-gray-800 mb-3">{{ t('invoicing.action_history') }}</h2>
          <ul v-if="history.length" class="space-y-2 text-sm text-gray-600">
            <li v-for="h in history" :key="h.id">
              <span class="text-gray-500">{{ formatDate(h.created_at) }}</span>
              : {{ historyLabel(h.action) }}
              <span v-if="h.user?.email" class="text-gray-400"> / {{ h.user.email }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-500">{{ t('invoicing.no_history') }}</p>
        </div>
      </div>

      <ExpenseShowSidebar
        :company-id="companyId"
        :expense-id="expenseId"
        :status="expense.status"
        :is-overdue="isOverdue"
        :paid-at="expense.paid_at"
        :total="expense.total"
        :currency="expense.currency"
        :has-attachment="savedAttachments.length > 0"
        :busy="acting || duplicating"
        @mark-paid="markPaid"
        @unmark-paid="unmarkPaid"
        @duplicate="duplicate"
        @cancel="cancelExpense"
        @download-attachment="downloadAttachment"
      />
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ExpenseAttachmentPanel from '../../components/invoicing/ExpenseAttachmentPanel.vue';
import ExpenseIsdocExtractModal from '../../components/invoicing/ExpenseIsdocExtractModal.vue';
import ExpenseShowSidebar from '../../components/invoicing/ExpenseShowSidebar.vue';
import LocalFirstBridgeNotice from '../../components/invoicing/LocalFirstBridgeNotice.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import {
  useExpenseIsdocAttachment,
  type ExpenseImportDraft,
} from '../../composables/useExpenseIsdocAttachment';
import { parseExpenseRowMeta } from '../../composables/useExpenseRowMeta';
import { useInvoicingExpense } from '../../composables/useInvoicingExpenses';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import {
  cancelLocalExpense,
  duplicateLocalExpense,
  markLocalExpensePaid,
  unmarkLocalExpensePaid,
  updateLocalExpense,
} from '../../evolu/expenseCrud';
import { filterLocalExpenses } from '../../evolu/expenseMap';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import type { ExpenseId } from '../../evolu/schema';
import api, { getWebBlob } from '../../services/api';

type ExpenseAttachment = {
  id: string;
  original_filename: string | null;
  mime?: string | null;
  created_at?: string;
};

type Expense = {
  id: string;
  internal_number: string;
  external_number: string | null;
  title: string | null;
  status: string;
  issue_date: string;
  delivery_date: string | null;
  due_date: string | null;
  paid_at: string | null;
  total: string;
  currency: string;
  variable_symbol: string | null;
  constant_symbol: string | null;
  specific_symbol: string | null;
  internal_note: string | null;
  attachment_path: string | null;
  original_filename: string | null;
  attachments?: ExpenseAttachment[];
};

type HistoryRow = {
  id: string;
  action: string;
  created_at: string;
  user?: { email?: string };
};

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const expenseId = computed(() => route.params.expenseId as string);
const localFirst = isInvoicingLocalFirst();
const expenseResource = useInvoicingExpense(companyId, expenseId);

const expense = ref<Expense | null>(null);
const history = ref<HistoryRow[]>([]);
const neighborIds = ref<string[]>([]);
const loading = ref(true);
const acting = ref(false);
const duplicating = ref(false);
const uploading = ref(false);
const noteDraft = ref('');
const noteSaving = ref(false);
const savedPreviewUrl = ref<string | null>(null);
const savedPreviewKind = ref<'pdf' | 'image' | 'other' | null>(null);
const selectedAttachmentId = ref<string | null>(null);

const {
  quota,
  pendingAttachmentName,
  previewUrl,
  previewKind,
  lastDetectHasIsdoc,
  detectError,
  showExtractModal,
  detecting,
  extracting,
  purchasing,
  loadQuota,
  purchasePack,
  pendingFiles,
  onDocumentSelected,
  onDocumentsSelected,
  confirmExtract,
  skipExtract,
  clearPendingAttachment,
  uploadAllPendingAttachments,
} = useExpenseIsdocAttachment(companyId);

const rowMeta = computed(() => (expense.value ? parseExpenseRowMeta(expense.value) : { category: '', supplier: '' }));

const supplierName = computed(() => rowMeta.value.supplier || expense.value?.title || expense.value?.internal_number || '');

const categoryName = computed(() => rowMeta.value.category);

const displayName = computed(() => supplierName.value);

const tagsLine = computed(() => {
  const note = expense.value?.internal_note ?? '';
  const match = note.match(/Tags:\s*([^\n]+)/i);

  return match ? match[1].trim() : '';
});

const detectErrorMessage = computed(() => {
  if (!detectError.value) return '';
  if (detectError.value === 'detect_failed') {
    return t('invoicing.expense_attachment_detect_failed');
  }

  return detectError.value;
});

const savedAttachments = computed(() => expense.value?.attachments ?? []);

const selectedAttachment = computed(() => {
  const list = savedAttachments.value;
  if (!list.length) return null;
  const id = selectedAttachmentId.value;
  if (id) {
    return list.find((a) => a.id === id) ?? list[0];
  }

  return list[0];
});

const panelFileName = computed(() => {
  if (pendingAttachmentName.value) return pendingAttachmentName.value;

  return selectedAttachment.value?.original_filename || expense.value?.original_filename || '';
});

const panelHasFile = computed(
  () => Boolean(pendingAttachmentName.value || savedAttachments.value.length > 0 || expense.value?.attachment_path),
);

const panelPreviewUrl = computed(() => previewUrl.value || savedPreviewUrl.value);

const panelPreviewKind = computed(() => previewKind.value || savedPreviewKind.value);

const isOverdue = computed(() => {
  if (!expense.value || expense.value.status !== 'recorded' || !expense.value.due_date) return false;

  return expense.value.due_date.slice(0, 10) < new Date().toISOString().slice(0, 10);
});

const neighborIndex = computed(() =>
  expenseId.value ? neighborIds.value.indexOf(expenseId.value) : -1,
);

watch(expense, (e) => {
  noteDraft.value = e?.internal_note ?? '';
});

function expenseListTo() {
  return { name: 'invoicing-expenses', params: { companyId: companyId.value } };
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;

  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' }).format(n || 0);
}

function formatDate(iso: string) {
  const d = (iso || '').slice(0, 10);
  const [y, m, day] = d.split('-');

  return day && m && y ? `${day}.${m}.${y}` : '—';
}

function historyLabel(action: string) {
  const map: Record<string, string> = {
    'business_expense.created': t('invoicing.expense_history_created'),
    'business_expense.updated': t('invoicing.expense_history_updated'),
    'business_expense.imported': t('invoicing.expense_history_imported'),
    'business_expense.duplicated': t('invoicing.expense_history_duplicated'),
    'business_expense.marked_paid': t('invoicing.expense_history_marked_paid'),
    'business_expense.unmarked_paid': t('invoicing.expense_history_unmarked_paid'),
    'business_expense.cancelled': t('invoicing.expense_history_cancelled'),
    'business_expense.attachment_uploaded': t('invoicing.expense_history_attachment'),
    'business_expense.attachment_removed': t('invoicing.expense_history_attachment_removed'),
  };

  return map[action] || action;
}

async function loadNeighbors() {
  if (localFirst) {
    const rows = filterLocalExpenses(
      expenseResource.expenseRows.value.filter((row) => row.companyId === companyId.value),
      { year: new Date().getFullYear() },
    );
    neighborIds.value = rows.map((row) => row.id);
    return;
  }
  const res = await api.get(`/invoicing/companies/${companyId.value}/expenses`, {
    params: { per_page: 200, year: new Date().getFullYear() },
  });
  neighborIds.value = (res.data.data ?? []).map((e: { id: string }) => e.id);
}

function goNeighbor(delta: number) {
  const idx = neighborIndex.value + delta;
  if (idx < 0 || idx >= neighborIds.value.length) return;
  router.push({
    name: 'invoicing-expense-show',
    params: { companyId: companyId.value, expenseId: neighborIds.value[idx] },
  });
}

async function load() {
  loading.value = true;
  try {
    if (localFirst) {
      await expenseResource.refresh();
      expense.value = expenseResource.expense.value as Expense | null;
      noteDraft.value = expense.value?.internal_note ?? '';
      history.value = [];
      selectedAttachmentId.value = null;
      return;
    }
    const [expRes, histRes] = await Promise.all([
      api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`),
      api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/history`),
    ]);
    expense.value = expRes.data.data;
    history.value = histRes.data.data ?? [];
    selectedAttachmentId.value = expense.value?.attachments?.[0]?.id ?? null;
    await loadSavedPreview();
  } finally {
    loading.value = false;
  }
}

function revokeSavedPreview() {
  if (savedPreviewUrl.value) {
    URL.revokeObjectURL(savedPreviewUrl.value);
    savedPreviewUrl.value = null;
  }
  savedPreviewKind.value = null;
}

function attachmentPreviewUrl(attachmentId?: string | null) {
  if (attachmentId) {
    return `/api/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/attachments/${attachmentId}`;
  }

  return `/api/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/attachment`;
}

async function loadSavedPreview() {
  revokeSavedPreview();
  if (pendingAttachmentName.value) return;

  const att = selectedAttachment.value;
  if (!att && !expense.value?.attachment_path) return;

  try {
    const blob = await getWebBlob(attachmentPreviewUrl(att?.id));
    const name = att?.original_filename || expense.value?.original_filename || '';
    if (name.toLowerCase().endsWith('.pdf') || blob.type === 'application/pdf') {
      savedPreviewKind.value = 'pdf';
    } else if (blob.type.startsWith('image/')) {
      savedPreviewKind.value = 'image';
    } else {
      savedPreviewKind.value = 'other';
    }
    if (savedPreviewKind.value === 'pdf' || savedPreviewKind.value === 'image') {
      savedPreviewUrl.value = URL.createObjectURL(blob);
    }
  } catch {
    savedPreviewKind.value = 'other';
  }
}

function downloadAttachment() {
  const att = selectedAttachment.value;
  window.open(attachmentPreviewUrl(att?.id), '_blank');
}

function onSelectAttachment(id: string) {
  selectedAttachmentId.value = id;
  if (!pendingAttachmentName.value) {
    loadSavedPreview();
  }
}

async function onRemoveSavedAttachment(id: string) {
  if (!confirm(t('invoicing.expense_attachment_remove_confirm'))) return;
  try {
    const res = await api.delete(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/attachments/${id}`,
    );
    expense.value = res.data.data;
    if (selectedAttachmentId.value === id) {
      selectedAttachmentId.value = expense.value?.attachments?.[0]?.id ?? null;
    }
    await loadSavedPreview();
  } catch {
    window.alert(t('invoicing.expense_attachment_remove_failed'));
  }
}

async function saveNote() {
  if (!expense.value || noteDraft.value === (expense.value.internal_note ?? '')) return;
  noteSaving.value = true;
  try {
    if (localFirst && expenseResource.evolu && expense.value) {
      await updateLocalExpense(
        expenseResource.evolu,
        expenseId.value as ExpenseId,
        {
          issue_date: expense.value.issue_date,
          total: expense.value.total,
          internal_note: noteDraft.value,
          title: expense.value.title,
          external_number: expense.value.external_number,
          variable_symbol: expense.value.variable_symbol,
          constant_symbol: expense.value.constant_symbol,
          specific_symbol: expense.value.specific_symbol,
          delivery_date: expense.value.delivery_date,
          due_date: expense.value.due_date,
          currency: expense.value.currency,
        },
      );
      await expenseResource.refresh();
      expense.value = expenseResource.expense.value as Expense | null;
      return;
    }
    const res = await api.patch(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`, {
      internal_note: noteDraft.value,
    });
    expense.value = res.data.data;
  } finally {
    noteSaving.value = false;
  }
}

async function duplicate() {
  duplicating.value = true;
  try {
    if (localFirst && expenseResource.evolu) {
      const source = expenseResource.expenseRows.value.find((row) => row.id === expenseId.value);
      if (!source) return;
      const result = duplicateLocalExpense(
        expenseResource.evolu,
        source,
        expenseResource.expenseRows.value,
      );
      if (!result.ok) return;
      await router.push({
        name: 'invoicing-expense-edit',
        params: { companyId: companyId.value, expenseId: result.value.id },
      });
      return;
    }
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/duplicate`,
    );
    await router.push({
      name: 'invoicing-expense-edit',
      params: { companyId: companyId.value, expenseId: res.data.data.id },
    });
  } finally {
    duplicating.value = false;
  }
}

async function markPaid() {
  acting.value = true;
  try {
    if (localFirst && expenseResource.evolu) {
      markLocalExpensePaid(expenseResource.evolu, expenseId.value as ExpenseId);
      await load();
      return;
    }
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/mark-paid`,
    );
    expense.value = res.data.data;
    const histRes = await api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/history`);
    history.value = histRes.data.data ?? [];
  } finally {
    acting.value = false;
  }
}

async function unmarkPaid() {
  acting.value = true;
  try {
    if (localFirst && expenseResource.evolu) {
      unmarkLocalExpensePaid(expenseResource.evolu, expenseId.value as ExpenseId);
      await load();
      return;
    }
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/unmark-paid`,
    );
    expense.value = res.data.data;
  } finally {
    acting.value = false;
  }
}

async function cancelExpense() {
  if (!confirm(t('invoicing.expense_cancel_confirm'))) return;
  acting.value = true;
  try {
    if (localFirst && expenseResource.evolu) {
      cancelLocalExpense(expenseResource.evolu, expenseId.value as ExpenseId);
      await router.push(expenseListTo());
      return;
    }
    await api.delete(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`);
    await router.push(expenseListTo());
  } finally {
    acting.value = false;
  }
}

function draftToPayload(draft: ExpenseImportDraft) {
  return {
    title: draft.title ?? expense.value?.title,
    external_number: draft.external_number ?? expense.value?.external_number,
    variable_symbol: draft.variable_symbol ?? expense.value?.variable_symbol,
    constant_symbol: draft.constant_symbol ?? expense.value?.constant_symbol,
    specific_symbol: draft.specific_symbol ?? expense.value?.specific_symbol,
    issue_date: draft.issue_date?.slice(0, 10) ?? expense.value?.issue_date,
    delivery_date: draft.delivery_date?.slice(0, 10) ?? expense.value?.delivery_date,
    due_date: draft.due_date?.slice(0, 10) ?? expense.value?.due_date,
    total: draft.total ?? expense.value?.total,
    currency: draft.currency ?? expense.value?.currency,
    internal_note: expense.value?.internal_note,
  };
}

async function uploadAttachmentOnly() {
  if (pendingFiles.value.length === 0) return;
  uploading.value = true;
  try {
    await uploadAllPendingAttachments(expenseId.value);
    const res = await api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`);
    expense.value = res.data.data;
    selectedAttachmentId.value = expense.value?.attachments?.at(-1)?.id
      ?? expense.value?.attachments?.[0]?.id
      ?? null;
    await loadSavedPreview();
  } finally {
    uploading.value = false;
  }
}

async function onFileSelected(file: File) {
  try {
    await onDocumentSelected(file);
    if (!showExtractModal.value) {
      await uploadAttachmentOnly();
    }
  } catch {
    /* detectError shown in panel */
  }
}

async function onFilesSelected(files: File[]) {
  try {
    await onDocumentsSelected(files);
    if (!showExtractModal.value) {
      await uploadAttachmentOnly();
    }
  } catch {
    /* detectError shown in panel */
  }
}

function onPanelClear() {
  if (pendingFiles.value.length > 0) {
    clearPendingAttachment();

    return;
  }
  revokeSavedPreview();
}

async function onConfirmExtract() {
  try {
    const draft = await confirmExtract();
    if (draft) {
      const res = await api.patch(
        `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`,
        draftToPayload(draft),
      );
      expense.value = res.data.data;
    }
    await uploadAttachmentOnly();
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: { quota?: string[] }; message?: string } } };
    window.alert(err?.response?.data?.errors?.quota?.[0] || err?.response?.data?.message || t('invoicing.expense_extract_failed'));
    skipExtract();
  }
}

async function onSkipExtract() {
  skipExtract();
  await uploadAttachmentOnly();
}

watch(expenseId, () => {
  load();
});

onMounted(() => {
  rememberCompany(companyId.value);
  if (!localFirst) {
    loadQuota();
  }
  load();
  loadNeighbors();
});

onUnmounted(() => {
  revokeSavedPreview();
});
</script>

<style scoped>
.expense-show-toolbar {
  @apply flex items-center justify-between gap-4 w-full;
}

.expense-show-toolbar__start {
  @apply flex items-center gap-3 min-w-0 flex-1;
}

.expense-show-toolbar__back {
  @apply inline-flex items-center gap-1.5 shrink-0 whitespace-nowrap text-sm text-gray-600 hover:text-indigo-700;
}

.expense-show-toolbar__title {
  @apply text-lg font-semibold text-gray-900 truncate min-w-0;
}

.expense-show-toolbar__pager {
  @apply flex items-center gap-0.5 text-sm text-gray-600 shrink-0 ml-4;
}

.expense-show-toolbar__pager-btn {
  @apply p-1.5 rounded hover:bg-gray-100 hover:text-indigo-700 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-600;
}

.expense-show-toolbar__pager-count {
  @apply tabular-nums px-1.5 min-w-[3.75rem] text-center;
}

.expense-show-banner {
  background: linear-gradient(90deg, #f0f9ff 0%, #e0f2fe 100%);
}

.expense-show-body {
  min-height: min(72vh, 820px);
}
</style>
