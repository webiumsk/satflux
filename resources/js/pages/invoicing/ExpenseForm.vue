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

    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(320px,42%)] gap-6 items-start">
      <form class="invoicing-card-pad space-y-4 min-w-0" @submit.prevent="save">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.expense_col_title') }}</label>
          <input v-model="form.title" type="text" class="invoicing-sf-input w-full" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_external') }}</label>
            <input v-model="form.external_number" type="text" class="invoicing-sf-input w-full" />
          </div>
          <div v-if="!isNew">
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_internal') }}</label>
            <input :value="internalNumber" type="text" class="invoicing-sf-input w-full bg-gray-50" readonly />
          </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.variable_symbol') }}</label>
            <input v-model="form.variable_symbol" type="text" class="invoicing-sf-input w-full" />
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
            <input v-model="form.issue_date" type="date" class="invoicing-sf-input w-full" required />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.expense_col_delivery') }}</label>
            <input v-model="form.delivery_date" type="date" class="invoicing-sf-input w-full" />
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
        :detecting="detecting"
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
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
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
import { isInvoicingLocalFirst } from '../../evolu/flags';
import type { CompanyId, ExpenseId } from '../../evolu/schema';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const expenseId = computed(() => route.params.expenseId as string | undefined);
const isNew = computed(() => route.name === 'invoicing-expense-new');
const localFirst = isInvoicingLocalFirst();
const expenseResource = useInvoicingExpense(companyId, expenseId);
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
} = useExpenseIsdocAttachment(companyId);

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

function applyImportDraft(draft: ExpenseImportDraft) {
  if (draft.title) form.value.title = draft.title;
  if (draft.external_number) form.value.external_number = draft.external_number;
  if (draft.variable_symbol) form.value.variable_symbol = draft.variable_symbol;
  if (draft.constant_symbol) form.value.constant_symbol = draft.constant_symbol;
  if (draft.specific_symbol) form.value.specific_symbol = draft.specific_symbol;
  if (draft.issue_date) form.value.issue_date = draft.issue_date.slice(0, 10);
  if (draft.delivery_date) form.value.delivery_date = draft.delivery_date.slice(0, 10);
  if (draft.due_date) form.value.due_date = draft.due_date.slice(0, 10);
  if (draft.total != null) form.value.total = Number(draft.total);
  if (draft.currency) form.value.currency = draft.currency;
  importNotice.value = t('invoicing.expense_import_prefilled');
}

async function onFileSelected(file: File) {
  error.value = '';
  await onDocumentSelected(file);
}

async function onFilesSelected(files: File[]) {
  error.value = '';
  await onDocumentsSelected(files);
}

async function onConfirmExtract() {
  error.value = '';
  try {
    const draft = await confirmExtract();
    if (draft) applyImportDraft(draft);
  } catch (e: unknown) {
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
      delivery_date: (e.delivery_date || e.issue_date || '').slice(0, 10),
      due_date: e.due_date ? e.due_date.slice(0, 10) : '',
      total: parseFloat(e.total) || 0,
      currency: e.currency || 'EUR',
      internal_note: e.internal_note || '',
      mark_paid: false,
    };
    return;
  }
  const res = await api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`);
  const e = res.data.data;
  internalNumber.value = e.internal_number;
  expenseLabel.value = e.internal_number + (e.title ? ` · ${e.title}` : '');
  form.value = {
    title: e.title || '',
    external_number: e.external_number || '',
    variable_symbol: e.variable_symbol || '',
    constant_symbol: e.constant_symbol || '',
    specific_symbol: e.specific_symbol || '',
    issue_date: (e.issue_date || '').slice(0, 10),
    delivery_date: (e.delivery_date || e.issue_date || '').slice(0, 10),
    due_date: e.due_date ? e.due_date.slice(0, 10) : '',
    total: parseFloat(e.total) || 0,
    currency: e.currency || 'EUR',
    internal_note: e.internal_note || '',
    mark_paid: false,
  };
}

async function save() {
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
      delivery_date: form.value.delivery_date || form.value.issue_date,
      due_date: form.value.due_date || null,
      total: form.value.total,
      currency: form.value.currency,
      internal_note: form.value.internal_note || null,
      mark_paid: isNew.value ? form.value.mark_paid : undefined,
    };
    let savedId: string;
    if (localFirst && expenseResource.evolu) {
      if (isNew.value) {
        const result = insertLocalExpense(
          expenseResource.evolu,
          companyId.value as CompanyId,
          payload,
          expenseResource.expenseRows.value,
        );
        if (!result.ok) {
          error.value = t('errors.generic');
          return;
        }
        savedId = result.value.id;
      } else {
        const current = expenseResource.expenseRows.value.find((row) => row.id === expenseId.value);
        const result = updateLocalExpense(
          expenseResource.evolu,
          expenseId.value! as ExpenseId,
          payload,
          current ?? null,
        );
        if (!result.ok) {
          error.value = t('errors.generic');
          return;
        }
        savedId = expenseId.value!;
      }
      await router.push(expenseShowTo(savedId));
      return;
    }
    if (isNew.value) {
      const res = await api.post(`/invoicing/companies/${companyId.value}/expenses`, payload);
      savedId = res.data.data.id;
    } else {
      await api.patch(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`, payload);
      savedId = expenseId.value!;
    }
    await uploadAllPendingAttachments(savedId);
    await router.push(expenseShowTo(savedId));
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } };
    error.value = err?.response?.data?.message || t('errors.generic');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  rememberCompany(companyId.value);
  if (!localFirst) {
    await loadQuota();
    const companyRes = await api.get(`/invoicing/companies/${companyId.value}`);
    form.value.currency = companyRes.data.data?.default_currency || 'EUR';
  } else {
    form.value.currency = defaultCurrency.value || 'EUR';
  }
  await loadExpense();
});
</script>
