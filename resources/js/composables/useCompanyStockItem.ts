import { computed } from 'vue';
import { useRoute } from 'vue-router';

export type StockItemRow = {
  id: string;
  name: string;
  sku?: string | null;
  description?: string | null;
  unit: string;
  track_inventory: boolean;
  quantity_on_hand: number | string;
  purchase_unit_price?: number | string | null;
  purchase_currency?: string | null;
  sale_unit_price?: number | string | null;
  internal_note?: string | null;
  exclude_from_suggester: boolean;
};

export type StockItemMovementRow = {
  id: string;
  created_at?: string;
  quantity_after: number | string;
  quantity_delta: number | string;
  purchase_unit_price?: number | string | null;
  sale_unit_price?: number | string | null;
  note?: string | null;
  source: string;
  document_number?: string | null;
  document_type?: string | null;
  business_document_id?: string | null;
};

export type StockItemFormState = {
  name: string;
  sku: string;
  description: string;
  unit: string;
  track_inventory: boolean;
  quantity_on_hand: number;
  purchase_unit_price: number | null;
  purchase_currency: string;
  sale_unit_price: number | null;
  internal_note: string;
  exclude_from_suggester: boolean;
};

export type StockSummaryMeta = {
  item_count: number;
  purchase_value_total: number;
  sale_value_total: number;
  summary_currency: string;
};

export function emptyStockItemForm(defaultCurrency = 'EUR'): StockItemFormState {
  return {
    name: '',
    sku: '',
    description: '',
    unit: 'ks',
    track_inventory: true,
    quantity_on_hand: 0,
    purchase_unit_price: null,
    purchase_currency: defaultCurrency,
    sale_unit_price: null,
    internal_note: '',
    exclude_from_suggester: false,
  };
}

export function stockItemToForm(item: StockItemRow, defaultCurrency = 'EUR'): StockItemFormState {
  return {
    name: item.name ?? '',
    sku: item.sku ?? '',
    description: item.description ?? '',
    unit: item.unit ?? 'ks',
    track_inventory: item.track_inventory ?? true,
    quantity_on_hand: Number(item.quantity_on_hand ?? 0),
    purchase_unit_price: item.purchase_unit_price != null ? Number(item.purchase_unit_price) : null,
    purchase_currency: item.purchase_currency ?? defaultCurrency,
    sale_unit_price: item.sale_unit_price != null ? Number(item.sale_unit_price) : null,
    internal_note: item.internal_note ?? '',
    exclude_from_suggester: item.exclude_from_suggester ?? false,
  };
}

export function formToStockPayload(form: StockItemFormState) {
  return {
    name: form.name.trim(),
    sku: form.sku.trim() || null,
    description: form.description.trim() || null,
    unit: form.unit.trim() || 'ks',
    track_inventory: form.track_inventory,
    quantity_on_hand: form.quantity_on_hand,
    purchase_unit_price: form.purchase_unit_price,
    purchase_currency: form.purchase_currency || null,
    sale_unit_price: form.sale_unit_price,
    internal_note: form.internal_note.trim() || null,
    exclude_from_suggester: form.exclude_from_suggester,
  };
}

export function useStockRoutes(companyId: { value: string }) {
  const stockListTo = () => ({
    name: 'invoicing-stock',
    params: { companyId: companyId.value },
  });

  const stockNewTo = () => ({
    name: 'invoicing-stock-new',
    params: { companyId: companyId.value },
  });

  const stockEditTo = (itemId: string) => ({
    name: 'invoicing-stock-edit',
    params: { companyId: companyId.value, itemId },
  });

  return { stockListTo, stockNewTo, stockEditTo };
}

export function useStockItemPage() {
  const route = useRoute();
  const companyId = computed(() => route.params.companyId as string);
  const itemId = computed(() => route.params.itemId as string | undefined);
  const isNew = computed(() => !itemId.value || route.name === 'invoicing-stock-new');

  return { companyId, itemId, isNew };
}

export function formatStockQuantity(qty: number | string, unit: string): string {
  const n = Number(qty);
  const formatted = Number.isInteger(n) ? String(n) : n.toLocaleString(undefined, { maximumFractionDigits: 4 });
  const u = unit?.trim();
  return u ? `${formatted} ${u}` : formatted;
}

export function formatStockPrice(amount: number | string | null | undefined, currency?: string | null): string {
  if (amount == null || amount === '') return '—';
  const n = Number(amount);
  if (!Number.isFinite(n)) return '—';
  const formatted = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return currency ? `${formatted} ${currency}` : formatted;
}
