<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader
        :company-label="company?.trade_name || company?.legal_name"
        :show-filter-bar="false"
      />
    </template>
    <template #toolbar>
      <RouterLink :to="backTo" class="invoicing-back mb-0">← {{ backLabel }}</RouterLink>
    </template>

    <h1 class="invoicing-title mb-4">
      {{ isNew ? newTitle : editTitle }}
    </h1>

    <div
      v-if="isCreditNote && sourceDocument?.number"
      class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 mb-4"
    >
      {{ t('invoicing.linked_credited_invoice', { number: sourceDocument.number }) }}
    </div>

    <div v-if="isLocked" class="invoicing-alert-warn mb-4">{{ t('invoicing.readonly_locked_hint') }}</div>
    <div v-else-if="isIssued" class="invoicing-alert-info mb-4">{{ t('invoicing.issued_edit_hint') }}</div>

    <form class="invoicing-card-pad space-y-6" @submit.prevent="save(false)">
        <section>
          <h2 class="text-xs font-bold uppercase tracking-wide text-gray-600 mb-4">
            {{ t('invoicing.section_general') }}
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-3">
              <div>
                <label class="invoicing-sf-label">
                  {{ t('invoicing.invoice_number') }}
                  <span class="text-red-500">*</span>
                </label>
                <input
                  :value="displayDocumentNumber || t('invoicing.draft_label')"
                  type="text"
                  class="invoicing-sf-input bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <label class="invoicing-sf-label">{{ t('invoicing.variable_symbol') }}</label>
                <input v-model="form.variable_symbol" type="text" class="invoicing-sf-input" maxlength="20" :disabled="isLocked" />
              </div>
              <div>
                <label class="invoicing-sf-label">{{ t('invoicing.invoice_title') }}</label>
                <input v-model="form.title" type="text" class="invoicing-sf-input" :disabled="isLocked" />
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <label class="invoicing-sf-label">
                  {{ t('invoicing.issue_date') }}
                  <span class="text-red-500">*</span>
                </label>
                <input v-model="form.issue_date" type="date" class="invoicing-sf-input" required :disabled="isLocked" />
              </div>
              <div>
                <label class="invoicing-sf-label">{{ t('invoicing.delivery_date') }}</label>
                <input v-model="form.delivery_date" type="date" class="invoicing-sf-input" :disabled="isLocked" />
              </div>
              <div>
                <label class="invoicing-sf-label">
                  {{ isQuote ? t('invoicing.valid_until') : t('invoicing.due_date') }}
                  <span class="text-red-500">*</span>
                </label>
                <input v-model="form.due_date" type="date" class="invoicing-sf-input" required :disabled="isLocked" />
              </div>
            </div>

            <div class="space-y-2">
              <div>
                <label class="invoicing-sf-label">
                  {{ t('invoicing.customer') }}
                  <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.company_contact_id"
                  class="invoicing-sf-input"
                  required
                  :disabled="isLocked"
                  @change="onContactChange"
                >
                  <option value="">{{ t('invoicing.select_customer') }}</option>
                  <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <p v-if="selectedContact && formatContactAddress(selectedContact)" class="text-xs text-gray-600 leading-relaxed">
                {{ formatContactAddress(selectedContact) }}
              </p>
              <div class="flex flex-wrap gap-3 text-sm">
                <button
                  v-if="!isLocked"
                  type="button"
                  class="text-indigo-600 hover:underline"
                  @click="showContactCreateModal = true"
                >
                  + {{ t('invoicing.create_client') }}
                </button>
                <RouterLink
                  v-if="selectedContact"
                  :to="contactShowTo(selectedContact.id)"
                  class="text-indigo-600 hover:underline"
                >
                  {{ t('invoicing.edit_client') }}
                </RouterLink>
              </div>
            </div>
          </div>

          <details class="mt-4 text-sm">
            <summary class="text-indigo-600 cursor-pointer hover:underline">{{ t('invoicing.more_options') }}</summary>
            <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
              <div>
                <label class="invoicing-sf-label">{{ t('invoicing.currency') }}</label>
                <select v-model="form.currency" class="invoicing-sf-input" :disabled="isLocked">
                  <option v-for="code in documentCurrencyOptions" :key="code" :value="code">{{ code }}</option>
                </select>
              </div>
              <div>
                <label class="invoicing-sf-label">{{ t('invoicing.constant_symbol') }}</label>
                <input v-model="form.constant_symbol" type="text" class="invoicing-sf-input" :disabled="isLocked" />
              </div>
              <div>
                <label class="invoicing-sf-label">{{ t('invoicing.specific_symbol') }}</label>
                <input v-model="form.specific_symbol" type="text" class="invoicing-sf-input" :disabled="isLocked" />
              </div>
              <template v-if="!isQuote && !isCreditNote">
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.btcpay_store') }}</label>
                  <select
                    v-model="form.store_id"
                    class="invoicing-sf-input"
                    :disabled="isLocked || linkedStores.length === 0"
                    :required="linkedStores.length > 0 && form.payment_btc_enabled"
                  >
                    <option v-if="linkedStores.length === 0" value="">{{ t('invoicing.no_store') }}</option>
                    <option v-for="s in linkedStores" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 pt-6">
                  <input v-model="form.payment_bank_enabled" type="checkbox" :disabled="isLocked" />
                  {{ t('invoicing.payment_bank') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 pt-6">
                  <input v-model="form.payment_btc_enabled" type="checkbox" :disabled="isLocked" />
                  {{ t('invoicing.payment_btc') }}
                </label>
                <p v-if="form.payment_btc_enabled" class="text-xs text-gray-500 sm:col-span-2 lg:col-span-4">
                  {{ t('invoicing.payment_btc_lazy_hint') }}
                </p>
              </template>
            </div>
          </details>

          <p class="mt-3">
            <RouterLink
              :to="{ name: 'invoicing-company', params: { companyId } }"
              class="text-sm text-indigo-600 hover:underline"
            >
              {{ t('invoicing.edit_company_on_invoice') }}
            </RouterLink>
          </p>
        </section>

        <section>
                <label class="invoicing-sf-label">{{ t('invoicing.note_above') }}</label>
          <textarea v-model="form.note_above_lines" rows="3" class="invoicing-sf-input" :disabled="isLocked" />
        </section>

        <section>
          <div class="overflow-x-auto rounded border border-gray-200">
            <table class="w-full text-sm">
              <thead class="bg-gray-100 text-gray-600 text-xs uppercase">
                <tr>
                  <th class="text-left px-3 py-2 min-w-[200px]">{{ t('invoicing.col_item') }}</th>
                  <th class="text-center px-2 py-2 w-20">{{ t('invoicing.col_qty') }}</th>
                  <th class="text-center px-2 py-2 w-24">{{ t('invoicing.col_unit') }}</th>
                  <th class="text-right px-2 py-2 w-28">{{ t('invoicing.col_unit_price') }}</th>
                  <th v-if="showLineTaxColumn" class="text-right px-2 py-2 w-16">{{ lineTaxColumnLabel }}%</th>
                  <th class="text-right px-2 py-2 w-28">{{ t('invoicing.col_line_total_vat') }}</th>
                  <th class="w-8"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in form.lines" :key="idx" class="border-t border-gray-200">
                  <td class="px-3 py-2 align-top">
                    <StockLineSuggestField
                      v-if="showLineSuggester && !isLocked"
                      :company-id="companyId"
                      :name="line.name"
                      :description="line.description"
                      :stock-item-id="line.company_stock_item_id"
                      :quantity-on-hand="line.stock_quantity_hint"
                      :unit="line.unit"
                      :enabled="showLineSuggester"
                      :disabled="isLocked"
                      :required="!isLocked"
                      :description-placeholder="t('invoicing.item_description')"
                      @update:name="line.name = $event"
                      @update:description="line.description = $event"
                      @pick="onLineStockPick(line, $event)"
                      @clear-stock-link="clearLineStockLink(line)"
                    />
                    <template v-else>
                      <input v-model="line.name" class="invoicing-sf-input-table" :required="!isLocked" :disabled="isLocked" />
                      <input
                        v-model="line.description"
                        class="invoicing-sf-input-table mt-1 text-gray-500"
                        :placeholder="t('invoicing.item_description')"
                        :disabled="isLocked"
                      />
                    </template>
                  </td>
                  <td class="px-2 py-2 align-middle">
                    <input v-model.number="line.quantity" type="number" min="0.0001" step="any" class="invoicing-sf-input-table text-center" :disabled="isLocked" />
                  </td>
                  <td class="px-2 py-2 align-middle">
                    <InvoiceLineUnitSelect v-model="line.unit" :disabled="isLocked" />
                  </td>
                  <td class="px-2 py-2 align-middle">
                    <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="invoicing-sf-input-table text-right" :disabled="isLocked" />
                  </td>
                  <td v-if="showLineTaxColumn" class="px-2 py-2 align-middle">
                    <input v-model.number="line.tax_rate" type="number" min="0" max="100" class="invoicing-sf-input-table text-right" :disabled="isLocked" />
                  </td>
                  <td class="px-2 py-2 align-middle text-right font-medium text-gray-800 whitespace-nowrap">
                    {{ lineTotalDisplay(line).toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </td>
                  <td class="px-1 py-2 text-center">
                    <button
                      v-if="!isLocked && form.lines.length > 1"
                      type="button"
                      class="text-red-500 hover:text-red-700"
                      @click="form.lines.splice(idx, 1)"
                    >
                      ×
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <button v-if="!isLocked" type="button" class="mt-2 text-sm text-indigo-600 hover:underline" @click="addLine">
            {{ t('invoicing.add_line') }}
          </button>
        </section>

        <section class="grid md:grid-cols-2 gap-6">
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.note_footer') }}</label>
            <textarea v-model="form.note_footer" rows="4" class="invoicing-sf-input" :disabled="isLocked" />
          </div>
          <div class="rounded-lg bg-sky-50 border border-sky-200 p-4 space-y-3">
            <div class="flex items-center gap-2">
              <label class="text-sm text-gray-700 shrink-0">{{ t('invoicing.discount') }}</label>
              <input
                v-model.number="form.discount_percent"
                type="number"
                min="0"
                max="100"
                class="invoicing-sf-input w-20 text-right"
                :disabled="isLocked"
              />
              <span class="text-sm text-gray-500">%</span>
              <span class="ml-auto text-sm text-gray-600">
                {{ discountAmount.toLocaleString(locale, { minimumFractionDigits: 2 }) }} {{ form.currency }}
              </span>
            </div>
            <div class="text-right border-t border-sky-200 pt-3">
              <span class="text-sm text-gray-600">{{ t('invoicing.grand_total') }}</span>
              <div class="text-2xl font-bold text-gray-900">
                {{ previewTotals.total.toLocaleString(locale, { minimumFractionDigits: 2 }) }} {{ form.currency }}
              </div>
            </div>
          </div>
        </section>

        <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.issuer_name') }}</label>
            <input :value="company?.issuer_name || ''" type="text" class="invoicing-sf-input bg-gray-50" readonly />
          </div>
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.issuer_phone') }}</label>
            <input :value="company?.issuer_phone || ''" type="text" class="invoicing-sf-input bg-gray-50" readonly />
          </div>
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.issuer_web') }}</label>
            <input :value="company?.website || ''" type="text" class="invoicing-sf-input bg-gray-50" readonly />
          </div>
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.issuer_email') }}</label>
            <input :value="company?.issuer_email || ''" type="email" class="invoicing-sf-input bg-gray-50" readonly />
          </div>
        </section>

        <section class="grid sm:grid-cols-2 gap-4">
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.tags') }}</label>
            <input v-model="tagsInput" type="text" class="invoicing-sf-input" :placeholder="t('invoicing.tags_placeholder')" :disabled="isLocked" />
          </div>
          <div>
                <label class="invoicing-sf-label">{{ t('invoicing.internal_note') }}</label>
            <textarea v-model="form.internal_note" rows="2" class="invoicing-sf-input" :disabled="isLocked" />
          </div>
        </section>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div v-if="!isLocked" class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="invoicing-btn-primary px-6 py-3" :disabled="saving">
            {{ isNew || documentStatus === 'draft' ? t('invoicing.save_invoice') : t('invoicing.save_changes') }}
          </button>
        <button
          v-if="isNew || documentStatus === 'draft'"
          type="button"
          class="invoicing-btn-primary px-6 py-3"
          :disabled="saving"
          @click="save(true)"
        >
          {{ t('invoicing.save_and_pdf') }}
        </button>
          <RouterLink
            v-if="!isNew"
            :to="{ name: 'invoicing-invoice-show', params: { companyId, documentId } }"
            class="invoicing-btn-secondary"
          >
            {{ t('common.cancel') }}
          </RouterLink>
        </div>
      </form>

    <ContactCreateModal
      :open="showContactCreateModal"
      :company-id="companyId"
      @close="showContactCreateModal = false"
      @saved="onContactCreated"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import ContactCreateModal from '../../components/invoicing/ContactCreateModal.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoiceLineUnitSelect from '../../components/invoicing/InvoiceLineUnitSelect.vue';
import StockLineSuggestField from '../../components/invoicing/StockLineSuggestField.vue';
import type { InvoiceLineForm } from '../../components/invoicing/InvoiceLivePreview.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import api, { businessDocumentPdfPath, getWebBlob } from '../../services/api';
import { companyCurrencyOptions } from '../../config/companyCurrencies';
import { appSettingsFromCompany } from '../../composables/useCompanyAppSettings';
import { useInvoiceDocument } from '../../composables/useInvoiceDocument';

const {
  t,
  locale,
  router,
  companyId,
  documentId,
  saving,
  error,
  documentStatus,
  displayDocumentNumber,
  company,
  contacts,
  linkedStores,
  tagsInput,
  isIssued,
  isLocked,
  form,
  selectedContact,
  previewTotals,
  lineTotalDisplay,
  newLine,
  payload,
  extractError,
  loadCompanyAndContacts,
  reloadDocument,
  contactShowTo,
  formatContactAddress,
  route,
  calcTotals,
  documentType,
  isUsCompany,
  documentRoutes,
  documentKind,
  isProforma,
  isQuote,
  isCreditNote,
  sourceDocument,
  applyDocument,
  showLineTaxColumn,
} = useInvoiceDocument();

const { rememberCompany } = useInvoicingLayout();

const isNew = computed(() => !documentId.value);
const showContactCreateModal = ref(false);
const showLineSuggester = computed(() => appSettingsFromCompany(company.value).show_line_suggester);

function onLineStockPick(
  line: InvoiceLineForm,
  payload: {
    name: string;
    description: string;
    unit: string;
    unit_price: number;
    company_stock_item_id: string;
    quantity_on_hand: number | null;
  }
) {
  line.name = payload.name;
  line.description = payload.description;
  line.unit = payload.unit;
  line.unit_price = payload.unit_price;
  line.company_stock_item_id = payload.company_stock_item_id;
  line.stock_quantity_hint = payload.quantity_on_hand;
}

function clearLineStockLink(line: InvoiceLineForm) {
  line.company_stock_item_id = null;
  line.stock_quantity_hint = null;
}

const documentCurrencyOptions = computed(() =>
  companyCurrencyOptions(form.currency || company.value?.default_currency)
);

const lineTaxColumnLabel = computed(() =>
  isUsCompany.value ? t('invoicing.sales_tax') : t('invoicing.vat')
);

const newTitle = computed(() => {
  if (isProforma.value) return t('invoicing.new_proforma');
  if (isQuote.value) return t('invoicing.new_quote');
  if (isCreditNote.value) return t('invoicing.new_credit_note');
  return t('invoicing.new_invoice');
});
const editTitle = computed(() => {
  if (isProforma.value) return t('invoicing.edit_proforma');
  if (isQuote.value) return t('invoicing.edit_quote');
  if (isCreditNote.value) return t('invoicing.edit_credit_note');
  return t('invoicing.edit_invoice');
});

const backTo = computed(() =>
  isNew.value
    ? { name: documentRoutes.value.list, params: { companyId: companyId.value } }
    : {
        name: documentRoutes.value.show,
        params: { companyId: companyId.value, documentId: documentId.value },
      }
);

const backLabel = computed(() => {
  if (isNew.value) {
    if (isProforma.value) return t('invoicing.proformas_title');
    if (isQuote.value) return t('invoicing.quotes_title');
    if (isCreditNote.value) return t('invoicing.credit_notes_title');
    return t('invoicing.invoices_title');
  }
  if (isProforma.value) return t('invoicing.view_proforma');
  if (isQuote.value) return t('invoicing.view_quote');
  if (isCreditNote.value) return t('invoicing.view_credit_note');
  return t('invoicing.view_invoice');
});

const discountAmount = computed(() => {
  const { subtotal, tax, total } = calcTotals();
  return subtotal + tax - total;
});

function onContactChange() {
  const c = selectedContact.value;
  if (!c || form.due_date) return;
  const days = isQuote.value ? 14 : Number(c.default_payment_terms_days ?? 14);
  const base = form.issue_date ? new Date(`${form.issue_date}T12:00:00`) : new Date();
  base.setDate(base.getDate() + days);
  form.due_date = base.toISOString().slice(0, 10);
}

function onContactCreated(contact: Record<string, unknown>) {
  const id = contact.id as string;
  if (!id) return;
  contacts.value = [...contacts.value, contact];
  form.company_contact_id = id;
  onContactChange();
}

function addLine() {
  form.lines.push(newLine());
}

async function save(downloadPdf: boolean) {
  if (isLocked.value) return;
  saving.value = true;
  error.value = '';
  try {
    const wasDraft = isNew.value || documentStatus.value === 'draft';
    let docId = documentId.value;
    if (!isNew.value && docId) {
      await api.patch(`/invoicing/companies/${companyId.value}/documents/${docId}`, payload());
    } else {
      const res = await api.post(`/invoicing/companies/${companyId.value}/documents`, payload());
      docId = res.data.data.id;
    }
    if (wasDraft && docId) {
      const issueRes = await api.post(
        `/invoicing/companies/${companyId.value}/documents/${docId}/issue`
      );
      await applyDocument(issueRes.data.data);
    }
    if (downloadPdf && docId) {
      const blob = await getWebBlob(businessDocumentPdfPath(companyId.value, docId));
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `invoice-${docId}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    }
    router.push({
      name: documentRoutes.value.show,
      params: { companyId: companyId.value, documentId: docId! },
    });
  } catch (e: any) {
    error.value = extractError(e);
  } finally {
    saving.value = false;
  }
}

watch(documentId, async () => {
  if (documentId.value) await reloadDocument();
});

onMounted(async () => {
  rememberCompany(companyId.value);
  documentType.value =
    documentKind.value === 'proforma'
      ? 'proforma'
      : documentKind.value === 'quote'
        ? 'quote'
        : documentKind.value === 'credit_note'
          ? 'credit_note'
          : 'invoice';
  await loadCompanyAndContacts();
  if (documentId.value) {
    await reloadDocument();
  } else {
    form.lines.push(newLine());
    const prefill = route.query.contact as string | undefined;
    if (prefill && contacts.value.some((c) => c.id === prefill)) {
      form.company_contact_id = prefill;
      onContactChange();
    }
  }
});
</script>
