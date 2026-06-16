import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { InvoiceLineForm } from '../components/invoicing/InvoiceLivePreview.vue';
import api from '../services/api';
import { isInvoicingLocalFirst } from '../evolu/flags';
import { allDocumentEventsQuery } from '../evolu/client';
import type { CompanyId, DocumentId } from '../evolu/schema';
import type { DocumentSavePayload } from '../evolu/documentCrud';
import { payloadFromApiDocument, markLocalDocumentPaid } from '../evolu/documentCrud';
import { variableSymbolFromNumber } from '../evolu/documentNumber';
import { documentHistoryFromEvents } from '../evolu/documentEventLog';
import type { EvoluDocumentRow } from '../evolu/documentMap';
import {
  buildEphemeralSnapshot,
  fetchEphemeralBtcpayCheckout,
  fetchEphemeralBtcpayStatus,
  resolveEphemeralBridgeCompanyId,
} from '../evolu/ephemeralBridge';
import { useLocalInvoiceDocumentSupport } from './useLocalInvoiceDocument';
import { useStoresStore } from '../store/stores';
import { appSettingsFromCompany } from './useCompanyAppSettings';
import { useCompanyVatPolicy } from './useCompanyVatPolicy';
import { DEFAULT_INVOICE_LINE_UNIT } from './useInvoiceLineUnits';
import { invoicingDocumentRoutes, type InvoicingDocumentRoutes } from './useInvoicingDocumentRoutes';
import type { InvoicingDocumentKind } from './useInvoicingLayout';
import { defaultWarehouseId, type WarehouseRow } from './useCompanyWarehouse';

function parseDocumentAmountPaid(value: unknown): number | null {
  if (value == null || value === '') {
    return null;
  }
  const amount = typeof value === 'number' ? value : parseFloat(String(value));
  return Number.isFinite(amount) ? amount : null;
}

const NEW_DOCUMENT_ROUTE_NAMES = new Set([
  'invoicing-invoice-new',
  'invoicing-proforma-new',
  'invoicing-quote-new',
  'invoicing-credit-note-new',
]);

export function useInvoiceDocument() {
  const { t, locale } = useI18n();
  const route = useRoute();
  const router = useRouter();
  const localFirst = isInvoicingLocalFirst();
  const local = localFirst ? useLocalInvoiceDocumentSupport() : null;
  const storesStore = useStoresStore();

  const companyId = computed(() => route.params.companyId as string);
  const documentId = computed(() => route.params.documentId as string | undefined);

  const saving = ref(false);
  const error = ref('');
  const success = ref('');
  const documentStatus = ref('draft');
  const canDelete = ref(false);
  const canCancel = ref(false);
  const canUnmarkPaid = ref(false);
  const documentNumber = ref('');
  const nextNumberPreview = ref('');
  const paidAt = ref<string | null>(null);
  const amountPaid = ref<number | null>(null);
  const bankMatch = ref<{
    match_type?: string;
    matched_at?: string;
    transaction?: { booked_at?: string };
  } | null>(null);
  const company = ref<Record<string, any> | null>(null);
  const contacts = ref<any[]>([]);
  const warehouses = ref<WarehouseRow[]>([]);
  const linkedStores = ref<any[]>([]);
  const history = ref<any[]>([]);
  const neighborIds = ref<string[]>([]);
  const tagsInput = ref('');
  const paymentToken = ref<string | null>(null);
  const localBtcpayCheckoutLink = ref('');
  const localBtcpayCheckoutLoading = ref(false);
  const localBtcpayInvoiceId = ref('');
  let localBtcpayPollTimer: ReturnType<typeof setInterval> | null = null;
  const sourceDocument = ref<{ id: string; number?: string; type?: string } | null>(null);
  const finalInvoice = ref<{ id: string; number?: string; status?: string } | null>(null);
  const quoteStatus = ref<string | null>(null);
  const resolvedQuoteStatus = ref<string | null>(null);

  const documentKind = computed(
    () => (route.meta.documentKind as InvoicingDocumentKind | undefined) ?? 'invoice'
  );

  function resolveDocumentTypeFromRoute(): 'invoice' | 'proforma' | 'quote' | 'credit_note' {
    if (documentKind.value === 'proforma') return 'proforma';
    if (documentKind.value === 'quote') return 'quote';
    if (documentKind.value === 'credit_note') return 'credit_note';
    return 'invoice';
  }

  const documentType = ref<'invoice' | 'proforma' | 'quote' | 'credit_note'>(
    resolveDocumentTypeFromRoute()
  );

  const documentRoutes = computed<InvoicingDocumentRoutes>(() =>
    invoicingDocumentRoutes(documentType.value)
  );

  const isProforma = computed(() => documentType.value === 'proforma');
  const isQuote = computed(() => documentType.value === 'quote');
  const isCreditNote = computed(() => documentType.value === 'credit_note');

  watch(
    () => route.meta.documentKind,
    async () => {
      documentType.value = resolveDocumentTypeFromRoute();
      if (!documentId.value && documentStatus.value === 'draft' && !documentNumber.value) {
        await loadNextNumberPreview();
      }
    }
  );

  const displayDocumentNumber = computed(() => {
    if (documentNumber.value) {
      return documentNumber.value;
    }
    if (nextNumberPreview.value) {
      return nextNumberPreview.value;
    }
    return '';
  });

  const isIssued = computed(() => documentStatus.value === 'issued');
  const isLocked = computed(() => ['paid', 'cancelled'].includes(documentStatus.value));
  const canUpdate = computed(() => ['draft', 'issued'].includes(documentStatus.value));
  const defaultVat = computed(() => Number(company.value?.vat_rate_default ?? 23));
  const isUsCompany = computed(() => company.value?.jurisdiction === 'us');
  const vatPolicy = useCompanyVatPolicy();
  const usesStripeUsTax = computed(
    () =>
      isUsCompany.value
      && (appSettingsFromCompany(company.value).us_sales_tax_provider === 'stripe_tax')
  );

  const defaultWarehouseIdValue = computed(() => defaultWarehouseId(warehouses.value));

  const serverTotals = ref<{
    subtotal: number;
    tax: number;
    total: number;
    tax_breakdown: { label?: string | null; tax_amount: string }[];
  } | null>(null);
  let usTaxPreviewTimer: ReturnType<typeof setTimeout> | null = null;

  const form = reactive({
    title: '',
    company_contact_id: '',
    store_id: '',
    issue_date: new Date().toISOString().slice(0, 10),
    delivery_date: '',
    due_date: '',
    variable_symbol: '',
    constant_symbol: '',
    specific_symbol: '',
    currency: 'EUR',
    discount_percent: 0,
    note_above_lines: '',
    note_footer: '',
    internal_note: '',
    pdf_locale: 'sk',
    pdf_show_signature: true,
    pdf_show_payment_info: true,
    payment_bank_enabled: true,
    payment_btc_enabled: false,
    lines: [] as InvoiceLineForm[],
  });

  const selectedContact = computed(
    () => contacts.value.find((c) => c.id === form.company_contact_id) ?? null
  );

  const neighborIndex = computed(() =>
    documentId.value ? neighborIds.value.indexOf(documentId.value) : -1
  );

  const pdfUrl = computed(() => {
    if (!documentId.value || documentStatus.value === 'draft') return '';
    return `${window.location.origin}/invoicing/companies/${companyId.value}/documents/${documentId.value}/pdf`;
  });

  const btcPayUrl = computed(() => {
    if (localFirst) {
      if (!form.payment_btc_enabled || documentStatus.value === 'draft' || documentStatus.value === 'paid') {
        return '';
      }
      return localBtcpayCheckoutLink.value;
    }
    if (!paymentToken.value || !form.payment_btc_enabled || documentStatus.value === 'draft') {
      return '';
    }
    return `${window.location.origin}/pay/i/${paymentToken.value}`;
  });

  const previewForm = computed(() => ({
    title: form.title,
    number: displayDocumentNumber.value,
    issue_date: form.issue_date,
    delivery_date: form.delivery_date,
    due_date: form.due_date,
    variable_symbol: form.variable_symbol,
    currency: form.currency,
    discount_percent: form.discount_percent,
    note_above_lines: form.note_above_lines,
    note_footer: form.note_footer,
    payment_bank_enabled: form.payment_bank_enabled,
    pdf_show_signature: form.pdf_show_signature,
    pdf_show_payment_info: form.pdf_show_payment_info,
    lines: form.lines,
  }));

  const showLineTaxColumn = computed(() =>
    vatPolicy.showsVatRateColumn(company.value, selectedContact.value),
  );

  function lineTaxApplies() {
    if (isUsCompany.value) {
      return true;
    }
    return vatPolicy.calculatesVatAmounts(company.value);
  }

  function lineTaxRate(line: InvoiceLineForm) {
    return vatPolicy.resolveLineTaxRate(
      company.value,
      selectedContact.value,
      line.tax_rate ?? defaultVat.value,
    );
  }

  function calcTotals() {
    let subtotal = 0;
    let tax = 0;
    for (const line of form.lines) {
      const net =
        (line.quantity || 0) * (line.unit_price || 0) * (1 - (line.line_discount_percent || 0) / 100);
      const lineTax = lineTaxApplies() ? net * (lineTaxRate(line) / 100) : 0;
      subtotal += net;
      tax += lineTax;
    }
    const gross = subtotal + tax;
    const total = gross * (1 - (form.discount_percent || 0) / 100);
    if (form.discount_percent > 0 && gross > 0) {
      const ratio = total / gross;
      subtotal *= ratio;
      tax *= ratio;
    }
    return { subtotal, tax, total };
  }

  const previewTotals = computed(() => {
    if (serverTotals.value) {
      return {
        subtotal: serverTotals.value.subtotal,
        tax: serverTotals.value.tax,
        total: serverTotals.value.total,
        tax_breakdown: serverTotals.value.tax_breakdown,
      };
    }
    return { ...calcTotals(), tax_breakdown: [] as { label?: string | null; tax_amount: string }[] };
  });

  function lineTotalDisplay(line: InvoiceLineForm) {
    const net =
      (line.quantity || 0) * (line.unit_price || 0) * (1 - (line.line_discount_percent || 0) / 100);
    const lineTax = lineTaxApplies() ? net * (lineTaxRate(line) / 100) : 0;
    return net + lineTax;
  }

  async function refreshUsSalesTaxPreview() {
    if (!usesStripeUsTax.value || !companyId.value || form.lines.length === 0) {
      serverTotals.value = null;
      return;
    }
    try {
      const res = await api.post(`/invoicing/companies/${companyId.value}/us-sales-tax/preview`, {
        company_contact_id: form.company_contact_id || null,
        currency: form.currency,
        discount_percent: form.discount_percent,
        lines: form.lines.map((l) => ({
          name: l.name || 'Line',
          description: l.description || null,
          quantity: l.quantity,
          unit: l.unit,
          unit_price: l.unit_price,
          line_discount_percent: l.line_discount_percent || 0,
          tax_rate: l.tax_rate ?? defaultVat.value,
        })),
      });
      const data = res.data.data;
      serverTotals.value = {
        subtotal: parseFloat(data.subtotal),
        tax: parseFloat(data.tax_total),
        total: parseFloat(data.total),
        tax_breakdown: data.tax_breakdown ?? [],
      };
    } catch {
      serverTotals.value = null;
    }
  }

  function scheduleUsSalesTaxPreview() {
    if (!usesStripeUsTax.value) {
      serverTotals.value = null;
      return;
    }
    if (usTaxPreviewTimer) clearTimeout(usTaxPreviewTimer);
    usTaxPreviewTimer = setTimeout(() => {
      void refreshUsSalesTaxPreview();
    }, 400);
  }

  watch(
    () => [
      form.lines,
      form.company_contact_id,
      form.discount_percent,
      form.currency,
      usesStripeUsTax.value,
    ],
    () => scheduleUsSalesTaxPreview(),
    { deep: true }
  );

  function newLine(): InvoiceLineForm {
    return {
      name: '',
      description: '',
      quantity: 1,
      unit: DEFAULT_INVOICE_LINE_UNIT,
      unit_price: 0,
      line_discount_percent: 0,
      tax_rate: vatPolicy.resolveLineTaxRate(company.value, selectedContact.value, defaultVat.value),
      company_stock_item_id: null,
      company_warehouse_id: defaultWarehouseIdValue.value || null,
      stock_quantity_hint: null,
      stock_quantities_by_warehouse: {},
      warehouse_deduct_on_issue: null,
    };
  }

  watch([selectedContact, () => company.value?.vat_status], () => {
    const rate = vatPolicy.resolveLineTaxRate(company.value, selectedContact.value, defaultVat.value);
    for (const line of form.lines) {
      line.tax_rate = rate;
    }
  });

  function parseTags(): string[] {
    return tagsInput.value
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
  }

  function payload() {
    return {
      ...form,
      type: documentType.value,
      tags: parseTags(),
      lines: form.lines.map((l) => ({
        name: l.name,
        description: l.description || null,
        quantity: l.quantity,
        unit: l.unit,
        unit_price: l.unit_price,
        line_discount_percent: l.line_discount_percent || 0,
        tax_rate: l.tax_rate ?? defaultVat.value,
        company_stock_item_id: l.company_stock_item_id || null,
        company_warehouse_id: l.company_warehouse_id || null,
      })),
    };
  }

  function extractError(e: any) {
    if (e instanceof Error && e.message && e.message !== 'validation' && e.message !== 'issue') {
      return e.message;
    }
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors && typeof fieldErrors === 'object') {
      for (const messages of Object.values(fieldErrors)) {
        if (Array.isArray(messages) && messages[0]) {
          return String(messages[0]);
        }
      }
    }
    return (
      e?.response?.data?.message
      || e?.response?.data?.errors?.status?.[0]
      || e?.response?.data?.errors?.store_id?.[0]
      || e?.response?.data?.errors?.document?.[0]
      || (e?.message === 'validation' ? t('invoicing.company_save_validation_error') : null)
      || (e?.message === 'issue' ? t('invoicing.issue_error') : null)
      || t('common.error')
    );
  }

  function existingLocalDocument() {
    if (!local || !documentId.value) return null;
    return (
      local.documentRows.value.find((row) => row.id === documentId.value) as
        | import('../evolu/documentMap').EvoluDocumentRow
        | undefined
    ) ?? null;
  }

  function localSaveOptions() {
    return {
      ...localTaxHelpers(),
      documentId: documentId.value as DocumentId | undefined,
      existingDocument: existingLocalDocument(),
    };
  }

  async function persistLocalDraftBeforeIssue(): Promise<void> {
    if (!local || !documentId.value) return;
    await local.refreshAll();
    const saveResult = local.saveLocalDocument(
      local.evolu,
      companyId.value as CompanyId,
      payload() as DocumentSavePayload,
      localSaveOptions(),
    );
    if (!saveResult.ok) {
      throw new Error('validation');
    }
    await local.refreshAll();
  }

  async function applyDocument(d: any) {
    documentType.value =
      d.type === 'proforma'
        ? 'proforma'
        : d.type === 'quote'
          ? 'quote'
          : d.type === 'credit_note'
            ? 'credit_note'
            : 'invoice';
    quoteStatus.value = d.quote_status ?? null;
    resolvedQuoteStatus.value = d.resolved_quote_status ?? d.quote_status ?? null;
    sourceDocument.value = d.source_document ?? null;
    finalInvoice.value = d.final_invoice ?? null;
    documentStatus.value = d.status;
    canDelete.value = Boolean(d.can_delete);
    canCancel.value = Boolean(d.can_cancel);
    canUnmarkPaid.value = Boolean(d.can_unmark_paid);
    documentNumber.value = d.number || '';
    nextNumberPreview.value = '';
    paymentToken.value = d.payment_token ?? null;
    paidAt.value = d.paid_at;
    amountPaid.value = parseDocumentAmountPaid(d.amount_paid);
    bankMatch.value = d.bank_match ?? null;
    tagsInput.value = (d.tags || []).join(', ');
    Object.assign(form, {
      title: d.title || '',
      company_contact_id: d.company_contact_id || '',
      store_id: d.store_id || '',
      issue_date: d.issue_date?.slice(0, 10) || form.issue_date,
      delivery_date: d.delivery_date?.slice(0, 10) || '',
      due_date: d.due_date?.slice(0, 10) || '',
      variable_symbol: d.variable_symbol || '',
      constant_symbol: d.constant_symbol || '',
      specific_symbol: d.specific_symbol || '',
      currency: d.currency || 'EUR',
      discount_percent: parseFloat(d.discount_percent) || 0,
      note_above_lines: d.note_above_lines || '',
      note_footer: d.note_footer || '',
      internal_note: d.internal_note || '',
      pdf_locale: d.pdf_locale || 'sk',
      pdf_show_signature: d.pdf_show_signature ?? true,
      pdf_show_payment_info: d.pdf_show_payment_info ?? true,
      payment_bank_enabled: d.payment_bank_enabled,
      payment_btc_enabled: d.payment_btc_enabled,
      lines: (d.lines || []).map((l: any) => ({
        name: l.name,
        description: l.description || '',
        quantity: parseFloat(l.quantity),
        unit: l.unit || DEFAULT_INVOICE_LINE_UNIT,
        unit_price: parseFloat(l.unit_price),
        line_discount_percent: parseFloat(l.line_discount_percent) || 0,
        tax_rate: parseFloat(l.tax_rate) || defaultVat.value,
        company_stock_item_id: l.company_stock_item_id || null,
        company_warehouse_id: l.company_warehouse_id ?? null,
        stock_quantity_hint: null,
        stock_quantities_by_warehouse: {},
        warehouse_deduct_on_issue: null,
      })),
    });
    if (documentStatus.value === 'draft' && !documentNumber.value) {
      await loadNextNumberPreview();
    }
    const numberForTitle = documentNumber.value || nextNumberPreview.value;
    if (!form.title && numberForTitle) {
      const prefixKey = isProforma.value
        ? 'invoicing.proforma_title_prefix'
        : isQuote.value
          ? 'invoicing.quote_title_prefix'
          : isCreditNote.value
            ? 'invoicing.credit_note_title_prefix'
            : 'invoicing.invoice_title_prefix';
      form.title = `${t(prefixKey)} ${numberForTitle}`;
    }
    if (form.lines.length === 0) form.lines.push(newLine());

    applyPaymentDefaults();
  }

  async function loadNextNumberPreview() {
    if (local) {
      await local.refreshAll();
      nextNumberPreview.value = await local.previewNumberAsync(
        companyId.value as CompanyId,
        documentType.value,
      );
    } else {
      try {
        const res = await api.get(
          `/invoicing/companies/${companyId.value}/number-series/preview`,
          { params: { type: documentType.value } }
        );
        nextNumberPreview.value = res.data.data?.next_number ?? '';
      } catch {
        nextNumberPreview.value = '';
      }
    }

    if (
      !documentId.value
      && documentStatus.value === 'draft'
      && !documentNumber.value
      && nextNumberPreview.value
      && !form.variable_symbol.trim()
    ) {
      form.variable_symbol = variableSymbolFromNumber(nextNumberPreview.value);
    }
  }

  function resetDocumentStateForNewDraft() {
    documentStatus.value = 'draft';
    documentNumber.value = '';
    nextNumberPreview.value = '';
    paidAt.value = null;
    amountPaid.value = null;
    bankMatch.value = null;
    paymentToken.value = null;
    sourceDocument.value = null;
    finalInvoice.value = null;
    quoteStatus.value = null;
    resolvedQuoteStatus.value = null;
    canDelete.value = false;
    canCancel.value = false;
    canUnmarkPaid.value = false;
    tagsInput.value = '';
    error.value = '';
    success.value = '';
    localBtcpayCheckoutLink.value = '';
    localBtcpayInvoiceId.value = '';
    stopLocalBtcpayPolling();

    const today = new Date().toISOString().slice(0, 10);
    Object.assign(form, {
      title: '',
      company_contact_id: '',
      store_id: '',
      issue_date: today,
      delivery_date: '',
      due_date: '',
      variable_symbol: '',
      constant_symbol: '',
      specific_symbol: '',
      currency: company.value?.default_currency || 'EUR',
      discount_percent: 0,
      note_above_lines: '',
      note_footer: company.value?.legal_footer_note || '',
      internal_note: '',
      pdf_locale: 'sk',
      pdf_show_signature: true,
      pdf_show_payment_info: true,
      payment_bank_enabled: true,
      payment_btc_enabled: false,
      lines: [] as InvoiceLineForm[],
    });
    documentType.value = resolveDocumentTypeFromRoute();
  }

  async function initNewDraft() {
    resetDocumentStateForNewDraft();
    form.lines.push(newLine());
    applyPaymentDefaults();
    applyQuoteDueDefault();
    const app = appSettingsFromCompany(company.value);
    if (!form.constant_symbol) form.constant_symbol = app.default_constant_symbol;
    if (documentType.value === 'quote') {
      form.payment_bank_enabled = false;
      form.payment_btc_enabled = false;
      form.pdf_show_payment_info = false;
    } else {
      form.payment_bank_enabled = app.show_pay_by_square;
    }
    await loadNextNumberPreview();
    const numberForTitle = documentNumber.value || nextNumberPreview.value;
    if (!form.title && numberForTitle) {
      const prefixKey = isProforma.value
        ? 'invoicing.proforma_title_prefix'
        : isQuote.value
          ? 'invoicing.quote_title_prefix'
          : isCreditNote.value
            ? 'invoicing.credit_note_title_prefix'
            : 'invoicing.invoice_title_prefix';
      form.title = `${t(prefixKey)} ${numberForTitle}`;
    }
  }

  function applyPaymentDefaults() {
    const stores = linkedStores.value;
    if (stores.length === 0) {
      return;
    }
    if (!form.store_id) {
      form.store_id = stores[0].id;
    }
    if (!documentId.value) {
      form.payment_btc_enabled = documentType.value !== 'quote' && documentType.value !== 'credit_note';
    }
  }

  function applyQuoteDueDefault() {
    if (documentType.value !== 'quote' || documentId.value || form.due_date) {
      return;
    }
    const base = new Date(form.issue_date || new Date().toISOString().slice(0, 10));
    base.setDate(base.getDate() + 14);
    form.due_date = base.toISOString().slice(0, 10);
  }

  async function loadCompanyAndContacts() {
    if (local) {
      if (!storesStore.stores.length) {
        await storesStore.fetchStores();
      }
      await local.refreshAll();
      company.value = local.companyApi(companyId.value);
      linkedStores.value = company.value?.stores ?? [];
      contacts.value = local.contactsForCompany(companyId.value);
      warehouses.value = [];

      if (company.value) {
        form.currency = company.value.default_currency || 'EUR';
        if (!form.note_footer) form.note_footer = company.value.legal_footer_note || '';
      }
      applyPaymentDefaults();

      if (!documentId.value) {
        const app = appSettingsFromCompany(company.value);
        if (!form.constant_symbol) form.constant_symbol = app.default_constant_symbol;
        documentType.value = resolveDocumentTypeFromRoute();
        if (documentType.value === 'quote') {
          form.payment_bank_enabled = false;
          form.payment_btc_enabled = false;
          form.pdf_show_payment_info = false;
        } else {
          form.payment_bank_enabled = app.show_pay_by_square;
        }
        applyQuoteDueDefault();
      }
      return;
    }

    const companyRes = await api.get(`/invoicing/companies/${companyId.value}`);
    company.value = companyRes.data.data;
    linkedStores.value = company.value?.stores ?? [];
    form.currency = company.value?.default_currency || 'EUR';
    if (!form.note_footer) form.note_footer = company.value?.legal_footer_note || '';

    applyPaymentDefaults();

    if (!documentId.value) {
      const app = appSettingsFromCompany(company.value);
      if (!form.constant_symbol) form.constant_symbol = app.default_constant_symbol;
      documentType.value = resolveDocumentTypeFromRoute();
      if (documentType.value === 'quote') {
        form.payment_bank_enabled = false;
        form.payment_btc_enabled = false;
        form.pdf_show_payment_info = false;
      } else {
        form.payment_bank_enabled = app.show_pay_by_square;
      }
      applyQuoteDueDefault();
    }

    const contactsRes = await api.get(`/invoicing/companies/${companyId.value}/contacts`);
    contacts.value = contactsRes.data.data ?? [];

    const whRes = await api.get(`/invoicing/companies/${companyId.value}/warehouses`);
    warehouses.value = (whRes.data.data ?? []).filter((w: WarehouseRow) => w.is_active);
  }

  watch(documentType, async () => {
    if (documentStatus.value === 'draft' && !documentNumber.value) {
      await loadNextNumberPreview();
    }
  });

  watch(documentId, async (id, prevId) => {
    if (id) {
      await reloadDocument();
      return;
    }
    if (prevId) {
      await initNewDraft();
    }
  });

  watch(
    () => route.name,
    async (name, prevName) => {
      if (!documentId.value && name && name !== prevName && NEW_DOCUMENT_ROUTE_NAMES.has(String(name))) {
        documentType.value = resolveDocumentTypeFromRoute();
        await initNewDraft();
      }
    },
  );

  async function reloadDocument() {
    if (!documentId.value) return;
    if (local) {
      await local.refreshAll();
      const apiDoc = local.documentApi(documentId.value as DocumentId);
      if (apiDoc) await applyDocument(apiDoc);
      await loadLocalBtcpayCheckout();
      return;
    }
    const docRes = await api.get(
      `/invoicing/companies/${companyId.value}/documents/${documentId.value}`
    );
    await applyDocument(docRes.data.data);
  }

  function buildCurrentEphemeralSnapshot() {
    const p = payload();
    return buildEphemeralSnapshot(
      company.value,
      selectedContact.value,
      {
        type: documentType.value,
        status: documentStatus.value,
        title: form.title,
        number: documentNumber.value || displayDocumentNumber.value,
        variable_symbol: form.variable_symbol,
        constant_symbol: form.constant_symbol,
        specific_symbol: form.specific_symbol,
        issue_date: form.issue_date,
        delivery_date: form.delivery_date,
        due_date: form.due_date,
        currency: form.currency,
        note_above_lines: form.note_above_lines,
        note_footer: form.note_footer,
        internal_note: form.internal_note,
        pdf_locale: form.pdf_locale,
        pdf_show_signature: form.pdf_show_signature,
        pdf_show_payment_info: form.pdf_show_payment_info,
        payment_bank_enabled: form.payment_bank_enabled,
        payment_btc_enabled: form.payment_btc_enabled,
        discount_percent: form.discount_percent,
        amount_paid: amountPaid.value,
      },
      p.lines,
      {
        storeId: form.store_id || null,
        evoluDocumentId: documentId.value || null,
        btcpayCheckoutLink: localBtcpayCheckoutLink.value || null,
      },
    );
  }

  async function loadLocalBtcpayCheckout() {
    localBtcpayCheckoutLink.value = '';
    localBtcpayInvoiceId.value = '';
    stopLocalBtcpayPolling();
    if (!localFirst || !documentId.value) return;
    if (
      !form.payment_btc_enabled
      || !form.store_id
      || documentStatus.value === 'draft'
      || documentStatus.value === 'paid'
    ) {
      return;
    }

    localBtcpayCheckoutLoading.value = true;
    try {
      const bridgeCompanyId = await resolveEphemeralBridgeCompanyId();
      const result = await fetchEphemeralBtcpayCheckout(
        buildCurrentEphemeralSnapshot(),
        form.store_id,
        documentId.value,
        bridgeCompanyId,
      );
      localBtcpayCheckoutLink.value = result.checkout_link || '';
      localBtcpayInvoiceId.value = result.btcpay_invoice_id || '';
      if (localBtcpayInvoiceId.value) {
        startLocalBtcpayPolling();
      } else if (!localBtcpayCheckoutLink.value) {
        error.value = t('invoicing.local_first_btcpay_checkout_missing');
      }
    } catch (e: unknown) {
      localBtcpayCheckoutLink.value = '';
      localBtcpayInvoiceId.value = '';
      error.value = extractError(e);
    } finally {
      localBtcpayCheckoutLoading.value = false;
    }
  }

  function stopLocalBtcpayPolling() {
    if (localBtcpayPollTimer) {
      clearInterval(localBtcpayPollTimer);
      localBtcpayPollTimer = null;
    }
  }

  async function syncEphemeralBtcpayPayment(): Promise<boolean> {
    if (!local || !documentId.value || !localBtcpayInvoiceId.value) return false;
    if (documentStatus.value === 'paid') {
      stopLocalBtcpayPolling();
      return true;
    }
    try {
      const status = await fetchEphemeralBtcpayStatus(
        documentId.value,
        localBtcpayInvoiceId.value,
      );
      if (status.status !== 'paid') return false;
      await local.refreshAll();
      markLocalDocumentPaid(
        local.evolu,
        documentId.value as DocumentId,
        local.documentRows.value as EvoluDocumentRow[],
      );
      await local.refreshAll();
      const apiDoc = local.documentApi(documentId.value as DocumentId);
      if (apiDoc) await applyDocument(apiDoc);
      stopLocalBtcpayPolling();
      success.value = t('invoicing.local_first_btcpay_paid_synced');
      return true;
    } catch {
      return false;
    }
  }

  function startLocalBtcpayPolling() {
    stopLocalBtcpayPolling();
    if (!localFirst || documentStatus.value === 'paid' || !localBtcpayInvoiceId.value) {
      return;
    }
    void syncEphemeralBtcpayPayment();
    localBtcpayPollTimer = setInterval(() => {
      void syncEphemeralBtcpayPayment();
    }, 15000);
  }

  onUnmounted(() => {
    stopLocalBtcpayPolling();
  });

  async function loadHistory() {
    if (!documentId.value) return;
    if (local) {
      await local.evolu.loadQuery(allDocumentEventsQuery);
      const events = local.documentEventRows.value.filter(
        (event) => event.documentId === documentId.value,
      );
      history.value = documentHistoryFromEvents(events);
      return;
    }
    const res = await api.get(
      `/invoicing/companies/${companyId.value}/documents/${documentId.value}/history`
    );
    history.value = res.data.data ?? [];
  }

  async function loadNeighbors() {
    if (local) {
      await local.refreshAll();
      neighborIds.value = local.documentRows.value
        .filter(
          (d) =>
            d.companyId === companyId.value
            && d.documentType === documentType.value,
        )
        .map((d) => d.id);
      return;
    }
    const res = await api.get(`/invoicing/companies/${companyId.value}/documents`, {
      params: { type: documentType.value, per_page: 100 },
    });
    neighborIds.value = (res.data.data ?? []).map((d: { id: string }) => d.id);
  }

  function goNeighbor(delta: number, routeName: string) {
    const idx = neighborIndex.value + delta;
    if (idx < 0 || idx >= neighborIds.value.length) return;
    router.push({
      name: routeName,
      params: { companyId: companyId.value, documentId: neighborIds.value[idx] },
    });
  }

  function contactShowTo(contactId: string) {
    return {
      name: 'invoicing-contact-show',
      params: { companyId: companyId.value, contactId },
    };
  }

  function contactEditTo(contactId: string) {
    return {
      name: 'invoicing-contact-edit',
      params: { companyId: companyId.value, contactId },
    };
  }

  function contactNewTo() {
    return { name: 'invoicing-contact-new', params: { companyId: companyId.value } };
  }

  function formatContactAddress(c: Record<string, any>) {
    const parts = [
      [c.street, c.postal_code, c.city].filter(Boolean).join(', '),
      c.country,
    ].filter(Boolean);
    return parts.join(', ') || '';
  }

  function localTaxHelpers() {
    return local!.saveOptions(
      defaultVat.value,
      () => lineTaxApplies(),
      (line) =>
        lineTaxRate({
          name: line.name,
          description: line.description || '',
          quantity: line.quantity,
          unit: line.unit,
          unit_price: line.unit_price,
          line_discount_percent: line.line_discount_percent,
          tax_rate: line.tax_rate,
          company_stock_item_id: line.company_stock_item_id,
          company_warehouse_id: line.company_warehouse_id,
          stock_quantity_hint: null,
          stock_quantities_by_warehouse: {},
          warehouse_deduct_on_issue: null,
        }),
    );
  }

  async function saveLocalDocumentFlow(): Promise<string | null> {
    if (!local) return null;
    await local.refreshAll();
    const p = payload() as DocumentSavePayload;
    const wasDraft = !documentId.value || documentStatus.value === 'draft';
    const saveResult = local.saveLocalDocument(
      local.evolu,
      companyId.value as CompanyId,
      p,
      {
        ...localSaveOptions(),
      },
    );
    if (!saveResult.ok) {
      throw new Error('validation');
    }
    const docId = saveResult.value.id;
    if (!documentId.value) {
      await router.replace({
        name: route.name as string,
        params: { ...route.params, documentId: docId },
      });
      await local.refreshAll();
    }
    if (wasDraft) {
      const companyRow = local.companyRows.value.find((c) => c.id === companyId.value);
      if (!companyRow) throw new Error('company');
      await local.refreshAll();
      const issueResult = await local.issueLocalDocumentAsync(
        local.evolu,
        docId as DocumentId,
        companyRow as import('../evolu/companyMap').EvoluCompanyRow,
        local.documentRows.value as import('../evolu/documentMap').EvoluDocumentRow[],
      );
      if (!issueResult.ok) throw new Error('issue');
      await local.refreshAll();
      const apiDoc = local.documentApi(docId as DocumentId);
      if (apiDoc) await applyDocument(apiDoc);
    }
    return docId;
  }

  async function persistLocalPdfOptions(): Promise<void> {
    if (!local || !documentId.value || isLocked.value) return;
    await local.refreshAll();
    local.saveLocalDocument(local.evolu, companyId.value as CompanyId, payload() as DocumentSavePayload, {
      ...localSaveOptions(),
    });
  }

  return {
    t,
    locale,
    router,
    route,
    localFirst,
    local,
    companyId,
    documentId,
    saving,
    error,
    success,
    documentStatus,
    documentNumber,
    nextNumberPreview,
    displayDocumentNumber,
    loadNextNumberPreview,
    initNewDraft,
    paidAt,
    amountPaid,
    bankMatch,
    company,
    contacts,
    linkedStores,
    history,
    neighborIds,
    tagsInput,
    isIssued,
    isLocked,
    canUpdate,
    canDelete,
    canCancel,
    canUnmarkPaid,
    defaultVat,
    isUsCompany,
    usesStripeUsTax,
    form,
    selectedContact,
    neighborIndex,
    pdfUrl,
    btcPayUrl,
    localBtcpayCheckoutLoading,
    loadLocalBtcpayCheckout,
    paymentToken,
    documentType,
    documentKind,
    documentRoutes,
    isProforma,
    isQuote,
    isCreditNote,
    quoteStatus,
    resolvedQuoteStatus,
    sourceDocument,
    finalInvoice,
    previewForm,
    previewTotals,
    showLineTaxColumn,
    calcTotals,
    lineTotalDisplay,
    newLine,
    payload,
    extractError,
    applyDocument,
    warehouses,
    defaultWarehouseIdValue,
    loadCompanyAndContacts,
    reloadDocument,
    loadHistory,
    loadNeighbors,
    goNeighbor,
    contactShowTo,
    contactEditTo,
    contactNewTo,
    formatContactAddress,
    saveLocalDocumentFlow,
    persistLocalPdfOptions,
    persistLocalDraftBeforeIssue,
    buildCurrentEphemeralSnapshot,
    localTaxHelpers,
    payloadFromApiDocument,
  };
}
