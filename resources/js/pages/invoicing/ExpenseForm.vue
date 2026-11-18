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

    <ExpenseAttachmentConsentModal :open="showProcessingConsent" :file-name="processingConsentFileName"
      :busy="detecting || extracting" @confirm="confirmProcessingConsent" @skip="skipProcessingConsent" />

    <ExpenseIsdocExtractModal
      :open="showExtractModal"
      :source="extractionSource"
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
      <button type="button" class="invoicing-btn-primary" :disabled="!formReady || saving || hasUnresolvedProcessing" @click="save">
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
      v-if="formReady"
      class="mb-4"
      :company-id="companyId"
      :draft="form"
      :exclude-id="expenseId || createdLocalExpenseId || undefined"
      :local-first="localFirst"
      :local-rows="expenseRows"
      :evolu="expenseResource.evolu"
    />

    <p v-if="!formReady" role="status" class="text-sm text-gray-600">{{ error || t('common.loading') }}</p>
    <div v-else class="grid lg:grid-cols-[minmax(0,1fr)_minmax(320px,42%)] gap-6 items-start">
      <form class="invoicing-card-pad space-y-4 min-w-0" @input="markFormEdited" @change="markFormEdited" @submit.prevent="save">
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
        <div v-if="form.payment_review_required" class="rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-2">
          <p class="text-xs text-amber-900" role="status">{{ t('invoicing.expense_payment_review_required') }}</p>
          <label v-if="canConfirmPaymentDetails" class="flex items-start gap-2 text-sm text-gray-800">
            <input id="expense-confirm-payment-details" type="checkbox" class="mt-1 rounded border-gray-300"
              :checked="paymentDetailsConfirmed" @change="setPaymentDetailsConfirmed" />
            <span>{{ t('invoicing.expense_payment_review_confirm') }}</span>
          </label>
          <p class="text-xs text-amber-900">{{ t(canConfirmPaymentDetails ? 'invoicing.expense_payment_review_after_save' : 'invoicing.expense_payment_review_blocked') }}</p>
        </div>
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
            <label for="expense-currency" class="invoicing-sf-label">{{ t('invoicing.expense_import_field_currency') }}</label>
            <ExpenseCurrencySelect id="expense-currency" v-model="form.currency" :previous-invoice-currency="previousInvoiceCurrency"
              @change="allowCurrencyDefault = false" />
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
        <button type="submit" class="invoicing-btn-primary" :disabled="!formReady || saving || hasUnresolvedProcessing">{{ t('common.save') }}</button>
      </form>

      <div class="min-w-0 h-full flex flex-col">
        <ExpenseAttachmentPanel
          allow-multiple
          :file-name="panelFileName"
          :has-file="panelHasFile"
          :allow-remove-saved="false"
          :saved-attachments="savedAttachments"
          :selected-attachment-id="pendingAttachmentName ? null : selectedAttachmentId"
          :has-pending="pendingFiles.length > 0"
          :pending-files="pendingFileSummaries"
          :selected-pending-id="selectedPendingId"
          :preview-url="pendingAttachmentName ? previewUrl : savedPreviewUrl"
          :preview-loading="!pendingAttachmentName && previewLoading"
          :preview-failed="!pendingAttachmentName && previewFailed"
          :preview-kind="pendingAttachmentName ? previewKind : savedPreviewKind"
          :detecting="detecting || saving || extracting"
          :detect-error="detectErrorMessage"
          :has-isdoc="lastDetectHasIsdoc"
          :detection-status="detectionStatus"
          @check-isdoc="retryDetection"
          @file-selected="onFileSelected"
          @files-selected="onFilesSelected"
          @select-pending="selectPendingFile"
          @remove-pending="removePendingFile"
          @select-attachment="onSelectAttachment"
        />
        <template v-if="!pendingAttachmentName && (embeddedUblXml || embeddedUblTooLarge)">
          <UblEmbeddedPdfPanel :xml="embeddedUblXml" />
          <p v-if="embeddedUblTooLarge" role="alert" class="p-3 text-sm text-amber-700">{{ t('invoicing.ubl_pdf_rejected') }}</p>
        </template>
      </div>
      <p v-if="localFirst" class="text-xs text-gray-500">
        {{ t('invoicing.local_first_expenses_isdoc_bridge') }}
      </p>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import ExpenseCurrencySelect from "../../components/invoicing/ExpenseCurrencySelect.vue";
import ExpenseDuplicateWarning from "../../components/invoicing/ExpenseDuplicateWarning.vue";
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { sqliteTrue } from '@evolu/common';
import { createQuery } from '@evolu/common/local-first';
import { useExpenseAttachmentPreview } from '../../composables/useExpenseAttachmentPreview';
import UblEmbeddedPdfPanel from '../../components/invoicing/UblEmbeddedPdfPanel.vue';
import ExpenseAttachmentPanel from '../../components/invoicing/ExpenseAttachmentPanel.vue';
import ExpenseAttachmentConsentModal from '../../components/invoicing/ExpenseAttachmentConsentModal.vue';
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
import { expensePaymentConfirmationTuple, expensePaymentReviewRequired, hasExpensePaymentEvidence } from '../../utils/expensePaymentReview';
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
  extractionSource,
  showProcessingConsent,
  processingConsentFileName,
  detectionStatus,
  hasUnresolvedProcessing,
  confirmProcessingConsent,
  skipProcessingConsent,
  retryDetection,
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
const savedExpense = ref<ExpenseApiRow | null>(null);
const formReady = computed(() => isNew.value || (!!savedExpense.value && savedExpense.value.id === expenseId.value
  && (!localFirst || expenseResource.expense.value?.id === expenseId.value)));
const { selectedAttachmentId, savedAttachments, savedPreviewUrl, savedPreviewKind, embeddedUblXml, embeddedUblTooLarge,
  previewFailed, previewLoading, panelFileName, panelHasFile, revokeSavedPreview, loadSavedPreview, onSelectAttachment,
} = useExpenseAttachmentPreview({ companyId, expenseId, expense: savedExpense, pendingAttachmentName, resource: expenseResource, localFirst });

watch(() => Boolean(pendingAttachmentName.value), pending => {
  if (pending) revokeSavedPreview();
  else if (savedExpense.value) void loadSavedPreview();
}, { flush: 'sync' });

const detectErrorMessage = computed(() => {
  if (!detectError.value) return '';
  if (detectError.value === 'detect_failed') {
    return t('invoicing.expense_attachment_detect_failed');
  }
  return detectError.value;
});

const today = new Date().toISOString().slice(0, 10);

const emptyForm = {
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
};
const form = ref({ ...emptyForm });
const documentKind = ref(route.query.credit_note === '1' ? 'credit_note' : 'expense');
const isCreditNote = computed(() => documentKind.value === 'credit_note');
const selectedContact = computed(() => supplierContacts.value.find(contact => contact.id === form.value.company_contact_id));
let extractedSupplier: { name: string; iban: string } | null = null;
let automaticSupplier: { name: string; iban: string } | null = null;
const supplierNameManuallyEdited = ref(false);
const supplierIbanManuallyEdited = ref(false);
const allowCurrencyDefault = ref(true);
const previousInvoiceCurrency = ref<string | null>(null);
const confirmedPaymentTuple = ref<string | null>(null);
const originalPaymentState = ref({ status: isNew.value ? 'recorded' : '', paidAt: null as string | null,
  note: null as string | null, bankLinked: false });
const paymentTuple = computed(() => expensePaymentConfirmationTuple(form.value));
const canConfirmPaymentDetails = computed(() => Boolean(form.value.payment_review_required && paymentTuple.value
  && originalPaymentState.value.status === 'recorded' && !originalPaymentState.value.paidAt
  && !originalPaymentState.value.bankLinked && !form.value.mark_paid
  && !hasExpensePaymentEvidence(originalPaymentState.value.note) && !hasExpensePaymentEvidence(form.value.internal_note)));
const paymentDetailsConfirmed = computed(() => canConfirmPaymentDetails.value
  && confirmedPaymentTuple.value !== null && confirmedPaymentTuple.value === paymentTuple.value);
function setPaymentDetailsConfirmed(event: Event) {
  confirmedPaymentTuple.value = (event.target as HTMLInputElement).checked && canConfirmPaymentDetails.value ? paymentTuple.value : null;
}
function rememberPaymentState(expense: ExpenseApiRow) {
  originalPaymentState.value = { status: expense.status || '', paidAt: expense.paid_at || null,
    note: expense.internal_note || null, bankLinked: Boolean(expense.bank_match_claim || expense.bank_match_conflict) };
  confirmedPaymentTuple.value = null;
}
watch([paymentTuple, () => form.value.mark_paid], () => { confirmedPaymentTuple.value = null; }, { flush: 'sync' });
watch(hasUnresolvedProcessing, pending => { if (pending) confirmedPaymentTuple.value = null; }, { flush: 'sync' });
watch(() => localFirst ? companyShareRoutingKey(companyId.value) : 'server', () => {
  confirmedPaymentTuple.value = null;
  if (savedExpense.value) void loadSavedPreview();
}, { flush: 'sync' });

function onDocumentKindChange() {
  form.value.total = Math.abs(Number(form.value.total) || 0) * (isCreditNote.value ? -1 : 1);
}

/** Only deliberate selection replaces the stored supplier and payment snapshots. */
function onSupplierContactChange() {
  const contact = selectedContact.value;
  const sameSupplier = (name: string) => extractedSupplier?.name.trim().toLocaleLowerCase() === name.trim().toLocaleLowerCase();
  if (contact && extractedSupplier?.name && !sameSupplier(contact.name)) form.value.payment_review_required = true;
  form.value.supplier_name = contact?.name ?? extractedSupplier?.name ?? '';
  form.value.supplier_iban = contact?.iban?.trim() || (extractedSupplier && (!contact || sameSupplier(contact.name)) ? extractedSupplier.iban : '');
  supplierNameManuallyEdited.value = false;
  supplierIbanManuallyEdited.value = false;
  automaticSupplier = contact ? null : { name: form.value.supplier_name, iban: form.value.supplier_iban };
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
let formEditRevision = 0;
let sourceReadController: AbortController | null = null;
function markFormEdited() {
  formEditRevision++;
}
watch([companyId, expenseId, isNew], (_current, [, previousId]) => {
  contextGeneration++;
  savedExpense.value = null;
  internalNumber.value = '';
  expenseLabel.value = '';
  importNotice.value = '';
  if (previousId && isNew.value) {
    form.value = { ...emptyForm, currency: defaultCurrency.value || 'EUR' };
    documentKind.value = route.query.credit_note === '1' ? 'credit_note' : 'expense';
    variableSymbolFollowsNumber.value = true;
    deliveryDateFollowsIssueDate.value = true;
  }
  selectedAttachmentId.value = null;
  revokeSavedPreview();
  createdLocalExpenseId.value = null;
  saving.value = false;
  error.value = '';
  extractedSupplier = null;
  automaticSupplier = null;
  form.value.company_contact_id = '';
  form.value.supplier_name = '';
  form.value.supplier_iban = '';
  form.value.payment_review_required = false;
  confirmedPaymentTuple.value = null;
  originalPaymentState.value = { status: isNew.value ? 'recorded' : '', paidAt: null, note: null, bankLinked: false };
  supplierNameManuallyEdited.value = false;
  supplierIbanManuallyEdited.value = false;
  allowCurrencyDefault.value = true;
  previousInvoiceCurrency.value = null;
  sourceReadController?.abort();
  if (!isNew.value && expenseId.value) void loadExpense();
}, { flush: 'sync' });
onUnmounted(() => {
  contextGeneration++;
  revokeSavedPreview();
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
  confirmedPaymentTuple.value = null;
  if (hasExpensePaymentEvidence(draft.internal_note)) {
    originalPaymentState.value.note = [originalPaymentState.value.note, draft.internal_note].filter(Boolean).join('\n');
  }
  markFormEdited();
  // Supplier identity and account come from structured fields, never copied prose.
  extractedSupplier = { name: draft.supplier_name?.trim() ?? '', iban: draft.supplier_iban?.trim() ?? '' };
  form.value.payment_review_required ||= draft.source === 'pdf_text' || draft.payment_review_required !== false
    || (draft.variable_symbol == null && Boolean(form.value.variable_symbol.trim()));
  const contact = selectedContact.value;
  const sameName = (left: string, right: string) => left.trim().toLocaleLowerCase() === right.trim().toLocaleLowerCase();
  const sameSupplier = (name: string) => sameName(extractedSupplier!.name, name);
  const partialTextDraft = draft.source === 'pdf_text';
  const previousSupplierName = form.value.supplier_name;
  // A later partial draft may replace automatic fields, but cannot mix two inferred recipients.
  const canReplaceAutomaticSupplier = !contact && !supplierNameManuallyEdited.value && !supplierIbanManuallyEdited.value
    && (automaticSupplier ? sameName(form.value.supplier_name, automaticSupplier.name) && form.value.supplier_iban === automaticSupplier.iban
      : !form.value.supplier_name.trim() && !form.value.supplier_iban.trim());
  const retainedSupplierName = supplierNameManuallyEdited.value || supplierIbanManuallyEdited.value
    ? form.value.supplier_name : contact?.name ?? (partialTextDraft
      ? canReplaceAutomaticSupplier && extractedSupplier.name ? extractedSupplier.name : form.value.supplier_name
      : extractedSupplier.name);
  const supplierConflict = Boolean(retainedSupplierName && extractedSupplier.name && !sameSupplier(retainedSupplierName));
  if (supplierConflict) form.value.payment_review_required = true;
  if (!supplierNameManuallyEdited.value) form.value.supplier_name = retainedSupplierName;
  if (!supplierIbanManuallyEdited.value) {
    if (!partialTextDraft) form.value.supplier_iban = contact?.iban?.trim() || (supplierConflict ? '' : extractedSupplier.iban);
    else if (contact?.iban?.trim()) form.value.supplier_iban = contact.iban.trim();
    else if (extractedSupplier.iban && (!retainedSupplierName || sameSupplier(retainedSupplierName))) {
      form.value.supplier_iban = extractedSupplier.iban;
    } else if (canReplaceAutomaticSupplier && (!sameName(previousSupplierName, retainedSupplierName)
      || (extractedSupplier.iban && !extractedSupplier.name))) form.value.supplier_iban = '';
  }
  if (!contact && !supplierNameManuallyEdited.value && !supplierIbanManuallyEdited.value
    && (!partialTextDraft || canReplaceAutomaticSupplier)) {
    automaticSupplier = { name: form.value.supplier_name, iban: form.value.supplier_iban };
  }
  if (draft.internal_note?.trim() && !form.value.internal_note.includes(draft.internal_note.trim())) {
    form.value.internal_note = [form.value.internal_note.trim(), draft.internal_note.trim()].filter(Boolean).join('\n\n');
  }
  form.value.variable_symbol = draft.variable_symbol ?? form.value.variable_symbol;
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
  if (partialTextDraft && draft.total != null && !draft.currency?.trim()) {
    previousInvoiceCurrency.value = form.value.currency || previousInvoiceCurrency.value || '';
    form.value.currency = '';
    allowCurrencyDefault.value = false;
  } else if (draft.currency && (previousInvoiceCurrency.value === null || draft.total != null)) {
    previousInvoiceCurrency.value = null;
    form.value.currency = draft.currency;
    allowCurrencyDefault.value = false;
  }
  importNotice.value = t(partialTextDraft ? 'invoicing.expense_pdf_text_prefilled' : 'invoicing.expense_import_prefilled');
}

async function onFileSelected(file: File) {
  if (!formReady.value || saving.value || extracting.value || detecting.value) return;
  error.value = '';
  await onDocumentSelected(file);
}

async function onFilesSelected(files: File[]) {
  if (!formReady.value || saving.value || extracting.value || detecting.value) return;
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
  const company = companyId.value;
  const id = expenseId.value;
  if (isNew.value || !id) return;
  const generation = contextGeneration;
  const current = () => generation === contextGeneration && company === companyId.value && id === expenseId.value;
  let e: ExpenseApiRow | null;
  try {
    if (localFirst) {
      await expenseResource.refresh();
      e = expenseResource.expense.value;
    } else e = await invoicingApi.expenses.get<ExpenseApiRow>(company, id);
    if (!current()) return;
    if (!e || e.id !== id) throw new Error('expense_unavailable');
  } catch {
    if (current()) error.value = t('invoicing.expense_unavailable');
    return;
  }
  rememberPaymentState(e);
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
  savedExpense.value = e;
  selectedAttachmentId.value = savedAttachments.value[0]?.id ?? null;
  void loadSavedPreview();
}

/** Prefill a new received correction without claiming a persisted document relationship. */
async function prefillCreditNote(expectedEditRevision: number) {
  const sourceId = route.query.source_expense;
  if (!isNew.value || !isCreditNote.value || typeof sourceId !== 'string' || expectedEditRevision !== formEditRevision) return;
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
  if (generation !== contextGeneration || company !== companyId.value || expectedEditRevision !== formEditRevision) return;
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
  if (!formReady.value || saving.value || extracting.value || detecting.value) return;
  if (hasUnresolvedProcessing.value) { error.value = t('invoicing.expense_attachment_processing_pending'); return; }
  markFormEdited();
  const requestedCompany = companyId.value;
  const requestedExpense = expenseId.value;
  const requestedNew = isNew.value;
  const requestedDuplicate = duplicateEditActive.value;
  const capturedGeneration = contextGeneration;
  const stillCurrent = () => capturedGeneration === contextGeneration && requestedCompany === companyId.value && requestedExpense === expenseId.value && requestedNew === isNew.value
    && (!requestedDuplicate || duplicateEditActive.value);
  error.value = '';
  if (!form.value.currency) {
    error.value = t('invoicing.expense_select_invoice_currency');
    return;
  }
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
    const editedNoteLines = form.value.internal_note.split(/\r?\n/);
    const protectedPaymentLines = [...new Set((originalPaymentState.value.note ?? '').split(/\r?\n/)
      .filter(line => hasExpensePaymentEvidence(line) && !editedNoteLines.includes(line)))];
    const payload = {
      ...(paymentDetailsConfirmed.value ? { payment_details_confirmation: confirmedPaymentTuple.value } : {}),
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
      internal_note: [form.value.internal_note, ...protectedPaymentLines].filter(Boolean).join('\n') || null,
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
    const err = e as { response?: { data?: { message?: string; errors?: { payment_details_confirmation?: string[] } } } };
    if ((e instanceof Error && e.message === 'expense_payment_details_confirmation') || err?.response?.data?.errors?.payment_details_confirmation) {
      confirmedPaymentTuple.value = null;
      error.value = t('invoicing.expense_payment_review_changed');
      return;
    }
    const mutationErrorKey = expenseMutationErrorKey(e);
    error.value = mutationErrorKey ? t(mutationErrorKey) : e instanceof Error && e.message === 'expense_unavailable'
      ? t('invoicing.expense_unavailable')
      : err?.response?.data?.message || t('errors.generic');
  } finally {
    if (stillCurrent()) saving.value = false;
  }
}

onMounted(async () => {
  const initialEditRevision = formEditRevision;
  const initialCompany = companyId.value;
  const initialGeneration = contextGeneration;
  const stillInitialContext = () => initialGeneration === contextGeneration && initialCompany === companyId.value;
  rememberCompany(initialCompany);
  if (!isNew.value) {
    if (!localFirst) void loadQuota().catch(() => { /* Extraction can reload quota when needed. */ });
    await loadExpense();
    return;
  }
  if (!localFirst) {
    await loadQuota();
    if (!stillInitialContext()) return;
    const companyRec = await invoicingApi.companies.get<{ default_currency?: string }>(initialCompany);
    if (!stillInitialContext()) return;
    if (allowCurrencyDefault.value) form.value.currency = companyRec?.default_currency || 'EUR';
  } else {
    form.value.currency = defaultCurrency.value || 'EUR';
  }
  if (!stillInitialContext()) return;
  try { await prefillCreditNote(initialEditRevision); }
  catch { if (stillInitialContext() && initialEditRevision === formEditRevision) error.value = t('invoicing.expense_unavailable'); }
});
</script>
