<template>
  <div class="invoicing-page">
    <div class="invoicing-sticky-chrome">
      <InvoicingAppHeader
        :company-label="company?.trade_name || company?.legal_name"
        :show-filter-bar="false"
      />
      <InvoicingDocumentSubNav />
    </div>
    <div
      v-if="localFirst && isRelaySyncing"
      class="invoicing-alert-warn mx-auto max-w-7xl px-4 py-3 mt-2"
    >
      <p class="text-sm font-medium">{{ t('invoicing.relay_sync_loading') }}</p>
      <p class="text-sm mt-1 opacity-90">{{ t('invoicing.relay_sync_documents_detail') }}</p>
    </div>
    <div class="invoicing-toolbar">
      <div class="md:hidden max-w-7xl mx-auto px-4 w-full py-2 invoicing-mobile-toolbar">
        <RouterLink :to="{ name: documentRoutes.list, params: { companyId } }" class="invoicing-mobile-icon-btn shrink-0">
          <InvoicingIcons name="chevron-left" />
        </RouterLink>
        <span class="invoicing-mobile-toolbar-title">
          {{ displayDocumentNumber || t('invoicing.draft_label') }}
        </span>
        <InvoicingRowActionsMenu>
          <RouterLink
            v-if="canUpdate"
            :to="{ name: documentRoutes.edit, params: { companyId, documentId } }"
            class="invoicing-dropdown-item block"
          >
            {{ t('invoicing.action_edit') }}
          </RouterLink>
          <button type="button" class="invoicing-dropdown-item" @click="downloadPdf">
            {{ t('invoicing.action_pdf') }}
          </button>
        </InvoicingRowActionsMenu>
      </div>
      <div class="hidden md:flex max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex-wrap items-center gap-4 py-3">
        <RouterLink :to="{ name: documentRoutes.list, params: { companyId } }" class="invoicing-back mb-0">
          ← {{ listTitle }}
        </RouterLink>
        <h1 class="text-lg font-semibold text-gray-900">
          {{ displayDocumentNumber || t('invoicing.draft_label') }}
        </h1>
        <div v-if="neighborIds.length > 1 && neighborIndex >= 0" class="flex items-center gap-1 text-sm text-gray-600">
          <button type="button" class="px-2 hover:text-indigo-700 disabled:opacity-30" :disabled="neighborIndex <= 0" @click="goNeighbor(-1, documentRoutes.show)">‹</button>
          <span>{{ neighborIndex + 1 }} / {{ neighborIds.length }}</span>
          <button type="button" class="px-2 hover:text-indigo-700 disabled:opacity-30" :disabled="neighborIndex >= neighborIds.length - 1" @click="goNeighbor(1, documentRoutes.show)">›</button>
        </div>
        <div class="flex flex-wrap items-center gap-3 ml-auto">
          <label class="text-xs text-gray-600 flex items-center gap-2">
            {{ t('invoicing.pdf_language') }}
            <select
              v-model="form.pdf_locale"
              class="invoicing-sf-input w-auto py-1"
              :disabled="isLocked"
              @change="persistPdfOptions"
            >
              <option value="sk">Slovenčina</option>
              <option value="cs">Čeština</option>
              <option value="de">Deutsch</option>
              <option value="en">English</option>
            </select>
          </label>
          <label class="text-xs text-gray-700 flex items-center gap-1.5">
            <input v-model="form.pdf_show_signature" type="checkbox" class="rounded border-gray-300" @change="persistPdfOptions" />
            {{ t('invoicing.pdf_signature') }}
          </label>
          <label v-if="!isQuote" class="text-xs text-gray-700 flex items-center gap-1.5">
            <input v-model="form.pdf_show_payment_info" type="checkbox" class="rounded border-gray-300" @change="persistPdfOptions" />
            {{ t('invoicing.pdf_payment_info') }}
          </label>
          <RouterLink
            v-if="canUpdate"
            :to="{ name: documentRoutes.edit, params: { companyId, documentId } }"
            class="invoicing-btn-primary py-1.5"
          >
            {{ t('invoicing.action_edit') }}
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-6 flex flex-col lg:flex-row gap-6">
      <div class="flex-1 min-w-0 space-y-6">
        <div
          v-if="sourceDocument?.number"
          class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900"
        >
          <template v-if="isCreditNote && sourceDocument.type === 'invoice'">
            {{ t('invoicing.linked_credited_invoice', { number: sourceDocument.number }) }}
            <RouterLink
              :to="sourceDocumentShowTo"
              class="ml-2 font-medium underline"
            >
              {{ t('invoicing.view_invoice') }}
            </RouterLink>
          </template>
          <template v-else-if="isProforma || sourceDocument.type === 'proforma'">
            {{ t('invoicing.linked_proforma', { number: sourceDocument.number }) }}
            <RouterLink
              :to="{ name: 'invoicing-proforma-show', params: { companyId, documentId: sourceDocument.id } }"
              class="ml-2 font-medium underline"
            >
              {{ t('invoicing.view_proforma') }}
            </RouterLink>
          </template>
        </div>

        <div
          v-if="finalInvoice?.id"
          class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
          {{ t('invoicing.linked_final_invoice', { number: finalInvoice.number || t('invoicing.draft_label') }) }}
          <RouterLink
            :to="{ name: 'invoicing-invoice-show', params: { companyId, documentId: finalInvoice.id } }"
            class="ml-2 font-medium underline"
          >
            {{ t('invoicing.view_invoice') }}
          </RouterLink>
        </div>

        <InvoiceLivePreview
          :company="company"
          :selected-contact="selectedContact"
          :form="previewForm"
          :totals="previewTotals"
          :document-status="documentStatus"
          :document-type="documentType"
          :amount-paid="amountPaid"
        />

        <div class="invoicing-card-pad space-y-4">
          <div v-if="pdfUrl">
            <label class="invoicing-sf-label">{{ t('invoicing.pdf_link') }}</label>
            <div class="flex gap-2 mt-1">
              <input :value="pdfUrl" readonly class="invoicing-sf-input flex-1 text-xs" />
              <button type="button" class="invoicing-btn-secondary text-sm" @click="copyPdfUrl">
                {{ t('common.copy_url') }}
              </button>
            </div>
          </div>
          <div v-if="localFirst && form.payment_btc_enabled && form.store_id && documentStatus !== 'draft' && documentStatus !== 'paid'">
            <label class="invoicing-sf-label">{{ t('invoicing.btc_pay_link') }}</label>
            <div v-if="localBtcpayCheckoutLoading" class="text-sm text-gray-500 mt-1">
              {{ t('common.loading') }}
            </div>
            <template v-else-if="btcPayUrl">
              <div class="flex gap-2 mt-1">
                <input :value="btcPayUrl" readonly class="invoicing-sf-input flex-1 text-xs" />
                <a :href="btcPayUrl" target="_blank" rel="noopener" class="invoicing-btn-secondary text-sm shrink-0">
                  {{ t('invoicing.btc_pay_open') }}
                </a>
              </div>
              <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.local_first_btcpay_checkout_hint') }}</p>
            </template>
            <div v-else class="mt-1 flex flex-wrap items-center gap-2">
              <p class="text-sm text-gray-500">{{ t('invoicing.local_first_btcpay_checkout_missing') }}</p>
              <button type="button" class="invoicing-btn-secondary text-sm" @click="createLocalBtcpayCheckout">
                {{ t('invoicing.local_first_btcpay_checkout_refresh') }}
              </button>
            </div>
          </div>
          <div v-else-if="btcPayUrl && documentStatus !== 'paid'">
            <label class="invoicing-sf-label">{{ t('invoicing.btc_pay_link') }}</label>
            <div class="flex gap-2 mt-1">
              <input :value="btcPayUrl" readonly class="invoicing-sf-input flex-1 text-xs" />
              <a :href="btcPayUrl" target="_blank" rel="noopener" class="invoicing-btn-secondary text-sm shrink-0">
                {{ t('invoicing.btc_pay_open') }}
              </a>
            </div>
            <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.payment_btc_lazy_hint') }}</p>
          </div>
          <div v-if="(tagsInput || '').length">
            <span class="invoicing-sf-label">{{ t('invoicing.tags') }}: </span>
            <span class="text-indigo-700 text-sm">{{ tagsInput }}</span>
          </div>
          <div v-if="form.internal_note">
            <h3 class="invoicing-sf-label">{{ t('invoicing.internal_note') }}</h3>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ form.internal_note }}</p>
          </div>
          <div>
            <h3 class="invoicing-sf-label mb-2">{{ t('invoicing.action_history') }}</h3>
            <ul v-if="history.length" class="space-y-2 text-sm text-gray-600 max-h-48 overflow-y-auto">
              <li v-for="h in history" :key="h.id" class="border-b border-gray-100 pb-2">
                <span class="text-gray-500">{{ formatDateTime(h.created_at) }}</span>
                · {{ historyLabel(h.action) }}
                <span v-if="h.user?.email" class="text-gray-400"> / {{ h.user.email }}</span>
              </li>
            </ul>
            <p v-else class="text-sm text-gray-500">{{ t('invoicing.no_history') }}</p>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="success" class="text-sm text-emerald-700">{{ success }}</p>
      </div>

      <SendDocumentEmailModal
        :open="sendEmailOpen"
        :company-id="companyId"
        :document-id="documentId || ''"
        :ephemeral-snapshot="localFirst ? ephemeralSnapshotForModal : null"
        :bridge-company-id="ephemeralBridgeCompanyId"
        @close="sendEmailOpen = false"
        @sent="onEmailSent"
      />

      <div class="w-full lg:w-56 shrink-0 space-y-2">
        <InvoiceEfakturaPanel
          v-if="showEfakturaPanel && documentId"
          :company-id="companyId"
          :document-id="documentId"
          :company="company"
          :selected-contact="selectedContact"
          :local-first="localFirst"
          :ephemeral-snapshot="localFirst ? ephemeralSnapshotForModal : null"
          :evolu-document-id="localFirst ? documentId : undefined"
          :bridge-company-id="ephemeralBridgeCompanyId"
          @sent="onEfakturaSent"
        />

        <InvoiceFormSidebar
          mode="show"
        :company-id="companyId"
        :document-id="documentId"
        :edit-route-name="documentRoutes.edit"
        :document-status="documentStatus"
        :is-locked="isLocked"
        :can-update="canUpdate"
        :can-delete="canDelete"
        :can-cancel="canCancel"
        :can-unmark-paid="canUnmarkPaid"
        :can-pdf="documentStatus !== 'draft'"
        :can-eu-export="supportsEuExport && documentStatus !== 'draft'"
        :can-send-email="documentStatus !== 'draft' && documentStatus !== 'cancelled'"
        :can-issue="documentStatus === 'draft' && !isRelaySyncing"
        :show-create-final-invoice="canCreateFinalInvoice"
        :show-approve-quote="canApproveQuote"
        :show-reject-quote="canRejectQuote"
        :show-create-invoice-from-quote="canCreateInvoiceFromQuote"
        :can-mark-paid="!isQuote && !isCreditNote && (documentStatus === 'issued' || documentStatus === 'draft')"
        :busy="saving || isRelaySyncing"
        :paid-at="paidAt"
        :amount-paid="amountPaid"
        :bank-match="bankMatch"
        :paid-via-btcpay="paidViaBtcpay"
        @issue="issueDocument"
        @send-email="openSendEmail"
        @pdf="downloadPdf"
        @isdoc="downloadIsdoc"
        @ubl="downloadUbl"
        @duplicate="duplicateCurrent"
        @delete="deleteDoc"
        @cancel="cancelDoc"
        @mark-paid="markPaid"
        @unmark-paid="unmarkPaid"
        @create-final-invoice="createFinalInvoice"
        @approve-quote="approveQuote"
        @reject-quote="rejectQuote"
        @create-invoice-from-quote="createInvoiceFromQuote"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import '../../styles/invoicing-theme.css';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingDocumentSubNav from '../../components/invoicing/InvoicingDocumentSubNav.vue';
import InvoicingRowActionsMenu from '../../components/invoicing/InvoicingRowActionsMenu.vue';
import InvoicingIcons from '../../components/invoicing/icons/InvoicingIcons.vue';
import InvoiceEfakturaPanel from '../../components/invoicing/InvoiceEfakturaPanel.vue';
import InvoiceFormSidebar from '../../components/invoicing/InvoiceFormSidebar.vue';
import SendDocumentEmailModal from '../../components/invoicing/SendDocumentEmailModal.vue';
import { useInvoicingRelaySync } from '../../composables/useInvoicingRelaySync';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import InvoiceLivePreview from '../../components/invoicing/InvoiceLivePreview.vue';
import { useEfakturaFeature } from '../../composables/useEfakturaFeature';
import { isCompanyEfakturaEligible } from '../../composables/useCompanyEfakturaSettings';
import { useInvoiceDocument } from '../../composables/useInvoiceDocument';
import { invoicingDocumentRoutesForType } from '../../composables/useInvoicingDocumentRoutes';
import {
  businessDocumentIsdocPath,
  businessDocumentPdfPath,
  businessDocumentUblPath,
  getWebBlob,
  invoicingApi,
} from '../../services/api';
import {
  downloadEphemeralIsdoc,
  downloadEphemeralPdf,
  downloadEphemeralUbl,
  resolveEphemeralBridgeCompanyId,
} from '../../evolu/ephemeralBridge';
import { markLocalDocumentEmailSent } from '../../evolu/documentCrud';
import type { DocumentId } from '../../evolu/schema';

const {
  t,
  locale,
  router,
  companyId,
  documentId,
  saving,
  error,
  success,
  documentStatus,
  displayDocumentNumber,
  documentNumber,
  paidAt,
  amountPaid,
  bankMatch,
  company,
  history,
  neighborIds,
  tagsInput,
  isLocked,
  canUpdate,
  canDelete,
  canCancel,
  canUnmarkPaid,
  form,
  selectedContact,
  neighborIndex,
  pdfUrl,
  btcPayUrl,
  localBtcpayCheckoutLoading,
  createLocalBtcpayCheckout,
  previewForm,
  previewTotals,
  extractError,
  loadCompanyAndContacts,
  reloadDocument,
  loadHistory,
  loadNeighbors,
  goNeighbor,
  payload,
  documentRoutes,
  documentType,
  isProforma,
  isQuote,
  isCreditNote,
  resolvedQuoteStatus,
  sourceDocument,
  finalInvoice,
  localFirst,
  local,
  persistLocalPdfOptions,
  persistLocalDraftBeforeIssue,
  buildCurrentEphemeralSnapshot,
  localTaxHelpers,
  payloadFromApiDocument,
} = useInvoiceDocument();

const { isRelaySyncing } = useInvoicingRelaySync({ refreshOnMount: true });

const paidViaBtcpay = computed(
  () =>
    documentStatus.value === 'paid'
    && !!form.payment_btc_enabled
    && !bankMatch.value
);

const listTitle = computed(() => {
  if (isProforma.value) return t('invoicing.proformas_title');
  if (isQuote.value) return t('invoicing.quotes_title');
  if (isCreditNote.value) return t('invoicing.credit_notes_title');
  return t('invoicing.invoices_title');
});

const sourceDocumentShowTo = computed(() => {
  if (!sourceDocument.value?.id) {
    return { name: documentRoutes.value.list, params: { companyId: companyId.value } };
  }
  const routes = invoicingDocumentRoutesForType(sourceDocument.value.type || 'invoice');
  return {
    name: routes.show,
    params: { companyId: companyId.value, documentId: sourceDocument.value.id },
  };
});

const canCreateFinalInvoice = computed(
  () =>
    isProforma.value
    && documentStatus.value === 'paid'
    && !finalInvoice.value?.id
);

const canApproveQuote = computed(
  () => isQuote.value && documentStatus.value === 'issued' && resolvedQuoteStatus.value === 'pending'
);

const canRejectQuote = computed(
  () => isQuote.value && documentStatus.value === 'issued' && resolvedQuoteStatus.value === 'pending'
);

const canCreateInvoiceFromQuote = computed(
  () =>
    isQuote.value
    && documentStatus.value === 'issued'
    && resolvedQuoteStatus.value === 'approved'
    && !finalInvoice.value?.id
);

const sendEmailOpen = ref(false);
const ephemeralBridgeCompanyId = ref<string | null>(null);

const ephemeralSnapshotForModal = computed(() => (localFirst ? buildCurrentEphemeralSnapshot() : null));

async function openSendEmail() {
  if (localFirst) {
    ephemeralBridgeCompanyId.value = await resolveEphemeralBridgeCompanyId(
      company.value
        ? {
            legal_name: company.value.legal_name,
            registration_number: company.value.registration_number ?? null,
          }
        : undefined,
    );
  }
  sendEmailOpen.value = true;
}
const { enabled: efakturaGloballyEnabled, load: loadEfakturaFeature } = useEfakturaFeature();

const supportsEuExport = computed(() => {
  const j = company.value?.jurisdiction;
  return j === 'eu_sk' || j === 'eu_cz' || j === 'eu_other';
});

const showEfakturaPanel = computed(() => {
  if (!isCompanyEfakturaEligible(company.value, efakturaGloballyEnabled.value)) {
    return false;
  }
  if (documentStatus.value !== 'issued') {
    return false;
  }

  return documentType.value === 'invoice' || documentType.value === 'credit_note';
});

async function onEfakturaSent() {
  await loadHistory();
}

async function onEmailSent(payload?: { email_sent_at?: string }) {
  if (localFirst && local && documentId.value) {
    markLocalDocumentEmailSent(
      local.evolu,
      documentId.value as DocumentId,
      payload?.email_sent_at,
    );
  }
  success.value = t('invoicing.send_email_success');
  await reloadDocument();
  await loadHistory();
}

async function persistPdfOptions() {
  if (!documentId.value || isLocked.value) return;
  if (localFirst) {
    await persistLocalPdfOptions();
    return;
  }
  try {
    await invoicingApi.documents.update(companyId.value, documentId.value!, payload());
  } catch {
    /* ignore */
  }
}

async function downloadPdf() {
  if (!documentId.value) return;
  if (localFirst) {
    saving.value = true;
    error.value = '';
    try {
      const bridgeCompanyId = await resolveEphemeralBridgeCompanyId();
      const snapshot = buildCurrentEphemeralSnapshot();
      await downloadEphemeralPdf(
        snapshot,
        `invoice-${documentNumber.value || documentId.value}.pdf`,
        bridgeCompanyId,
      );
    } catch (e: unknown) {
      error.value = extractError(e);
    } finally {
      saving.value = false;
    }
    return;
  }
  await downloadWebFile(
    businessDocumentPdfPath(companyId.value, documentId.value),
    `invoice-${documentNumber.value || documentId.value}.pdf`
  );
}

async function downloadIsdoc() {
  if (!documentId.value) return;
  if (localFirst) {
    saving.value = true;
    error.value = '';
    try {
      const bridgeCompanyId = await resolveEphemeralBridgeCompanyId();
      const snapshot = buildCurrentEphemeralSnapshot();
      await downloadEphemeralIsdoc(
        snapshot,
        `${documentNumber.value || documentId.value}.isdoc`,
        bridgeCompanyId,
      );
    } catch (e: unknown) {
      error.value = extractError(e);
    } finally {
      saving.value = false;
    }
    return;
  }
  await downloadWebFile(
    businessDocumentIsdocPath(companyId.value, documentId.value),
    `${documentNumber.value || documentId.value}.isdoc`
  );
}

async function downloadUbl() {
  if (!documentId.value) return;
  if (localFirst) {
    saving.value = true;
    error.value = '';
    try {
      const bridgeCompanyId = await resolveEphemeralBridgeCompanyId();
      const snapshot = buildCurrentEphemeralSnapshot();
      await downloadEphemeralUbl(
        snapshot,
        `${documentNumber.value || documentId.value}.xml`,
        bridgeCompanyId,
      );
    } catch (e: unknown) {
      error.value = extractError(e);
    } finally {
      saving.value = false;
    }
    return;
  }
  await downloadWebFile(
    businessDocumentUblPath(companyId.value, documentId.value),
    `${documentNumber.value || documentId.value}.xml`
  );
}

async function downloadWebFile(path: string, filename: string) {
  saving.value = true;
  try {
    const blob = await getWebBlob(path);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function duplicateCurrent() {
  if (!documentId.value) return;
  if (localFirst && local) {
    await local.refreshAll();
    const apiDoc = local.documentApi(documentId.value as import('../../evolu/schema').DocumentId);
    if (!apiDoc) return;
    const p = payloadFromApiDocument(apiDoc);
    p.title = p.title ? `${p.title} (copy)` : 'Copy';
    const result = local.saveLocalDocument(local.evolu, companyId.value as import('../../evolu/schema').CompanyId, p, {
      ...localTaxHelpers(),
      documentId: undefined,
    });
    if (!result.ok) return;
    const routes = invoicingDocumentRoutesForType(String(apiDoc.type || 'invoice'));
    router.push({
      name: routes.edit,
      params: { companyId: companyId.value, documentId: result.value.id },
    });
    return;
  }
  const dup = await invoicingApi.documents.action<{ id: string; type: string }>(companyId.value, documentId.value!, 'duplicate');
  const routes = invoicingDocumentRoutesForType(dup.type);
  router.push({
    name: routes.edit,
    params: { companyId: companyId.value, documentId: dup.id },
  });
}

async function issueDocument() {
  if (!documentId.value) return;
  if (localFirst && isRelaySyncing.value) {
    error.value = t('invoicing.relay_sync_wait_hint');
    return;
  }
  saving.value = true;
  error.value = '';
  try {
    if (localFirst && local) {
      await persistLocalDraftBeforeIssue();
      const companyRow = local.companyRows.value.find((c) => c.id === companyId.value);
      if (!companyRow) throw new Error('company');
      const issueResult = await local.issueLocalDocumentAsync(
        local.evolu,
        documentId.value as import('../../evolu/schema').DocumentId,
        companyRow as import('../../evolu/companyMap').EvoluCompanyRow,
      );
      if (!issueResult.ok) {
        throw new Error(typeof issueResult.error === 'string' ? issueResult.error : 'issue');
      }
      success.value = t('invoicing.issue_success');
      await reloadDocument();
      return;
    }
    await invoicingApi.documents.action(companyId.value, documentId.value!, 'issue');
    success.value = t('invoicing.issue_success');
    await reloadDocument();
    await loadHistory();
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function createFinalInvoice() {
  if (!documentId.value) return;
  saving.value = true;
  error.value = '';
  try {
    if (localFirst && local) {
      await local.refreshAll();
      const apiDoc = local.documentApi(documentId.value as import('../../evolu/schema').DocumentId);
      if (!apiDoc) throw new Error('not_found');
      const result = local.createLocalFinalInvoiceFromProforma(
        local.evolu,
        documentId.value as import('../../evolu/schema').DocumentId,
        local.documentRows.value as import('../../evolu/documentMap').EvoluDocumentRow[],
        local.lineRows.value as import('../../evolu/documentMap').EvoluDocumentLineRow[],
        (doc) => payloadFromApiDocument(doc),
        { ...localTaxHelpers(), documentId: undefined },
      );
      if (!result.ok) throw new Error('create');
      router.push({
        name: 'invoicing-invoice-edit',
        params: { companyId: companyId.value, documentId: result.value.id },
      });
      return;
    }
    const res = await invoicingApi.documents.action<{ id: string }>(companyId.value, documentId.value!, 'create-final-invoice');
    router.push({
      name: 'invoicing-invoice-edit',
      params: { companyId: companyId.value, documentId: res.id },
    });
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function approveQuote() {
  if (!documentId.value) return;
  saving.value = true;
  error.value = '';
  try {
    if (localFirst && local) {
      local.approveLocalQuote(local.evolu, documentId.value as import('../../evolu/schema').DocumentId);
      success.value = t('invoicing.quote_approved_success');
      await reloadDocument();
      return;
    }
    await invoicingApi.documents.action(companyId.value, documentId.value!, 'approve-quote');
    success.value = t('invoicing.quote_approved_success');
    await reloadDocument();
    await loadHistory();
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function rejectQuote() {
  if (!documentId.value) return;
  saving.value = true;
  error.value = '';
  try {
    if (localFirst && local) {
      local.rejectLocalQuote(local.evolu, documentId.value as import('../../evolu/schema').DocumentId);
      success.value = t('invoicing.quote_rejected_success');
      await reloadDocument();
      return;
    }
    await invoicingApi.documents.action(companyId.value, documentId.value!, 'reject-quote');
    success.value = t('invoicing.quote_rejected_success');
    await reloadDocument();
    await loadHistory();
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function createInvoiceFromQuote() {
  if (!documentId.value) return;
  saving.value = true;
  error.value = '';
  try {
    if (localFirst && local) {
      await local.refreshAll();
      const apiDoc = local.documentApi(documentId.value as import('../../evolu/schema').DocumentId);
      if (!apiDoc) throw new Error('not_found');
      const result = local.createLocalInvoiceFromQuote(
        local.evolu,
        documentId.value as import('../../evolu/schema').DocumentId,
        local.documentRows.value as import('../../evolu/documentMap').EvoluDocumentRow[],
        local.lineRows.value as import('../../evolu/documentMap').EvoluDocumentLineRow[],
        (doc) => payloadFromApiDocument(doc),
        { ...localTaxHelpers(), documentId: undefined },
      );
      if (!result.ok) throw new Error('create');
      router.push({
        name: 'invoicing-invoice-edit',
        params: { companyId: companyId.value, documentId: result.value.id },
      });
      return;
    }
    const res = await invoicingApi.documents.action<{ id: string }>(companyId.value, documentId.value!, 'create-invoice-from-quote');
    router.push({
      name: 'invoicing-invoice-edit',
      params: { companyId: companyId.value, documentId: res.id },
    });
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function deleteDoc() {
  if (!documentId.value || !canDelete.value) return;
  const msg =
    documentStatus.value === 'paid' || documentStatus.value === 'issued'
      ? t('invoicing.confirm_delete_last')
      : t('invoicing.confirm_delete');
  if (!window.confirm(msg)) return;
  if (localFirst && local) {
    const result = await local.deleteLocalDocumentAsync(
      local.evolu,
      documentId.value as import('../../evolu/schema').DocumentId,
      local.documentRows.value as import('../../evolu/documentMap').EvoluDocumentRow[],
      local.seriesRows.value as import('../../evolu/numberSeriesMap').EvoluNumberSeriesRow[],
    );
    if (!result.ok) {
      // Gapless numbering: the number must be freed on the server first.
      error.value =
        result.error === 'delete_requires_online'
          ? t('invoicing.delete_requires_online')
          : result.error === 'not_last'
            ? t('invoicing.delete_not_last')
            : t('invoicing.delete_release_failed');
      return;
    }
    router.push({ name: documentRoutes.value.list, params: { companyId: companyId.value } });
    return;
  }
  await invoicingApi.documents.delete(companyId.value, documentId.value!);
  router.push({ name: documentRoutes.value.list, params: { companyId: companyId.value } });
}

async function cancelDoc() {
  if (!documentId.value || !window.confirm(t('invoicing.confirm_cancel'))) return;
  if (localFirst && local) {
    await local.cancelLocalDocumentAsync(
      local.evolu,
      documentId.value as import('../../evolu/schema').DocumentId,
      local.documentRows.value as import('../../evolu/documentMap').EvoluDocumentRow[],
    );
    await reloadDocument();
    return;
  }
  await invoicingApi.documents.action(companyId.value, documentId.value!, 'cancel');
  await reloadDocument();
  await loadHistory();
}

async function markPaid() {
  if (!documentId.value) return;
  saving.value = true;
  try {
    if (localFirst && local) {
      await local.refreshAll();
      local.markLocalDocumentPaid(
        local.evolu,
        documentId.value as import('../../evolu/schema').DocumentId,
        local.documentRows.value as import('../../evolu/documentMap').EvoluDocumentRow[],
      );
      await reloadDocument();
      return;
    }
    await invoicingApi.documents.action(companyId.value, documentId.value!, 'mark-paid');
    await reloadDocument();
    await loadHistory();
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function unmarkPaid() {
  if (!documentId.value) return;
  if (!window.confirm(t('invoicing.confirm_unmark_paid'))) return;
  saving.value = true;
  try {
    if (localFirst && local) {
      local.unmarkLocalDocumentPaid(
        local.evolu,
        documentId.value as import('../../evolu/schema').DocumentId,
      );
      await reloadDocument();
      return;
    }
    await invoicingApi.documents.action(companyId.value, documentId.value!, 'unmark-paid');
    await reloadDocument();
    await loadHistory();
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

async function copyPdfUrl() {
  try {
    await navigator.clipboard.writeText(pdfUrl.value);
    success.value = t('common.copied');
  } catch {
    error.value = t('common.copy_failed');
  }
}

function historyLabel(action: string) {
  const key = `invoicing.history_${action.replace(/\./g, '_')}`;
  const translated = t(key);
  return translated !== key ? translated : action;
}

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString(locale.value);
}

watch(documentId, async () => {
  if (documentId.value) {
    await reloadDocument();
    await loadHistory();
  }
});

const { rememberCompany } = useInvoicingLayout();

onMounted(async () => {
  await loadEfakturaFeature();
  rememberCompany(companyId.value);
  await loadCompanyAndContacts();
  await loadNeighbors();
  await reloadDocument();
  await loadHistory();
});
</script>
