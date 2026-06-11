<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader
        :company-label="company?.trade_name || company?.legal_name"
        :show-filter-bar="false"
      />
    </template>
    <template #toolbar>
      <RouterLink
        :to="{ name: 'invoicing-recurring', params: { companyId } }"
        class="invoicing-back mb-0"
      >
        ← {{ t('invoicing.doc_nav_recurring') }}
      </RouterLink>
    </template>

    <h1 class="invoicing-title mb-4">
      {{ isNew ? t('invoicing.new_recurring') : form.title || t('invoicing.edit_recurring') }}
    </h1>

    <form class="invoicing-card-pad space-y-6" @submit.prevent="save">
      <section class="grid md:grid-cols-2 gap-6 p-4 rounded-lg bg-gray-50 border border-gray-200">
        <div class="space-y-3">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.recurring_repeat') }}</label>
            <select v-model="form.recurrence_interval" class="invoicing-sf-input">
              <option value="monthly">{{ t('invoicing.recurring_interval_monthly') }}</option>
              <option value="yearly">{{ t('invoicing.recurring_interval_yearly') }}</option>
            </select>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.recurring_first_issue') }}</label>
            <input v-model="form.first_issue_date" type="date" class="invoicing-sf-input" required />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.recurring_next') }}</label>
            <input v-model="form.next_issue_date" type="date" class="invoicing-sf-input" required />
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.issue_last_day_of_month" type="checkbox" class="rounded border-gray-300" />
            {{ t('invoicing.recurring_last_day_month') }}
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.repeat_indefinitely" type="checkbox" class="rounded border-gray-300" />
            {{ t('invoicing.recurring_unlimited') }}
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
            {{ t('invoicing.recurring_active') }}
          </label>
        </div>
        <div class="space-y-3">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.pdf_language') }}</label>
            <select v-model="form.pdf_locale" class="invoicing-sf-input">
              <option value="sk">Slovenčina</option>
              <option value="en">English</option>
              <option value="cs">Čeština</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.pdf_show_signature" type="checkbox" class="rounded border-gray-300" />
            {{ t('invoicing.pdf_signature') }}
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.pdf_show_payment_info" type="checkbox" class="rounded border-gray-300" />
            {{ t('invoicing.pdf_payment_info') }}
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.send_email_after_issue" type="checkbox" class="rounded border-gray-300" />
            {{ t('invoicing.recurring_email_after') }}
          </label>
        </div>
      </section>

      <details class="rounded-lg border border-gray-200 bg-amber-50/50 p-3 text-sm text-gray-700">
        <summary class="cursor-pointer font-medium text-gray-800">
          {{ t('invoicing.recurring_placeholders_title') }}
        </summary>
        <p class="mt-2 text-xs">{{ t('invoicing.recurring_placeholders_hint') }}</p>
        <p class="mt-2 font-mono text-xs leading-relaxed">
          {{ recurringPlaceholderTokens }}
        </p>
      </details>

      <section>
        <h2 class="text-xs font-bold uppercase tracking-wide text-gray-600 mb-4">
          {{ t('invoicing.section_general') }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.recurring_doc_type') }}</label>
            <select v-model="form.document_type" class="invoicing-sf-input" @change="onDocumentTypeChange">
              <option value="invoice">{{ t('invoicing.recurring_type_invoice') }}</option>
              <option value="proforma">{{ t('invoicing.recurring_type_proforma') }}</option>
            </select>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.variable_symbol') }}</label>
            <input v-model="form.variable_symbol" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.invoice_title') }}</label>
            <input v-model="form.title" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.recurring_payment_terms') }}</label>
            <div class="flex items-center gap-2">
              <input v-model.number="form.payment_terms_days" type="number" min="0" class="invoicing-sf-input w-24" />
              <span class="text-sm text-gray-600">{{ t('invoicing.recurring_days') }}</span>
            </div>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.delivery_date') }}</label>
            <select v-model="form.delivery_date_mode" class="invoicing-sf-input">
              <option value="on_issue">{{ t('invoicing.recurring_delivery_on_issue') }}</option>
              <option value="empty">{{ t('invoicing.recurring_delivery_empty') }}</option>
            </select>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.customer') }}</label>
            <select v-model="form.company_contact_id" class="invoicing-sf-input">
              <option value="">{{ t('invoicing.select_customer') }}</option>
              <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <button
              type="button"
              class="mt-1 inline-block text-sm text-indigo-600 hover:underline"
              @click="showContactCreateModal = true"
            >
              + {{ t('invoicing.create_client') }}
            </button>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.btcpay_store') }}</label>
            <select v-model="form.store_id" class="invoicing-sf-input" :disabled="linkedStores.length === 0">
              <option v-if="linkedStores.length === 0" value="">{{ t('invoicing.no_store') }}</option>
              <option v-for="s in linkedStores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.currency') }}</label>
            <select v-model="form.currency" class="invoicing-sf-input">
              <option v-for="code in profileCurrencyOptions" :key="code" :value="code">{{ code }}</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700 pt-6">
            <input v-model="form.payment_bank_enabled" type="checkbox" />
            {{ t('invoicing.payment_bank') }}
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700 pt-6">
            <input v-model="form.payment_btc_enabled" type="checkbox" />
            {{ t('invoicing.payment_btc') }}
          </label>
        </div>
      </section>

      <section>
        <label class="invoicing-sf-label">{{ t('invoicing.note_above') }}</label>
        <textarea v-model="form.note_above_lines" rows="2" class="invoicing-sf-input" />
      </section>

      <section>
        <div class="hidden md:block overflow-x-auto rounded border border-gray-200">
          <table class="w-full text-sm">
            <thead class="bg-gray-100 text-gray-600 text-xs uppercase">
              <tr>
                <th class="text-left px-3 py-2">{{ t('invoicing.col_item') }}</th>
                <th v-if="showLineSuggester" class="text-left px-2 py-2 min-w-[130px]">{{ t('invoicing.warehouse_col_name') }}</th>
                <th class="text-center px-2 py-2 w-20">{{ t('invoicing.col_qty') }}</th>
                <th class="text-center px-2 py-2 w-24">{{ t('invoicing.col_unit') }}</th>
                <th class="text-right px-2 py-2 w-28">{{ t('invoicing.col_unit_price') }}</th>
                <th v-if="company?.vat_payer" class="text-right px-2 py-2 w-16">{{ t('invoicing.vat') }}%</th>
                <th class="text-right px-2 py-2 w-28">{{ t('invoicing.col_line_total_vat') }}</th>
                <th class="w-8"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, idx) in form.lines" :key="idx" class="border-t border-gray-200">
                <td class="px-3 py-2 align-top">
                  <StockLineSuggestField
                    v-if="showLineSuggester"
                    :company-id="companyId"
                    :name="line.name"
                    :description="line.description"
                    :stock-item-id="line.company_stock_item_id"
                    :warehouse-id="line.company_warehouse_id"
                    :quantity-on-hand="line.stock_quantity_hint"
                    :deduct-on-issue="line.warehouse_deduct_on_issue"
                    :unit="line.unit"
                    :enabled="showLineSuggester"
                    :required="true"
                    :description-placeholder="t('invoicing.item_description')"
                    @update:name="line.name = $event"
                    @update:description="line.description = $event"
                    @pick="onLineStockPick(line, $event)"
                    @clear-stock-link="clearLineStockLink(line)"
                  />
                  <template v-else>
                    <input v-model="line.name" class="invoicing-sf-input-table" required />
                    <input
                      v-model="line.description"
                      class="invoicing-sf-input-table mt-1 text-gray-500"
                      :placeholder="t('invoicing.item_description')"
                    />
                  </template>
                </td>
                <td v-if="showLineSuggester" class="px-2 py-2 align-top">
                  <StockWarehouseSelect
                    v-model="line.company_warehouse_id"
                    :warehouses="warehouses"
                    @update:model-value="onLineWarehouseChange(line)"
                  />
                </td>
                <td class="px-2 py-2 align-middle">
                  <input v-model.number="line.quantity" type="number" min="0.0001" step="any" class="invoicing-sf-input-table text-center" />
                </td>
                <td class="px-2 py-2 align-middle">
                  <InvoiceLineUnitSelect v-model="line.unit" />
                </td>
                <td class="px-2 py-2 align-middle">
                  <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="invoicing-sf-input-table text-right" />
                </td>
                <td v-if="company?.vat_payer" class="px-2 py-2 align-middle">
                  <input v-model.number="line.tax_rate" type="number" min="0" max="100" class="invoicing-sf-input-table text-right" />
                </td>
                <td class="px-2 py-2 align-middle text-right font-medium whitespace-nowrap">
                  {{ lineTotal(line).toLocaleString(locale, { minimumFractionDigits: 2 }) }}
                </td>
                <td class="px-1 py-2 text-center">
                  <button
                    v-if="form.lines.length > 1"
                    type="button"
                    class="text-red-500"
                    @click="form.lines.splice(idx, 1)"
                  >
                    ×
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="md:hidden space-y-3">
          <div v-for="(line, idx) in form.lines" :key="`mobile-line-${idx}`" class="invoicing-line-item-card">
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-semibold text-gray-500 uppercase">{{ t('invoicing.col_item') }} {{ idx + 1 }}</span>
              <button v-if="form.lines.length > 1" type="button" class="text-red-500 text-sm" @click="form.lines.splice(idx, 1)">
                {{ t('invoicing.action_delete') }}
              </button>
            </div>
            <input v-model="line.name" class="invoicing-sf-input w-full" required />
            <input v-model="line.description" class="invoicing-sf-input w-full" :placeholder="t('invoicing.item_description')" />
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-xs text-gray-500">{{ t('invoicing.col_qty') }}</label>
                <input v-model.number="line.quantity" type="number" min="0.0001" step="any" class="invoicing-sf-input w-full" />
              </div>
              <div>
                <label class="text-xs text-gray-500">{{ t('invoicing.col_unit_price') }}</label>
                <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="invoicing-sf-input w-full" />
              </div>
            </div>
          </div>
        </div>
        <button type="button" class="mt-2 text-sm text-indigo-600 hover:underline" @click="addLine">
          {{ t('invoicing.add_line') }}
        </button>
      </section>

      <section class="grid md:grid-cols-2 gap-6">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.note_footer') }}</label>
          <textarea v-model="form.note_footer" rows="3" class="invoicing-sf-input" />
        </div>
        <div class="rounded-lg bg-sky-50 border border-sky-200 p-4 text-right">
          <span class="text-sm text-gray-600">{{ t('invoicing.grand_total') }}</span>
          <div class="text-2xl font-bold">
            {{ previewTotals.total.toLocaleString(locale, { minimumFractionDigits: 2 }) }} {{ form.currency }}
          </div>
        </div>
      </section>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.tags') }}</label>
        <input v-model="tagsInput" type="text" class="invoicing-sf-input" :placeholder="t('invoicing.tags_placeholder')" />
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="invoicing-btn-primary px-8 py-3" :disabled="saving">
          {{ saveLabel }}
        </button>
        <button
          v-if="!isNew"
          type="button"
          class="invoicing-btn-secondary"
          :disabled="saving"
          @click="generateNow"
        >
          {{ t('invoicing.recurring_generate_now') }}
        </button>
        <button
          v-if="!isNew"
          type="button"
          class="text-red-600 text-sm hover:underline"
          :disabled="saving"
          @click="remove"
        >
          {{ t('invoicing.action_delete') }}
        </button>
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
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import ContactCreateModal from '../../components/invoicing/ContactCreateModal.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoiceLineUnitSelect from '../../components/invoicing/InvoiceLineUnitSelect.vue';
import StockLineSuggestField from '../../components/invoicing/StockLineSuggestField.vue';
import StockWarehouseSelect from '../../components/invoicing/StockWarehouseSelect.vue';
import { defaultWarehouseId, type WarehouseRow } from '../../composables/useCompanyWarehouse';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { DEFAULT_INVOICE_LINE_UNIT } from '../../composables/useInvoiceLineUnits';
import api from '../../services/api';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { appSettingsFromCompany } from '../../composables/useCompanyAppSettings';
import { companyCurrencyOptions } from '../../config/companyCurrencies';
import {
  RECURRING_INVOICE_NUMBER_TOKEN,
  RECURRING_PLACEHOLDER_TOKENS,
} from '../../composables/useInvoicingPlaceholders';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();

const profileId = computed(() => route.params.profileId as string | undefined);
const isNew = computed(() => !profileId.value);

const saving = ref(false);
const error = ref('');
const company = ref<Record<string, any> | null>(null);
const contacts = ref<any[]>([]);
const linkedStores = ref<any[]>([]);
const profileCurrencyOptions = computed(() =>
  companyCurrencyOptions(form.currency || company.value?.default_currency)
);
const showLineSuggester = computed(() => appSettingsFromCompany(company.value).show_line_suggester);
const tagsInput = ref('');
const warehouses = ref<WarehouseRow[]>([]);
const defaultWarehouseIdValue = computed(() => defaultWarehouseId(warehouses.value));

type RecurringLineForm = {
  name: string;
  description: string;
  quantity: number;
  unit: string;
  unit_price: number;
  line_discount_percent: number;
  tax_rate: number;
  company_stock_item_id?: string | null;
  company_warehouse_id?: string | null;
  stock_quantity_hint?: number | null;
  stock_quantities_by_warehouse?: Record<string, number>;
  warehouse_deduct_on_issue?: boolean | null;
};

const form = reactive({
  document_type: 'invoice' as 'invoice' | 'proforma',
  company_contact_id: '',
  store_id: '',
  is_active: true,
  recurrence_interval: 'yearly',
  first_issue_date: new Date().toISOString().slice(0, 10),
  next_issue_date: new Date().toISOString().slice(0, 10),
  repeat_indefinitely: true,
  issue_last_day_of_month: false,
  title: `Faktúra ${RECURRING_INVOICE_NUMBER_TOKEN}`,
  variable_symbol: RECURRING_INVOICE_NUMBER_TOKEN,
  constant_symbol: '',
  payment_terms_days: 14,
  delivery_date_mode: 'on_issue',
  currency: 'EUR',
  discount_percent: 0,
  note_above_lines: '',
  note_footer: '',
  internal_note: '',
  pdf_locale: 'sk',
  pdf_show_signature: true,
  pdf_show_payment_info: true,
  payment_btc_enabled: true,
  payment_bank_enabled: true,
  send_email_after_issue: false,
  lines: [
    {
      name: '',
      description: '',
      quantity: 1,
      unit: DEFAULT_INVOICE_LINE_UNIT,
      unit_price: 0,
      line_discount_percent: 0,
      tax_rate: 23,
      company_stock_item_id: null as string | null,
      company_warehouse_id: null as string | null,
      stock_quantity_hint: null as number | null,
      stock_quantities_by_warehouse: {},
      warehouse_deduct_on_issue: null,
    },
  ],
});

const showContactCreateModal = ref(false);

function onContactCreated(contact: Record<string, unknown>) {
  const id = contact.id as string;
  if (!id) return;
  contacts.value = [...contacts.value, contact];
  form.company_contact_id = id;
}

const defaultVat = computed(() => Number(company.value?.vat_rate_default ?? 23));

const saveLabel = computed(() =>
  form.document_type === 'proforma'
    ? t('invoicing.recurring_save_proforma')
    : t('invoicing.recurring_save_invoice')
);

const recurringPlaceholderTokens = RECURRING_PLACEHOLDER_TOKENS.join(' ');

function onDocumentTypeChange() {
  if (form.document_type === 'proforma') {
    form.title = `Zálohová faktúra ${RECURRING_INVOICE_NUMBER_TOKEN}`;
  } else {
    form.title = `Faktúra ${RECURRING_INVOICE_NUMBER_TOKEN}`;
  }
}

function onLineStockPick(
  line: RecurringLineForm,
  payload: {
    name: string;
    description: string;
    unit: string;
    unit_price: number;
    company_stock_item_id: string;
    quantity_on_hand: number | null;
    quantities_by_warehouse?: Record<string, number>;
    deduct_on_issue?: boolean | null;
  }
) {
  line.name = payload.name;
  line.description = payload.description;
  line.unit = payload.unit;
  line.unit_price = payload.unit_price;
  line.company_stock_item_id = payload.company_stock_item_id;
  line.stock_quantities_by_warehouse = payload.quantities_by_warehouse ?? {};
  if (!line.company_warehouse_id) {
    line.company_warehouse_id = defaultWarehouseIdValue.value || null;
  }
  syncLineStockHint(line);
}

function onLineWarehouseChange(line: RecurringLineForm) {
  syncLineStockHint(line);
}

function syncLineStockHint(line: RecurringLineForm) {
  const warehouseId = line.company_warehouse_id;
  if (!warehouseId) {
    line.stock_quantity_hint = null;
    line.warehouse_deduct_on_issue = null;
    return;
  }
  const warehouse = warehouses.value.find((w) => w.id === warehouseId);
  line.warehouse_deduct_on_issue = warehouse?.deduct_on_issue ?? null;
  if (line.company_stock_item_id && line.stock_quantities_by_warehouse) {
    const qty = line.stock_quantities_by_warehouse[warehouseId];
    line.stock_quantity_hint = qty != null ? Number(qty) : null;
  }
}

function clearLineStockLink(line: RecurringLineForm) {
  line.company_stock_item_id = null;
  line.stock_quantity_hint = null;
  line.stock_quantities_by_warehouse = {};
}

function lineTotal(line: (typeof form.lines)[0]) {
  const qty = Number(line.quantity) || 0;
  const price = Number(line.unit_price) || 0;
  const disc = Number(line.line_discount_percent) || 0;
  const tax = company.value?.vat_payer ? Number(line.tax_rate) || 0 : 0;
  const net = qty * price * (1 - disc / 100);
  return net + net * (tax / 100);
}

const previewTotals = computed(() => {
  let subtotal = 0;
  let tax = 0;
  form.lines.forEach((line) => {
    const qty = Number(line.quantity) || 0;
    const price = Number(line.unit_price) || 0;
    const disc = Number(line.line_discount_percent) || 0;
    const rate = company.value?.vat_payer ? Number(line.tax_rate) || 0 : 0;
    const net = qty * price * (1 - disc / 100);
    const lineTax = company.value?.vat_payer ? net * (rate / 100) : 0;
    subtotal += net;
    tax += lineTax;
  });
  const gross = subtotal + tax;
  const total = gross * (1 - (Number(form.discount_percent) || 0) / 100);
  return { subtotal, tax, total };
});

function addLine() {
  form.lines.push({
    name: '',
    description: '',
    quantity: 1,
    unit: DEFAULT_INVOICE_LINE_UNIT,
    unit_price: 0,
    line_discount_percent: 0,
    tax_rate: defaultVat.value,
    company_stock_item_id: null,
    company_warehouse_id: defaultWarehouseIdValue.value || null,
    stock_quantity_hint: null,
    stock_quantities_by_warehouse: {},
    warehouse_deduct_on_issue: null,
  });
}

function applyPaymentDefaults() {
  if (linkedStores.value.length && !form.store_id) {
    form.store_id = linkedStores.value[0].id;
  }
}

function payload() {
  return {
    ...form,
    tags: tagsInput.value
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean),
    lines: form.lines.map((l) => ({
      name: l.name,
      description: l.description || null,
      quantity: l.quantity,
      unit: l.unit,
      unit_price: l.unit_price,
      line_discount_percent: l.line_discount_percent || 0,
      tax_rate: l.tax_rate ?? defaultVat.value,
      company_stock_item_id: l.company_stock_item_id ?? null,
      company_warehouse_id: l.company_warehouse_id ?? null,
    })),
  };
}

async function loadCompany() {
  const res = await api.get(`/invoicing/companies/${companyId.value}`);
  company.value = res.data.data;
  linkedStores.value = company.value?.stores ?? [];
  contacts.value = (
    await api.get(`/invoicing/companies/${companyId.value}/contacts`)
  ).data.data ?? [];
  warehouses.value = (
    await api.get(`/invoicing/companies/${companyId.value}/warehouses`)
  ).data.data?.filter((w: WarehouseRow) => w.is_active) ?? [];
  const app = appSettingsFromCompany(company.value);
  if (!form.constant_symbol) form.constant_symbol = app.default_constant_symbol;
  if (!form.note_footer) form.note_footer = company.value?.legal_footer_note || '';
  applyPaymentDefaults();
}

async function loadProfile() {
  const res = await api.get(
    `/invoicing/companies/${companyId.value}/recurring-profiles/${profileId.value}`
  );
  const d = res.data.data;
  Object.assign(form, {
    document_type: d.document_type,
    company_contact_id: d.company_contact_id || '',
    store_id: d.store_id || '',
    is_active: d.is_active,
    recurrence_interval: d.recurrence_interval,
    first_issue_date: d.first_issue_date?.slice(0, 10),
    next_issue_date: d.next_issue_date?.slice(0, 10),
    repeat_indefinitely: d.repeat_indefinitely,
    issue_last_day_of_month: d.issue_last_day_of_month,
    title: d.title || '',
    variable_symbol: d.variable_symbol || '',
    constant_symbol: d.constant_symbol || '',
    payment_terms_days: d.payment_terms_days ?? 14,
    delivery_date_mode: d.delivery_date_mode || 'on_issue',
    currency: d.currency || 'EUR',
    discount_percent: parseFloat(d.discount_percent) || 0,
    note_above_lines: d.note_above_lines || '',
    note_footer: d.note_footer || '',
    internal_note: d.internal_note || '',
    pdf_locale: d.pdf_locale || 'sk',
    pdf_show_signature: d.pdf_show_signature ?? true,
    pdf_show_payment_info: d.pdf_show_payment_info ?? true,
    payment_btc_enabled: d.payment_btc_enabled ?? false,
    payment_bank_enabled: d.payment_bank_enabled ?? true,
    send_email_after_issue: d.send_email_after_issue ?? false,
    lines: (d.lines || []).map((l: any) => ({
      name: l.name,
      description: l.description || '',
      quantity: parseFloat(l.quantity),
      unit: l.unit || DEFAULT_INVOICE_LINE_UNIT,
      unit_price: parseFloat(l.unit_price),
      line_discount_percent: parseFloat(l.line_discount_percent) || 0,
      tax_rate: parseFloat(l.tax_rate) || defaultVat.value,
      company_stock_item_id: l.company_stock_item_id ?? null,
      company_warehouse_id: l.company_warehouse_id ?? null,
    })),
  });
  tagsInput.value = (d.tags || []).join(', ');
  if (form.lines.length === 0) addLine();
}

async function save() {
  saving.value = true;
  error.value = '';
  try {
    if (isNew.value) {
      const res = await api.post(
        `/invoicing/companies/${companyId.value}/recurring-profiles`,
        payload()
      );
      router.push({
        name: 'invoicing-recurring-edit',
        params: { companyId: companyId.value, profileId: res.data.data.id },
      });
    } else {
      await api.patch(
        `/invoicing/companies/${companyId.value}/recurring-profiles/${profileId.value}`,
        payload()
      );
      await loadProfile();
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    saving.value = false;
  }
}

async function generateNow() {
  if (!profileId.value || !window.confirm(t('invoicing.recurring_generate_confirm'))) return;
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/recurring-profiles/${profileId.value}/generate`
    );
    const doc = res.data.data.document;
    const routeName =
      doc.type === 'proforma' ? 'invoicing-proforma-show' : 'invoicing-invoice-show';
    router.push({
      name: routeName,
      params: { companyId: companyId.value, documentId: doc.id },
    });
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (!profileId.value || !window.confirm(t('invoicing.confirm_delete'))) return;
  await api.delete(
    `/invoicing/companies/${companyId.value}/recurring-profiles/${profileId.value}`
  );
  router.push({ name: 'invoicing-recurring', params: { companyId: companyId.value } });
}

onMounted(async () => {
  rememberCompany(companyId.value);
  await loadCompany();
  if (profileId.value) {
    await loadProfile();
  }
});
</script>
