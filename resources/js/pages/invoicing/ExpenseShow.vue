<template>
  <InvoicingPageShell content-class="pt-4 pb-10">
    <ExpenseIsdocExtractModal
      :open="showExtractModal"
      :extracting="extracting || processingExtraction"
      :purchasing="purchasing"
      :quota="quota"
      :allow-purchases="!localFirst"
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
            <svg
              class="w-4 h-4 shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
            <span>{{ t("invoicing.main_nav_expenses") }}</span>
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
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
          </button>
          <span class="expense-show-toolbar__pager-count"
            >{{ neighborIndex + 1 }} / {{ neighborIds.length }}</span
          >
          <button
            type="button"
            class="expense-show-toolbar__pager-btn"
            :disabled="neighborIndex >= neighborIds.length - 1"
            :title="t('invoicing.expense_show_next')"
            @click="goNeighbor(1)"
          >
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5l7 7-7 7"
              />
            </svg>
          </button>
        </div>
      </div>
    </template>

    <div v-if="uploadError" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
      <p>{{ uploadError }}</p>
      <button
        v-if="pendingFiles.length > 0"
        type="button"
        class="invoicing-btn-secondary mt-2"
        :disabled="uploading || detecting || extracting || processingExtraction"
        @click="retryAttachmentUpload"
      >{{ t('invoicing.expense_attachment_retry') }}</button>
    </div>
    <div v-if="loading" class="invoicing-muted py-8">
      {{ t("common.loading") }}
    </div>
    <p v-else-if="loadError" class="py-8 text-sm text-red-700" role="alert">{{ loadError }}</p>

    <div v-else-if="expense" class="flex flex-col lg:flex-row gap-6 mt-2">
      <div class="flex-1 min-w-0 space-y-4">
        <div v-if="expense.bank_match_conflict" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="status">
          <p>{{ t('invoicing.expense_bank_match_conflict') }}</p>
          <button
            v-if="localFirst && expense.bank_match_claim != null"
            type="button"
            class="invoicing-btn-secondary mt-2"
            :disabled="acting || duplicating || uploading || processingExtraction || noteSaving"
            @click="releaseBankConflict"
          >{{ t('invoicing.bank_unmatch') }}</button>
        </div>
        <div class="invoicing-card overflow-hidden">
          <div
            class="expense-show-banner flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-sky-50 border-b border-sky-100"
          >
            <div class="text-2xl font-bold text-gray-900">
              {{ formatMoney(expense.total, expense.currency) }}
            </div>
            <div class="text-right min-w-0">
              <div class="font-semibold text-gray-900 truncate">
                {{ supplierName }}
              </div>
              <div v-if="categoryName" class="text-sm text-gray-600">
                {{ categoryName }}
              </div>
              <div class="text-xs text-gray-500 mt-0.5">
                {{ expense.internal_number }}
              </div>
            </div>
          </div>

          <div
            class="flex flex-col lg:flex-row lg:items-stretch expense-show-body"
          >
            <div
              class="lg:w-1/2 p-6 flex flex-col min-h-0 border-b lg:border-b-0 border-gray-100"
            >
              <div class="grid sm:grid-cols-2 gap-x-10 gap-y-4 text-sm">
                <div class="space-y-3">
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_show_issue_date") }}
                    </div>
                    <div>{{ formatDate(expense.issue_date) }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_show_delivery_date") }}
                    </div>
                    <div>
                      {{
                        expense.delivery_date
                          ? formatDate(expense.delivery_date)
                          : "-"
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_col_due") }}
                    </div>
                    <div :class="isOverdue ? 'text-red-600 font-medium' : ''">
                      {{
                        expense.due_date ? formatDate(expense.due_date) : "-"
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_col_external") }}
                    </div>
                    <div>{{ expense.external_number || "-" }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_show_internal_number") }}
                    </div>
                    <div>{{ expense.internal_number }}</div>
                  </div>
                </div>

                <div class="space-y-3">
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_show_type") }}
                    </div>
                    <div>{{ t("invoicing.expense_show_type_received") }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.variable_symbol") }}
                    </div>
                    <div>{{ expense.variable_symbol || "-" }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_col_ks") }}
                    </div>
                    <div>{{ expense.constant_symbol || "-" }}</div>
                  </div>
                  <div>
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.expense_col_ss") }}
                    </div>
                    <div>{{ expense.specific_symbol || "-" }}</div>
                  </div>
                  <div v-if="tagsLine">
                    <div class="invoicing-sf-label">
                      {{ t("invoicing.tags") }}
                    </div>
                    <div class="text-indigo-700">{{ tagsLine }}</div>
                  </div>
                </div>
              </div>

              <div
                class="mt-5 pt-4 border-t border-gray-100 flex-1 flex flex-col min-h-[7rem]"
              >
                <label class="invoicing-sf-label" for="expense-internal-note">{{
                  t("invoicing.expense_internal_note")
                }}</label>
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

            <div
              class="lg:w-1/2 border-t lg:border-t-0 lg:border-l border-gray-200 flex flex-col min-h-[420px] lg:min-h-0"
            >
              <ExpenseAttachmentPanel
                v-if="
                  expense.status !== 'cancelled' || savedAttachments.length > 0
                "
                embedded
                fill-height
                allow-multiple
                :file-name="panelFileName"
                :has-file="panelHasFile"
                :has-pending="pendingFiles.length > 0"
                :saved-attachments="savedAttachments"
                :selected-attachment-id="selectedAttachmentId"
                :preview-url="panelPreviewUrl"
                :preview-kind="panelPreviewKind"
                :detecting="detecting || uploading || extracting || processingExtraction"
                :detect-error="detectErrorMessage"
                :has-isdoc="lastDetectHasIsdoc"
                @file-selected="onFileSelected"
                @files-selected="onFilesSelected"
                @clear="onPanelClear"
                @download="downloadAttachment"
                @select-attachment="onSelectAttachment"
                @remove-saved="onRemoveSavedAttachment"
              />
              <p
                v-if="localFirst"
                class="px-4 py-2 text-xs text-gray-500 border-t border-gray-100"
              >
                {{ t("invoicing.local_first_expenses_isdoc_bridge") }}
              </p>
            </div>
          </div>

          <div
            class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex justify-between text-sm font-medium"
          >
            <span class="text-gray-600">{{
              t("invoicing.expense_show_total_footer")
            }}</span>
            <span class="text-gray-900">{{
              formatMoney(expense.total, expense.currency)
            }}</span>
          </div>
        </div>

        <div class="invoicing-card-pad">
          <h2 class="invoicing-sf-label text-sm font-medium text-gray-800 mb-3">
            {{ t("invoicing.action_history") }}
          </h2>
          <ul v-if="history.length" class="space-y-2 text-sm text-gray-600">
            <li v-for="h in history" :key="h.id">
              <span class="text-gray-500">{{ formatDate(h.created_at) }}</span>
              : {{ historyLabel(h.action) }}
              <span v-if="h.user?.email" class="text-gray-400">
                / {{ h.user.email }}</span
              >
            </li>
          </ul>
          <p v-else class="text-sm text-gray-500">
            {{ t("invoicing.no_history") }}
          </p>
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
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import ExpenseAttachmentPanel from "../../components/invoicing/ExpenseAttachmentPanel.vue";
import ExpenseIsdocExtractModal from "../../components/invoicing/ExpenseIsdocExtractModal.vue";
import ExpenseShowSidebar from "../../components/invoicing/ExpenseShowSidebar.vue";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import {
  useExpenseIsdocAttachment,
  type ExpenseImportDraft,
} from "../../composables/useExpenseIsdocAttachment";
import { parseExpenseRowMeta } from "../../composables/useExpenseRowMeta";
import { useInvoicingExpense } from "../../composables/useInvoicingExpenses";
import { useInvoicingLayout } from "../../composables/useInvoicingLayout";
import {
  cancelLocalExpense,
  duplicateLocalExpense,
  markLocalExpensePaid,
  unmarkLocalExpensePaid,
  updateLocalExpense,
} from "../../evolu/expenseCrud";
import { releaseLocalExpenseBankClaim } from "../../evolu/bankCrud";
import { filterLocalExpenses, type EvoluExpenseRow } from "../../evolu/expenseMap";
import { toAppRows } from "../../evolu/queryLoad";
import {
  attachmentBlobUrl,
  attachmentContentBlob,
  deleteLocalExpenseAttachment,
  type EvoluExpenseAttachmentRow,
} from "../../evolu/expenseAttachmentCrud";
import { isInvoicingLocalFirst } from "../../evolu/flags";
import type { CompanyId, ExpenseAttachmentId, ExpenseId } from "../../evolu/schema";
import { updateLocalExpenseNote } from "../../evolu/expenseCrud";
import { expenseMutationErrorKey } from "../../utils/expenseError";
import { getWebBlob, invoicingApi } from "../../services/api";
import { useInvoicingSaveFeedback } from "../../composables/useInvoicingSaveFeedback";

type ExpenseAttachment = {
  id: string;
  original_filename: string | null;
  mime?: string | null;
  created_at?: string;
};

type Expense = {
  bank_match_conflict?: boolean;
  bank_match_claim?: string | null;
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
const { notifySaved } = useInvoicingSaveFeedback();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const expenseId = computed(() => route.params.expenseId as string);
const localFirst = isInvoicingLocalFirst();
const expenseResource = useInvoicingExpense(companyId, expenseId);
const expenseRows = computed(() => toAppRows<EvoluExpenseRow>(expenseResource.expenseRows.value));

const expense = ref<Expense | null>(null);
const history = ref<HistoryRow[]>([]);
const neighborIds = ref<string[]>([]);
const loading = ref(true);
const acting = ref(false);
const duplicating = ref(false);
const uploading = ref(false);
const processingExtraction = ref(false);
const noteDraft = ref("");
const noteSaving = ref(false);
const savedPreviewUrl = ref<string | null>(null);
const savedPreviewKind = ref<"pdf" | "image" | "other" | null>(null);
const selectedAttachmentId = ref<string | null>(null);
const uploadError = ref('');
const loadError = ref('');
let contextGeneration = 0;
let loadRequest = 0;
let previewRequest = 0;
let neighborRequest = 0;

function captureContext() {
  return { generation: contextGeneration, company: companyId.value, expense: expenseId.value };
}

function isCurrentContext(context: ReturnType<typeof captureContext>) {
  return context.generation === contextGeneration && context.company === companyId.value && context.expense === expenseId.value;
}

function hasLoadedExpense(context: ReturnType<typeof captureContext>) {
  return isCurrentContext(context) && !loading.value && expense.value?.id === context.expense;
}

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
} = useExpenseIsdocAttachment(companyId, expenseId);

const rowMeta = computed(() =>
  expense.value
    ? parseExpenseRowMeta(expense.value)
    : { category: "", supplier: "" },
);

const supplierName = computed(
  () =>
    rowMeta.value.supplier ||
    expense.value?.title ||
    expense.value?.internal_number ||
    "",
);

const categoryName = computed(() => rowMeta.value.category);

const displayName = computed(() => supplierName.value);

const tagsLine = computed(() => {
  const note = expense.value?.internal_note ?? "";
  const match = note.match(/Tags:\s*([^\n]+)/i);

  return match ? match[1].trim() : "";
});

const detectErrorMessage = computed(() => {
  if (!detectError.value) return "";
  if (detectError.value === "detect_failed") {
    return t("invoicing.expense_attachment_detect_failed");
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

  return (
    selectedAttachment.value?.original_filename ||
    expense.value?.original_filename ||
    ""
  );
});

const panelHasFile = computed(() =>
  Boolean(
    pendingAttachmentName.value ||
    savedAttachments.value.length > 0 ||
    expense.value?.attachment_path,
  ),
);

const panelPreviewUrl = computed(
  () => previewUrl.value || savedPreviewUrl.value,
);

const panelPreviewKind = computed(
  () => previewKind.value || savedPreviewKind.value,
);

const isOverdue = computed(() => {
  if (
    !expense.value ||
    expense.value.status !== "recorded" ||
    !expense.value.due_date
  )
    return false;

  return (
    expense.value.due_date.slice(0, 10) < new Date().toISOString().slice(0, 10)
  );
});

const neighborIndex = computed(() =>
  expenseId.value ? neighborIds.value.indexOf(expenseId.value) : -1,
);

// Keep the visible payment outcome current when another device's claims arrive.
watch(expenseResource.expense, (projected) => {
  if (!localFirst || !projected || !expense.value || loading.value
    || projected.id !== expenseId.value || expense.value.id !== expenseId.value) return;
  Object.assign(expense.value, {
    status: projected.status, paid_at: projected.paid_at,
    bank_match_conflict: projected.bank_match_conflict,
    bank_match_claim: projected.bank_match_claim,
  });
}, { flush: 'sync' });

watch(expense, (e) => {
  noteDraft.value = e?.internal_note ?? "";
});

function expenseListTo() {
  return { name: "invoicing-expenses", params: { companyId: companyId.value } };
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === "string" ? parseFloat(amount) : amount;

  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency: currency || "EUR",
  }).format(n || 0);
}

function formatDate(iso: string) {
  const d = (iso || "").slice(0, 10);
  const [y, m, day] = d.split("-");

  return day && m && y ? `${day}.${m}.${y}` : "-";
}

function historyLabel(action: string) {
  const map: Record<string, string> = {
    "business_expense.created": t("invoicing.expense_history_created"),
    "business_expense.updated": t("invoicing.expense_history_updated"),
    "business_expense.imported": t("invoicing.expense_history_imported"),
    "business_expense.duplicated": t("invoicing.expense_history_duplicated"),
    "business_expense.marked_paid": t("invoicing.expense_history_marked_paid"),
    "business_expense.unmarked_paid": t(
      "invoicing.expense_history_unmarked_paid",
    ),
    "business_expense.cancelled": t("invoicing.expense_history_cancelled"),
    "business_expense.attachment_uploaded": t(
      "invoicing.expense_history_attachment",
    ),
    "business_expense.attachment_removed": t(
      "invoicing.expense_history_attachment_removed",
    ),
  };

  return map[action] || action;
}

async function loadNeighbors() {
  const context = captureContext();
  const request = ++neighborRequest;
  const current = () => isCurrentContext(context) && request === neighborRequest;
  try {
    if (localFirst) {
      const rows = filterLocalExpenses(
        expenseRows.value.filter((row) => row.companyId === context.company),
        { year: new Date().getFullYear() },
      );
      if (current()) neighborIds.value = rows.map((row) => row.id);
      return;
    }
    const list = await invoicingApi.expenses.list<{ id: string }>(context.company, {
      per_page: 200,
      year: new Date().getFullYear(),
    });
    if (current()) neighborIds.value = list.map((e) => e.id);
  } catch {
    if (current()) neighborIds.value = [];
  }
}

function goNeighbor(delta: number) {
  const idx = neighborIndex.value + delta;
  if (idx < 0 || idx >= neighborIds.value.length) return;
  router.push({
    name: "invoicing-expense-show",
    params: { companyId: companyId.value, expenseId: neighborIds.value[idx] },
  });
}

async function load() {
  const context = captureContext();
  const request = ++loadRequest;
  const current = () => isCurrentContext(context) && request === loadRequest;
  loading.value = true;
  loadError.value = '';
  try {
    if (localFirst) {
      await expenseResource.refresh();
      if (!current()) return;
      const loaded = expenseResource.expense.value as Expense | null;
      if (loaded && loaded.id !== context.expense) throw new Error('expense_unavailable');
      expense.value = loaded;
      noteDraft.value = expense.value?.internal_note ?? "";
      history.value = [];
      selectedAttachmentId.value = expense.value?.attachments?.[0]?.id ?? null;
      await Promise.all([loadSavedPreview(), loadNeighbors()]);
      return;
    }
    const [expRes, histRes] = await Promise.all([
      invoicingApi.expenses.get<NonNullable<typeof expense.value>>(context.company, context.expense),
      invoicingApi.expenses.history<HistoryRow>(context.company, context.expense),
    ]);
    if (!current()) return;
    if (expRes.id !== context.expense) throw new Error('expense_unavailable');
    expense.value = expRes;
    history.value = histRes;
    selectedAttachmentId.value = expense.value?.attachments?.[0]?.id ?? null;
    await Promise.all([loadSavedPreview(), loadNeighbors()]);
  } catch {
    if (current()) {
      expense.value = null;
      history.value = [];
      loadError.value = t('errors.generic');
    }
  } finally {
    if (current()) loading.value = false;
  }
}

function revokeSavedPreview() {
  previewRequest++;
  if (savedPreviewUrl.value) {
    URL.revokeObjectURL(savedPreviewUrl.value);
    savedPreviewUrl.value = null;
  }
  savedPreviewKind.value = null;
}

function attachmentPreviewUrl(attachmentId?: string | null, company = companyId.value, id = expenseId.value) {
  if (attachmentId) {
    return `/api/invoicing/companies/${company}/expenses/${id}/attachments/${attachmentId}`;
  }

  return `/api/invoicing/companies/${company}/expenses/${id}/attachment`;
}

function localAttachmentRow(
  attachmentId?: string | null,
): EvoluExpenseAttachmentRow | null {
  if (!attachmentId || !expenseResource.attachmentRows) return null;
  return (
    (expenseResource.attachmentRows.value.find(
      (row) => row.id === attachmentId,
    ) as EvoluExpenseAttachmentRow | undefined) ?? null
  );
}

async function loadSavedPreview() {
  revokeSavedPreview();
  const context = captureContext();
  const request = previewRequest;
  const selected = selectedAttachmentId.value;
  const current = () => isCurrentContext(context) && request === previewRequest
    && selected === selectedAttachmentId.value && !pendingAttachmentName.value;
  if (pendingAttachmentName.value) return;

  const att = selectedAttachment.value;
  if (!att && !expense.value?.attachment_path) return;

  try {
    if (localFirst) {
      const row = localAttachmentRow(att?.id);
      if (!row) return;
      const blob = attachmentContentBlob(row);
      if (!blob) return;
      const name = att?.original_filename || "";
      if (
        name.toLowerCase().endsWith(".pdf") ||
        blob.type === "application/pdf"
      ) {
        savedPreviewKind.value = "pdf";
      } else if (blob.type.startsWith("image/")) {
        savedPreviewKind.value = "image";
      } else {
        savedPreviewKind.value = "other";
      }
      if (
        savedPreviewKind.value === "pdf" ||
        savedPreviewKind.value === "image"
      ) {
        savedPreviewUrl.value = URL.createObjectURL(blob);
      }
      return;
    }

    const blob = await getWebBlob(attachmentPreviewUrl(att?.id, context.company, context.expense));
    if (!current()) return;
    const name =
      att?.original_filename || expense.value?.original_filename || "";
    if (
      name.toLowerCase().endsWith(".pdf") ||
      blob.type === "application/pdf"
    ) {
      savedPreviewKind.value = "pdf";
    } else if (blob.type.startsWith("image/")) {
      savedPreviewKind.value = "image";
    } else {
      savedPreviewKind.value = "other";
    }
    if (
      savedPreviewKind.value === "pdf" ||
      savedPreviewKind.value === "image"
    ) {
      savedPreviewUrl.value = URL.createObjectURL(blob);
    }
  } catch {
    if (current()) savedPreviewKind.value = "other";
  }
}

function downloadAttachment() {
  const att = selectedAttachment.value;
  if (localFirst) {
    const row = localAttachmentRow(att?.id);
    const url = row ? attachmentBlobUrl(row) : null;
    if (url) {
      window.open(url, "_blank");
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    }
    return;
  }
  window.open(attachmentPreviewUrl(att?.id), "_blank");
}

function onSelectAttachment(id: string) {
  selectedAttachmentId.value = id;
  if (!pendingAttachmentName.value) {
    loadSavedPreview();
  }
}

async function onRemoveSavedAttachment(id: string) {
  const context = captureContext();
  if (!hasLoadedExpense(context)) return;
  if (!confirm(t("invoicing.expense_attachment_remove_confirm")) || !isCurrentContext(context)) return;
  try {
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error('local_context_unavailable');
      deleteLocalExpenseAttachment(
        expenseResource.evolu,
        id as ExpenseAttachmentId,
      );
      await expenseResource.refresh();
      if (!isCurrentContext(context)) return;
      expense.value = expenseResource.expense.value as Expense | null;
      if (selectedAttachmentId.value === id) {
        selectedAttachmentId.value =
          expense.value?.attachments?.[0]?.id ?? null;
      }
      await loadSavedPreview();
      return;
    }
    const updated = await invoicingApi.expenses.deleteAttachment<NonNullable<typeof expense.value>>(context.company, context.expense, id);
    if (!isCurrentContext(context)) return;
    expense.value = updated;
    if (selectedAttachmentId.value === id) {
      selectedAttachmentId.value = expense.value?.attachments?.[0]?.id ?? null;
    }
    await loadSavedPreview();
  } catch {
    if (isCurrentContext(context)) window.alert(t("invoicing.expense_attachment_remove_failed"));
  }
}

async function saveNote() {
  if (!expense.value || noteDraft.value === (expense.value.internal_note ?? ""))
    return;
  const context = captureContext();
  if (!hasLoadedExpense(context)) return;
  const note = noteDraft.value;
  noteSaving.value = true;
  try {
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error('local_context_unavailable');
      const result = await updateLocalExpenseNote(
        expenseResource.evolu,
        context.expense as ExpenseId,
        note,
        context.company as CompanyId,
      );
      if (!result.ok) throw new Error(t('errors.generic'));
      if (!isCurrentContext(context)) return;
      await expenseResource.refresh();
      if (!isCurrentContext(context)) return;
      expense.value = expenseResource.expense.value as Expense | null;
      notifySaved('invoicing.changes_saved');
      return;
    }
    const updated = await invoicingApi.expenses.update<NonNullable<typeof expense.value>>(context.company, context.expense, {
      internal_note: note,
    });
    if (!isCurrentContext(context)) return;
    expense.value = updated;
    notifySaved('invoicing.changes_saved');
  } catch (error: unknown) {
    if (isCurrentContext(context)) window.alert(t(expenseMutationErrorKey(error) || 'errors.generic'));
  } finally {
    if (isCurrentContext(context)) noteSaving.value = false;
  }
}

/** Copy the loaded expense in either mode and open its marked edit only if the captured detail context remains current. */
async function duplicate() {
  const context = captureContext();
  if (!hasLoadedExpense(context)) return;
  duplicating.value = true;
  try {
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error('local_context_unavailable');
      const source = expenseRows.value.find(
        (row) => row.id === context.expense && row.companyId === context.company,
      );
      if (!source) return;
      const result = await duplicateLocalExpense(
        expenseResource.evolu,
        source,
        expenseRows.value,
      );
      if (!isCurrentContext(context)) return;
      if (!result.ok) return;
      await router.push({
        name: "invoicing-expense-edit",
        params: { companyId: context.company, expenseId: result.value.id },
        query: { duplicate: result.value.id },
      });
      return;
    }
    const res = await invoicingApi.expenses.action<{ id: string }>(context.company, context.expense, 'duplicate');
    if (!isCurrentContext(context)) return;
    await router.push({
      name: "invoicing-expense-edit",
      params: { companyId: context.company, expenseId: res.id },
      query: { duplicate: res.id },
    });
  } catch (error: unknown) {
    if (isCurrentContext(context)) window.alert(t(expenseMutationErrorKey(error) || 'errors.generic'));
  } finally {
    if (isCurrentContext(context)) duplicating.value = false;
  }
}

/** Release only the displayed local conflict; a changed context or claim requires fresh review. */
async function releaseBankConflict() {
  const context = captureContext();
  if (!localFirst || !hasLoadedExpense(context) || !expenseResource.evolu || acting.value
    || !expense.value?.bank_match_conflict || expense.value.bank_match_claim == null) return;
  const expectedClaim = expense.value.bank_match_claim;
  if (!window.confirm(t('invoicing.bank_unlink_expense_conflict_confirm'))) return;
  acting.value = true;
  try {
    await releaseLocalExpenseBankClaim(expenseResource.evolu, context.company as CompanyId, context.expense as ExpenseId, expectedClaim);
    if (isCurrentContext(context)) await load();
  } catch (error: unknown) {
    if (isCurrentContext(context)) window.alert(t(expenseMutationErrorKey(error) || 'errors.generic'));
  } finally {
    if (isCurrentContext(context)) acting.value = false;
  }
}

async function markPaid() {
  const context = captureContext();
  if (!hasLoadedExpense(context)) return;
  acting.value = true;
  try {
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error('local_context_unavailable');
      const result = await markLocalExpensePaid(expenseResource.evolu, context.expense as ExpenseId);
      if (!isCurrentContext(context)) return;
      if (!result.ok) throw new Error(t('errors.generic'));
      await load();
      return;
    }
    const updated = await invoicingApi.expenses.action<NonNullable<typeof expense.value>>(context.company, context.expense, 'mark-paid');
    if (!isCurrentContext(context)) return;
    expense.value = updated;
    const updatedHistory = await invoicingApi.expenses.history<HistoryRow>(context.company, context.expense);
    if (isCurrentContext(context)) history.value = updatedHistory;
  } catch (error: unknown) {
    if (isCurrentContext(context)) window.alert(t(expenseMutationErrorKey(error) || 'errors.generic'));
  } finally {
    if (isCurrentContext(context)) acting.value = false;
  }
}

async function unmarkPaid() {
  const context = captureContext();
  if (!hasLoadedExpense(context)) return;
  acting.value = true;
  try {
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error('local_context_unavailable');
      const result = await unmarkLocalExpensePaid(
        expenseResource.evolu,
        context.expense as ExpenseId,
      );
      if (!isCurrentContext(context)) return;
      if (!result.ok) throw new Error(t('errors.generic'));
      await load();
      return;
    }
    const updated = await invoicingApi.expenses.action<NonNullable<typeof expense.value>>(context.company, context.expense, 'unmark-paid');
    if (isCurrentContext(context)) expense.value = updated;
  } catch (error: unknown) {
    if (isCurrentContext(context)) window.alert(t(expenseMutationErrorKey(error) || 'errors.generic'));
  } finally {
    if (isCurrentContext(context)) acting.value = false;
  }
}

async function cancelExpense() {
  const context = captureContext();
  if (!hasLoadedExpense(context)) return;
  if (!confirm(t("invoicing.expense_cancel_confirm")) || !isCurrentContext(context)) return;
  acting.value = true;
  try {
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error('local_context_unavailable');
      const result = await cancelLocalExpense(expenseResource.evolu, context.expense as ExpenseId);
      if (!isCurrentContext(context)) return;
      if (!result.ok) throw new Error(t('errors.generic'));
      await router.push(expenseListTo());
      return;
    }
    await invoicingApi.expenses.delete(context.company, context.expense);
    if (!isCurrentContext(context)) return;
    await router.push(expenseListTo());
  } catch (error: unknown) {
    if (isCurrentContext(context)) window.alert(t(expenseMutationErrorKey(error) || 'errors.generic'));
  } finally {
    if (isCurrentContext(context)) acting.value = false;
  }
}

function draftToPayload(draft: ExpenseImportDraft, current: Expense) {
  return {
    title: draft.title ?? current.title,
    external_number: draft.external_number ?? current.external_number,
    variable_symbol: draft.variable_symbol ?? current.variable_symbol,
    constant_symbol: draft.constant_symbol ?? current.constant_symbol,
    specific_symbol: draft.specific_symbol ?? current.specific_symbol,
    issue_date: draft.issue_date?.slice(0, 10) ?? current.issue_date,
    delivery_date:
      draft.delivery_date?.slice(0, 10) ?? current.delivery_date,
    due_date: draft.due_date?.slice(0, 10) ?? current.due_date,
    total: draft.total ?? current.total,
    currency: draft.currency ?? current.currency,
    internal_note: current.internal_note,
  };
}

async function uploadAttachmentOnly() {
  if (pendingFiles.value.length === 0) return;
  const context = captureContext();
  uploadError.value = '';
  uploading.value = true;
  try {
    if (localFirst && (!expenseResource.evolu || expense.value?.id !== context.expense
      || !expenseRows.value.some((row) => row.id === context.expense && row.companyId === context.company))) {
      throw new Error('expense_unavailable');
    }
    await uploadAllPendingAttachments(context.expense);
    if (isCurrentContext(context)) await refreshAttachmentState(context);
  } catch (error: unknown) {
    if (!isCurrentContext(context)) return;
    uploadError.value = error instanceof Error && error.message === 'expense_unavailable'
      ? t('invoicing.expense_unavailable')
      : t('invoicing.expense_attachment_upload_failed');
    // Successful files have already left the pending queue. Refresh their
    // progress once, while retaining the failed files for an explicit retry.
    try { await refreshAttachmentState(context); } catch { /* Keep the original visible error. */ }
  } finally {
    if (isCurrentContext(context)) uploading.value = false;
  }
}

async function refreshAttachmentState(context: ReturnType<typeof captureContext>) {
  if (localFirst) {
    await expenseResource.refresh();
    if (!isCurrentContext(context)) return;
    const loaded = expenseResource.expense.value as Expense | null;
    if (loaded && loaded.id !== context.expense) throw new Error('expense_unavailable');
    expense.value = loaded;
  } else {
    const loaded = await invoicingApi.expenses.get<NonNullable<typeof expense.value>>(context.company, context.expense);
    if (!isCurrentContext(context)) return;
    if (loaded.id !== context.expense) throw new Error('expense_unavailable');
    expense.value = loaded;
  }
  selectedAttachmentId.value = expense.value?.attachments?.at(-1)?.id ?? expense.value?.attachments?.[0]?.id ?? null;
  await loadSavedPreview();
}

async function retryAttachmentUpload() {
  if (uploading.value || detecting.value || extracting.value || processingExtraction.value || pendingFiles.value.length === 0) return;
  await uploadAttachmentOnly();
}

async function onFileSelected(file: File) {
  if (detecting.value || uploading.value || extracting.value || processingExtraction.value) return;
  uploadError.value = '';
  const capturedGeneration = contextGeneration;
  const requestedCompany = companyId.value;
  const requestedExpense = expenseId.value;
  try {
    await onDocumentSelected(file);
    if (capturedGeneration !== contextGeneration || requestedCompany !== companyId.value || requestedExpense !== expenseId.value) return;
    if (!showExtractModal.value) {
      await uploadAttachmentOnly();
    }
  } catch {
    if (capturedGeneration === contextGeneration) uploadError.value = t('invoicing.expense_attachment_upload_failed');
  }
}

async function onFilesSelected(files: File[]) {
  if (detecting.value || uploading.value || extracting.value || processingExtraction.value) return;
  uploadError.value = '';
  const capturedGeneration = contextGeneration;
  const requestedCompany = companyId.value;
  const requestedExpense = expenseId.value;
  try {
    await onDocumentsSelected(files);
    if (capturedGeneration !== contextGeneration || requestedCompany !== companyId.value || requestedExpense !== expenseId.value) return;
    if (!showExtractModal.value) {
      await uploadAttachmentOnly();
    }
  } catch {
    if (capturedGeneration === contextGeneration) uploadError.value = t('invoicing.expense_attachment_upload_failed');
  }
}

function onPanelClear() {
  uploadError.value = '';
  if (pendingFiles.value.length > 0) {
    clearPendingAttachment();

    return;
  }
  revokeSavedPreview();
}

async function onConfirmExtract() {
  if (extracting.value || processingExtraction.value) return;
  const requestedCompany = companyId.value;
  const requestedExpense = expenseId.value;
  const capturedGeneration = contextGeneration;
  const stillCurrent = () => capturedGeneration === contextGeneration && requestedCompany === companyId.value && requestedExpense === expenseId.value;
  processingExtraction.value = true;
  try {
    const draft = await confirmExtract();
    if (!stillCurrent()) return;
    if (!draft) return;
    if (draft) {
      if (localFirst) {
        if (!expenseResource.evolu || expense.value?.id !== requestedExpense) throw new Error(t('errors.generic'));
        const current = expenseRows.value.find((row) => row.id === requestedExpense && row.companyId === requestedCompany);
        if (!current) throw new Error(t('errors.generic'));
        const result = await updateLocalExpense(expenseResource.evolu, requestedExpense as ExpenseId, draftToPayload(draft, expense.value), current);
        if (!result.ok) throw new Error(t('errors.generic'));
        await expenseResource.refresh();
        if (!stillCurrent()) return;
        expense.value = expenseResource.expense.value as Expense | null;
      } else {
        if (!expense.value) throw new Error(t('errors.generic'));
        const updated = await invoicingApi.expenses.update<NonNullable<typeof expense.value>>(requestedCompany, requestedExpense, draftToPayload(draft, expense.value));
        if (!stillCurrent()) return;
        expense.value = updated;
      }
    }
    await uploadAttachmentOnly();
  } catch (e: unknown) {
    if (!stillCurrent()) return;
    const err = e as {
      response?: { data?: { errors?: { quota?: string[] }; message?: string } };
    };
    const mutationErrorKey = expenseMutationErrorKey(e);
    window.alert(
      mutationErrorKey ? t(mutationErrorKey) : err?.response?.data?.errors?.quota?.[0] ||
        err?.response?.data?.message ||
        t("invoicing.expense_extract_failed"),
    );
  } finally {
    if (stillCurrent()) processingExtraction.value = false;
  }
}

async function onSkipExtract() {
  if (!showExtractModal.value || extracting.value || uploading.value || processingExtraction.value) return;
  skipExtract();
  await uploadAttachmentOnly();
}

watch([companyId, expenseId], () => {
  contextGeneration++;
  loadRequest++;
  neighborRequest++;
  expense.value = null;
  history.value = [];
  neighborIds.value = [];
  selectedAttachmentId.value = null;
  noteDraft.value = '';
  loadError.value = '';
  uploadError.value = '';
  revokeSavedPreview();
  uploading.value = false;
  processingExtraction.value = false;
  acting.value = false;
  duplicating.value = false;
  noteSaving.value = false;
  void load();
}, { flush: 'sync' });

onMounted(() => {
  rememberCompany(companyId.value);
  if (!localFirst) {
    loadQuota();
  }
  void load();
});

onUnmounted(() => {
  contextGeneration++;
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
