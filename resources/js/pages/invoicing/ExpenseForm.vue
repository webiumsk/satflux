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
        {{ isNew ? t(isCreditNote ? 'invoicing.expense_credit_note_new' : 'invoicing.expenses_new') : expenseLabel }}
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
          <label for="expense-document-kind" class="invoicing-sf-label">{{ t('invoicing.expense_document_kind') }}</label>
          <select id="expense-document-kind" v-model="documentKind" class="invoicing-sf-input w-full" @change="onDocumentKindChange">
            <option value="expense">{{ t('invoicing.expense_document_expense') }}</option>
            <option value="credit_note">{{ t('invoicing.expense_received_credit_note') }}</option>
          </select>
        </div>
        <div>
          <label for="expense-supplier-contact" class="invoicing-sf-label">{{ t('invoicing.expense_supplier_contact') }}</label>
          <select id="expense-supplier-contact" v-model="form.company_contact_id" class="invoicing-sf-input w-full" :disabled="contactsLoading" @change="onSupplierContactChange">
            <option value="">{{ t('invoicing.expense_supplier_contact_none') }}</option>
            <option v-if="form.company_contact_id && !selectedContact" :value="form.company_contact_id">{{ t('invoicing.expense_supplier_contact_unavailable') }}</option>
            <option v-for="contact in supplierContacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
          </select>
          <p v-if="contactsFailed" class="mt-1 text-xs text-amber-800" role="status">
            {{ t('invoicing.expense_supplier_contacts_failed') }}
            <button type="button" class="underline" @click="refreshContacts">{{ t('common.retry') }}</button>
          </p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="expense-supplier-name" class="invoicing-sf-label">{{ t('invoicing.expense_supplier_name') }}</label>
            <input id="expense-supplier-name" v-model="form.supplier_name" type="text" maxlength="255" class="invoicing-sf-input w-full" @input="supplierNameManuallyEdited = true" />
          </div>
          <div>
            <label for="expense-supplier-iban" class="invoicing-sf-label">{{ t('invoicing.expense_supplier_iban') }}</label>
            <input id="expense-supplier-iban" v-model="form.supplier_iban" type="text" maxlength="64" class="invoicing-sf-input w-full font-mono" autocomplete="off" @input="supplierIbanManuallyEdited = true" />
          </div>
        </div>
        <p v-if="form.payment_review_required" class="text-xs text-amber-800" role="status">{{ t('invoicing.expense_payment_review_required') }}</p>
        <p v-else class="text-xs text-gray-500">{{ t('invoicing.expense_supplier_iban_hint') }}</p>
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
            <input v-model.number="form.total" type="number" :min="isCreditNote ? undefined : 0" :max="isCreditNote ? -0.01 : undefined" step="0.01" class="invoicing-sf-input w-full" required />
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
import { createQuery } from '@evolu/common/local-first';
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
import { useExpenseSupplierContacts } from '../../composables/useExpenseSupplierContacts';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { insertLocalExpense, updateLocalExpense } from '../../evolu/expenseCrud';
import { expenseMutationErrorKey } from '../../utils/expenseError';
import { normalizeVariableSymbol } from '../../evolu/bankSymbolNormalizer';
import { expenseByIdIncludingDeletedQuery } from '../../evolu/client';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import { evoluExpenseToApi, type ExpenseApiRow, type EvoluExpenseRow } from '../../evolu/expenseMap';
import { normalizePaymentIban } from '../../utils/paymentIban';
import { expensePaymentReviewRequired } from '../../utils/expensePaymentReview';
import { companyShareRoutingKey, ownerIdForCompany, waitCompanyShareRegistryReady } from '../../evolu/companyShareRegistry';
import { withTimeout } from '../../evolu/asyncTimeout';
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
const { contacts: supplierContacts, loading: contactsLoading, failed: contactsFailed, refresh: refreshContacts } = useExpenseSupplierContacts(companyId);

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
  company_contact_id: '',
  supplier_name: '',
  supplier_iban: '',
  payment_review_required: false,
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
const documentKind = ref(route.query.credit_note === '1' ? 'credit_note' : 'expense');
const isCreditNote = computed(() => documentKind.value === 'credit_note');
const selectedContact = computed(() => supplierContacts.value.find(contact => contact.id === form.value.company_contact_id));
let extractedSupplier: { name: string; iban: string } | null = null;
const supplierNameManuallyEdited = ref(false);
const supplierIbanManuallyEdited = ref(false);

function onDocumentKindChange() {
  form.value.total = Math.abs(Number(form.value.total) || 0) * (isCreditNote.value ? -1 : 1);
}

/** Only deliberate selection replaces the stored supplier and payment snapshots. */
function onSupplierContactChange() {
  const contact = selectedContact.value;
  const sameSupplier = (name: string) => extractedSupplier?.name.trim().toLocaleLowerCase() === name.trim().toLocaleLowerCase();
  form.value.supplier_name = contact?.name ?? extractedSupplier?.name ?? '';
  form.value.supplier_iban = contact?.iban?.trim() || (extractedSupplier && (!contact || sameSupplier(contact.name)) ? extractedSupplier.iban : '');
  supplierNameManuallyEdited.value = false;
  supplierIbanManuallyEdited.value = false;
}

const internalNumber = ref('');
const expenseLabel = ref('');
const saving = ref(false);
const error = ref('');
const importNotice = ref('');
const createdLocalExpenseId = ref<string | null>(null);
const variableSymbolFollowsNumber = ref(true);
const deliveryDateFollowsIssueDate = ref(true);
let contextGeneration = 0;
let sourceReadController: AbortController | null = null;
watch([companyId, expenseId], () => {
  contextGeneration++;
  createdLocalExpenseId.value = null;
  saving.value = false;
  error.value = '';
  extractedSupplier = null;
  form.value.company_contact_id = '';
  form.value.supplier_name = '';
  form.value.supplier_iban = '';
  form.value.payment_review_required = false;
  supplierNameManuallyEdited.value = false;
  supplierIbanManuallyEdited.value = false;
  sourceReadController?.abort();
}, { flush: 'sync' });
onUnmounted(() => {
  contextGeneration++;
  duplicateEditActive.value = false;
  sourceReadController?.abort();
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
  // Supplier identity and account come from structured fields, never copied prose.
  extractedSupplier = { name: draft.supplier_name?.trim() ?? '', iban: draft.supplier_iban?.trim() ?? '' };
  form.value.payment_review_required ||= draft.payment_review_required !== false;
  const contact = selectedContact.value;
  const sameSupplier = (name: string) => extractedSupplier!.name.trim().toLocaleLowerCase() === name.trim().toLocaleLowerCase();
  const supplierConflict = Boolean((supplierNameManuallyEdited.value || supplierIbanManuallyEdited.value)
    && form.value.supplier_name && extractedSupplier.name && !sameSupplier(form.value.supplier_name));
  if (supplierConflict) form.value.payment_review_required = true;
  if (!supplierNameManuallyEdited.value) form.value.supplier_name = contact?.name ?? extractedSupplier.name;
  if (!supplierIbanManuallyEdited.value) form.value.supplier_iban = supplierConflict ? '' : contact?.iban?.trim()
    || (!contact || sameSupplier(contact.name) ? extractedSupplier.iban : '');
  if (draft.internal_note?.trim() && !form.value.internal_note.includes(draft.internal_note.trim())) {
    form.value.internal_note = [form.value.internal_note.trim(), draft.internal_note.trim()].filter(Boolean).join('\n\n');
  }
  form.value.variable_symbol = draft.variable_symbol ?? '';
  variableSymbolFollowsNumber.value = false;
  if (draft.title) form.value.title = draft.title;
  if (draft.external_number) {
    form.value.external_number = draft.external_number;
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
  if (draft.total != null) {
    form.value.total = Number(draft.total);
    documentKind.value = form.value.total < 0 ? 'credit_note' : 'expense';
  }
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
      company_contact_id: e.company_contact_id || '',
      supplier_name: e.supplier_name || '',
      supplier_iban: e.supplier_iban || '',
      payment_review_required: expensePaymentReviewRequired(e.payment_review_required, e.internal_note),
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
    documentKind.value = form.value.total < 0 ? 'credit_note' : 'expense';
    supplierNameManuallyEdited.value = true;
    supplierIbanManuallyEdited.value = true;
    return;
  }
  const e = await invoicingApi.expenses.get<ExpenseApiRow>(companyId.value, expenseId.value!);
  internalNumber.value = e.internal_number;
  expenseLabel.value = e.internal_number + (e.title ? ` · ${e.title}` : '');
  form.value = {
    company_contact_id: e.company_contact_id || '',
    supplier_name: e.supplier_name || '',
    supplier_iban: e.supplier_iban || '',
    payment_review_required: expensePaymentReviewRequired(e.payment_review_required, e.internal_note),
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
  documentKind.value = form.value.total < 0 ? 'credit_note' : 'expense';
  supplierNameManuallyEdited.value = true;
  supplierIbanManuallyEdited.value = true;
}

/** Prefill a new received correction without claiming a persisted document relationship. */
async function prefillCreditNote() {
  const sourceId = route.query.source_expense;
  if (!isNew.value || !isCreditNote.value || typeof sourceId !== 'string') return;
  const generation = contextGeneration;
  const company = companyId.value;
  let source: ExpenseApiRow;
  if (localFirst) {
    if (!expenseResource.evolu) return;
    sourceReadController?.abort();
    const controller = new AbortController();
    sourceReadController = controller;
    try { await withTimeout(waitCompanyShareRegistryReady(controller.signal), 30_000, 'expense_source_timeout'); }
    finally { controller.abort(); }
    if (generation !== contextGeneration || company !== companyId.value) return;
    const routingKey = companyShareRoutingKey(company);
    const owner = ownerIdForCompany(company) ?? (await withTimeout(expenseResource.evolu.appOwner, 30_000, 'expense_source_timeout')).id;
    if (generation !== contextGeneration || company !== companyId.value || routingKey !== companyShareRoutingKey(company)) return;
    const query = createQuery(db => db.selectFrom('expense').selectAll()
      .where('id', '=', sourceId as ExpenseId).where('companyId', '=', company as CompanyId)
      .where('ownerId', '=', owner).where('isDeleted', 'is not', sqliteTrue));
    const rows = await loadEvoluQueryWithTimeout(expenseResource.evolu, query);
    if (routingKey !== companyShareRoutingKey(company)) return;
    const row = toAppRows<EvoluExpenseRow & { isDeleted: number | null }>(rows).find(entry => entry.id === sourceId && entry.companyId === company
      && Reflect.get(entry, 'ownerId') === owner && entry.isDeleted !== sqliteTrue);
    if (!row) throw new Error('expense_unavailable');
    source = evoluExpenseToApi(row);
  } else source = await invoicingApi.expenses.get<ExpenseApiRow>(company, sourceId);
  if (generation !== contextGeneration || company !== companyId.value) return;
  form.value.company_contact_id = source.company_contact_id || '';
  form.value.supplier_name = source.supplier_name || '';
  form.value.supplier_iban = source.supplier_iban || '';
  form.value.payment_review_required = expensePaymentReviewRequired(source.payment_review_required, source.internal_note);
  form.value.total = -Math.abs(Number(source.total) || 0);
  form.value.currency = source.currency || form.value.currency;
  form.value.external_number = '';
  form.value.variable_symbol = '';
  variableSymbolFollowsNumber.value = false;
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
  const total = Number(form.value.total);
  if (!Number.isFinite(total) || (isCreditNote.value ? total >= 0 : total < 0)) {
    error.value = t('invoicing.expense_credit_note_amount_invalid');
    return;
  }
  const iban = form.value.supplier_iban.trim() ? normalizePaymentIban(form.value.supplier_iban) : null;
  if (form.value.supplier_iban.trim() && !iban) {
    error.value = t('invoicing.expense_supplier_iban_invalid');
    return;
  }
  saving.value = true;
  try {
    const payload = {
      company_contact_id: form.value.company_contact_id || null,
      supplier_name: form.value.supplier_name.trim() || null,
      supplier_iban: iban,
      payment_review_required: form.value.payment_review_required,
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
  try { await prefillCreditNote(); } catch { error.value = t('invoicing.expense_unavailable'); }
});
</script>
