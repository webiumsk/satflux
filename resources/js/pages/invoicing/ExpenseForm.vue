<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <RouterLink :to="expenseListTo()" class="invoicing-back mb-0">
        ← {{ t('invoicing.main_nav_expenses') }}
      </RouterLink>
    </template>

    <ExpenseIsdocExtractModal
      :open="showExtractModal"
      :extracting="extracting"
      :purchasing="purchasing"
      :quota="quota"
      :allow-purchases="!localFirst"
      @close="skipExtract"
      @skip="onSkipExtract"
      @confirm="onConfirmExtract"
      @purchase="purchasePack"
    />

    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
      <h1 class="invoicing-title">
        {{ isNew ? t('invoicing.expenses_new') : expenseLabel }}
      </h1>
      <button type="button" class="invoicing-btn-primary" :disabled="saving" @click="save">
        {{ t('common.save') }}
      </button>
    </div>

    <div
      v-if="importNotice"
      class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900"
    >
      {{ importNotice }}
    </div>

    <ExpenseDuplicateWarning
      class="mb-4"
      :company-id="companyId"
      :draft="form"
      :exclude-id="expenseId || createdLocalExpenseId || undefined"
      :local-first="localFirst"
      :local-rows="expenseRows"
      :evolu="expenseResource.evolu"
    />

    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(320px,42%)] gap-6 items-start">
      <form class="invoicing-card-pad space-y-4 min-w-0" @submit.prevent="save">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.expense_col_title') }}</label>
          <input v-model="form.title" type="text" class="invoicing-sf-input w-full" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_external') }}</label>
            <input v-model="form.external_number" type="text" class="invoicing-sf-input w-full" @input="syncVariableSymbolDefault" />
          </div>
          <div v-if="!isNew">
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_internal') }}</label>
            <input :value="internalNumber" type="text" class="invoicing-sf-input w-full bg-gray-50" readonly />
          </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.variable_symbol') }}</label>
            <input v-model="form.variable_symbol" type="text" class="invoicing-sf-input w-full" @input="variableSymbolFollowsNumber = false" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_ks') }}</label>
            <input v-model="form.constant_symbol" type="text" class="invoicing-sf-input w-full" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_ss') }}</label>
            <input v-model="form.specific_symbol" type="text" class="invoicing-sf-input w-full" />
          </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_issue') }} *</label>
            <input v-model="form.issue_date" type="date" class="invoicing-sf-input w-full" required @input="syncDeliveryDateDefault" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_delivery') }}</label>
            <input v-model="form.delivery_date" type="date" class="invoicing-sf-input w-full" @input="deliveryDateFollowsIssueDate = false" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_due') }}</label>
            <input v-model="form.due_date" type="date" class="invoicing-sf-input w-full" />
          </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_total') }} *</label>
            <input v-model.number="form.total" type="number" min="0" step="0.01" class="invoicing-sf-input w-full" required />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.currency') }}</label>
            <select v-model="form.currency" class="invoicing-sf-input w-full">
              <option value="EUR">EUR</option>
              <option value="CZK">CZK</option>
              <option value="USD">USD</option>
            </select>
          </div>
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.expense_internal_note') }}</label>
          <textarea v-model="form.internal_note" rows="3" class="invoicing-sf-input w-full"></textarea>
        </div>
        <label v-if="isNew" class="flex items-center gap-2 text-sm">
          <input v-model="form.mark_paid" type="checkbox" class="rounded border-gray-300" />
          {{ t('invoicing.expense_mark_paid_on_create') }}
        </label>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" class="invoicing-btn-primary" :disabled="saving">{{ t('common.save') }}</button>
      </form>

      <ExpenseAttachmentPanel
        allow-multiple
        :file-name="pendingAttachmentName"
        :has-file="pendingFiles.length > 0"
        :has-pending="pendingFiles.length > 0"
        :pending-files="pendingFileSummaries"
        :selected-pending-id="selectedPendingId"
        :preview-url="previewUrl"
        :preview-kind="previewKind"
        :detecting="detecting || saving || extracting"
        :detect-error="detectErrorMessage"
        :has-isdoc="lastDetectHasIsdoc"
        @file-selected="onFileSelected"
        @files-selected="onFilesSelected"
        @select-pending="selectPendingFile"
        @remove-pending="removePendingFile"
      />
      <p v-if="localFirst" class="text-xs text-gray-500">
        {{ t('invoicing.local_first_expenses_isdoc_bridge') }}
      </p>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import ExpenseDuplicateWarning from "../../components/invoicing/ExpenseDuplicateWarning.vue";
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { sqliteTrue } from '@evolu/common';
import ExpenseAttachmentPanel from '../../components/invoicing/ExpenseAttachmentPanel.vue';
import ExpenseIsdocExtractModal from '../../components/invoicing/ExpenseIsdocExtractModal.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import {
  useExpenseIsdocAttachment,
  type ExpenseImportDraft,
} from '../../composables/useExpenseIsdocAttachment';
import { useInvoicingCompanySummary } from '../../composables/useInvoicingCompanySummary';
import { useInvoicingExpense } from '../../composables/useInvoicingExpenses';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { insertLocalExpense, updateLocalExpense } from '../../evolu/expenseCrud';
import { expenseMutationErrorKey } from '../../utils/expenseError';
import { normalizeVariableSymbol } from '../../evolu/bankSymbolNormalizer';
import { expenseByIdIncludingDeletedQuery } from '../../evolu/client';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import type { EvoluExpenseRow } from '../../evolu/expenseMap';
import { loadEvoluQueryWithTimeout, toAppRows } from '../../evolu/queryLoad';
import type { CompanyId, ExpenseId } from '../../evolu/schema';
import { invoicingApi } from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const expenseId = computed(() => route.params.expenseId as string | undefined);
const isNew = computed(() => route.name === 'invoicing-expense-new');
// A persisted copy keeps edit semantics while its initial edit context can follow defaults.
const duplicateEditActive = ref(route.name === 'invoicing-expense-edit'
  && typeof route.query.duplicate === 'string' && route.query.duplicate === expenseId.value);
watch([companyId, expenseId, () => route.name], () => {
  duplicateEditActive.value = false;
}, { flush: 'sync' });
const localFirst = isInvoicingLocalFirst();
const expenseResource = useInvoicingExpense(companyId, expenseId);
const expenseRows = computed(() => toAppRows<EvoluExpenseRow>(expenseResource.expenseRows.value));
const { defaultCurrency } = useInvoicingCompanySummary();

const {
  quota,
  pendingFiles,
  pendingFileSummaries,
  selectedPendingId,
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
  onDocumentSelected,
  onDocumentsSelected,
  selectPendingFile,
  removePendingFile,
  confirmExtract,
  skipExtract,
  uploadAllPendingAttachments,
} = useExpenseIsdocAttachment(companyId, expenseId);

const detectErrorMessage = computed(() => {
  if (!detectError.value) return '';
  if (detectError.value === 'detect_failed') {
    return t('invoicing.expense_attachment_detect_failed');
  }
  return detectError.value;
});

const today = new Date().toISOString().slice(0, 10);

const form = ref({
  title: '',
  external_number: '',
  variable_symbol: '',
  constant_symbol: '',
  specific_symbol: '',
  issue_date: today,
  delivery_date: today,
  due_date: '',
  total: 0,
  currency: 'EUR',
  internal_note: '',
  mark_paid: false,
});

const internalNumber = ref('');
const expenseLabel = ref('');
const saving = ref(false);
const error = ref('');
const importNotice = ref('');
const createdLocalExpenseId = ref<string | null>(null);
const variableSymbolFollowsNumber = ref(true);
const deliveryDateFollowsIssueDate = ref(true);
let contextGeneration = 0;
watch([companyId, expenseId], () => {
  contextGeneration++;
  createdLocalExpenseId.value = null;
  saving.value = false;
  error.value = '';
}, { flush: 'sync' });
onUnmounted(() => {
  contextGeneration++;
  duplicateEditActive.value = false;
});

/** Follow document-number input for new or initial copy edits until a manual/imported VS overrides it. */
function syncVariableSymbolDefault() {
  if ((isNew.value || duplicateEditActive.value) && variableSymbolFollowsNumber.value) {
    const symbol = normalizeVariableSymbol(form.value.external_number) || '';
    form.value.variable_symbol = symbol.length <= 32 ? symbol : '';
  }
}

/** Follow issue-date input for new or initial copy edits until the delivery date is explicitly overridden. */
function syncDeliveryDateDefault() {
  if ((isNew.value || duplicateEditActive.value) && deliveryDateFollowsIssueDate.value) {
    form.value.delivery_date = form.value.issue_date;
  }
}

function applyImportDraft(draft: ExpenseImportDraft) {
  if (draft.title) form.value.title = draft.title;
  if (draft.external_number) {
    form.value.external_number = draft.external_number;
    syncVariableSymbolDefault();
  }
  if (draft.variable_symbol) {
    form.value.variable_symbol = draft.variable_symbol;
    variableSymbolFollowsNumber.value = false;
  }
  if (draft.constant_symbol) form.value.constant_symbol = draft.constant_symbol;
  if (draft.specific_symbol) form.value.specific_symbol = draft.specific_symbol;
  if (draft.issue_date) {
    form.value.issue_date = draft.issue_date.slice(0, 10);
    syncDeliveryDateDefault();
  }
  if (draft.delivery_date) {
    form.value.delivery_date = draft.delivery_date.slice(0, 10);
    deliveryDateFollowsIssueDate.value = false;
  }
  if (draft.due_date) form.value.due_date = draft.due_date.slice(0, 10);
  if (draft.total != null) form.value.total = Number(draft.total);
  if (draft.currency) form.value.currency = draft.currency;
  importNotice.value = t('invoicing.expense_import_prefilled');
}

async function onFileSelected(file: File) {
  if (saving.value || extracting.value || detecting.value) return;
  error.value = '';
  await onDocumentSelected(file);
}

async function onFilesSelected(files: File[]) {
  if (saving.value || extracting.value || detecting.value) return;
  error.value = '';
  await onDocumentsSelected(files);
}

async function onConfirmExtract() {
  const capturedGeneration = contextGeneration;
  const requestedCompany = companyId.value;
  const requestedExpense = expenseId.value;
  error.value = '';
  try {
    const draft = await confirmExtract();
    if (capturedGeneration !== contextGeneration || requestedCompany !== companyId.value || requestedExpense !== expenseId.value) return;
    if (draft) applyImportDraft(draft);
  } catch (e: unknown) {
    if (capturedGeneration !== contextGeneration) return;
    const err = e as { response?: { data?: { errors?: { quota?: string[] }; message?: string } } };
    const quotaMsg = err?.response?.data?.errors?.quota?.[0];
    error.value = quotaMsg || err?.response?.data?.message || t('invoicing.expense_extract_failed');
    skipExtract();
  }
}

function onSkipExtract() {
  skipExtract();
}

function expenseListTo() {
  return { name: 'invoicing-expenses', params: { companyId: companyId.value } };
}

function expenseShowTo(id: string) {
  return { name: 'invoicing-expense-show', params: { companyId: companyId.value, expenseId: id } };
}

async function loadExpense() {
  if (isNew.value) return;
  if (localFirst) {
    await expenseResource.refresh();
    const e = expenseResource.expense.value;
    if (!e) return;
    internalNumber.value = e.internal_number;
    expenseLabel.value = e.internal_number + (e.title ? ` · ${e.title}` : '');
    form.value = {
      title: e.title || '',
      external_number: e.external_number || '',
      variable_symbol: e.variable_symbol || '',
      constant_symbol: e.constant_symbol || '',
      specific_symbol: e.specific_symbol || '',
      issue_date: (e.issue_date || '').slice(0, 10),
      delivery_date: (e.delivery_date || '').slice(0, 10),
      due_date: e.due_date ? e.due_date.slice(0, 10) : '',
      total: parseFloat(e.total) || 0,
      currency: e.currency || 'EUR',
      internal_note: e.internal_note || '',
      mark_paid: false,
    };
    return;
  }
  const e = await invoicingApi.expenses.get<Record<string, any>>(companyId.value, expenseId.value!);
  internalNumber.value = e.internal_number;
  expenseLabel.value = e.internal_number + (e.title ? ` · ${e.title}` : '');
  form.value = {
    title: e.title || '',
    external_number: e.external_number || '',
    variable_symbol: e.variable_symbol || '',
    constant_symbol: e.constant_symbol || '',
    specific_symbol: e.specific_symbol || '',
    issue_date: (e.issue_date || '').slice(0, 10),
    delivery_date: (e.delivery_date || '').slice(0, 10),
    due_date: e.due_date ? e.due_date.slice(0, 10) : '',
    total: parseFloat(e.total) || 0,
    currency: e.currency || 'EUR',
    internal_note: e.internal_note || '',
    mark_paid: false,
  };
}

/**
 * Create new expenses or update persisted edits/copies, then await all pending attachments.
 * Navigate only within the captured context, replacing the initial copy edit and pushing ordinary saves.
 */
async function save() {
  if (saving.value || extracting.value || detecting.value) return;
  const requestedCompany = companyId.value;
  const requestedExpense = expenseId.value;
  const requestedNew = isNew.value;
  const requestedDuplicate = duplicateEditActive.value;
  const capturedGeneration = contextGeneration;
  const stillCurrent = () => capturedGeneration === contextGeneration && requestedCompany === companyId.value && requestedExpense === expenseId.value && requestedNew === isNew.value
    && (!requestedDuplicate || duplicateEditActive.value);
  error.value = '';
  if (!form.value.issue_date) {
    error.value = t('validation.required');
    return;
  }
  saving.value = true;
  try {
    const payload = {
      title: form.value.title || null,
      external_number: form.value.external_number || null,
      variable_symbol: form.value.variable_symbol || null,
      constant_symbol: form.value.constant_symbol || null,
      specific_symbol: form.value.specific_symbol || null,
      issue_date: form.value.issue_date,
      delivery_date: form.value.delivery_date || null,
      due_date: form.value.due_date || null,
      total: form.value.total,
      currency: form.value.currency,
      internal_note: form.value.internal_note || null,
      mark_paid: isNew.value ? form.value.mark_paid : undefined,
    };
    let savedId: string;
    if (localFirst) {
      if (!expenseResource.evolu) throw new Error(t('errors.generic'));
      if (requestedNew && !createdLocalExpenseId.value) {
        const result = await insertLocalExpense(
          expenseResource.evolu,
          requestedCompany as CompanyId,
          payload,
          expenseRows.value,
        );
        if (!stillCurrent()) return;
        if (!result.ok) {
          error.value = t('errors.generic');
          return;
        }
        savedId = result.value.id;
        createdLocalExpenseId.value = savedId;
      } else {
        const targetId = requestedNew ? createdLocalExpenseId.value! : requestedExpense!;
        const targets = await loadEvoluQueryWithTimeout(expenseResource.evolu, expenseByIdIncludingDeletedQuery(targetId as ExpenseId));
        if (!stillCurrent()) return;
        const current = toAppRows<EvoluExpenseRow & { isDeleted: number | null }>(targets).find((row) => row.id === targetId);
        if (!current || current.companyId !== requestedCompany || current.isDeleted === sqliteTrue) {
          throw new Error('expense_unavailable');
        }
        const result = await updateLocalExpense(
          expenseResource.evolu,
          targetId as ExpenseId,
          payload,
          current ?? null,
        );
        if (!stillCurrent()) return;
        if (!result.ok) {
          error.value = t('errors.generic');
          return;
        }
        savedId = targetId;
      }
      await uploadAllPendingAttachments(savedId);
      if (!stillCurrent()) return;
      await (requestedDuplicate ? router.replace(expenseShowTo(savedId)) : router.push(expenseShowTo(savedId)));
      return;
    }
    if (requestedNew) {
      const created = await invoicingApi.expenses.create<{ id: string }>(requestedCompany, payload);
      savedId = created.id;
    } else {
      await invoicingApi.expenses.update(requestedCompany, requestedExpense!, payload);
      savedId = requestedExpense!;
    }
    if (!stillCurrent()) return;
    await uploadAllPendingAttachments(savedId);
    if (!stillCurrent()) return;
    await (requestedDuplicate ? router.replace(expenseShowTo(savedId)) : router.push(expenseShowTo(savedId)));
  } catch (e: unknown) {
    if (!stillCurrent()) return;
    const err = e as { response?: { data?: { message?: string } } };
    const mutationErrorKey = expenseMutationErrorKey(e);
    error.value = mutationErrorKey ? t(mutationErrorKey) : e instanceof Error && e.message === 'expense_unavailable'
      ? t('invoicing.expense_unavailable')
      : err?.response?.data?.message || t('errors.generic');
  } finally {
    if (stillCurrent()) saving.value = false;
  }
}

onMounted(async () => {
  rememberCompany(companyId.value);
  if (!localFirst) {
    await loadQuota();
    const companyRec = await invoicingApi.companies.get<{ default_currency?: string }>(companyId.value);
    form.value.currency = companyRec?.default_currency || 'EUR';
  } else {
    form.value.currency = defaultCurrency.value || 'EUR';
  }
  await loadExpense();
});
</script>
