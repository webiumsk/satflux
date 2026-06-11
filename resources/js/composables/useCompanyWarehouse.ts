import { computed } from 'vue';
import { useRoute } from 'vue-router';

export type CompanyWarehouseType = 'own' | 'owned_external' | 'supplier_availability';

export type WarehouseRow = {
  id: string;
  name: string;
  type: CompanyWarehouseType;
  deduct_on_issue: boolean;
  is_default: boolean;
  is_active: boolean;
  company_contact_id?: string | null;
  contact_name?: string | null;
  street?: string | null;
  city?: string | null;
  postal_code?: string | null;
  country?: string | null;
  notes?: string | null;
};

export type StockBalanceRow = {
  warehouse_id: string;
  warehouse_name?: string;
  warehouse_type?: CompanyWarehouseType;
  deduct_on_issue?: boolean;
  quantity_on_hand: number | string;
};

export type WarehouseFormState = {
  name: string;
  type: CompanyWarehouseType;
  is_default: boolean;
  is_active: boolean;
  company_contact_id: string;
  street: string;
  city: string;
  postal_code: string;
  country: string;
  notes: string;
};

export function emptyWarehouseForm(): WarehouseFormState {
  return {
    name: '',
    type: 'own',
    is_default: false,
    is_active: true,
    company_contact_id: '',
    street: '',
    city: '',
    postal_code: '',
    country: '',
    notes: '',
  };
}

export function warehouseToForm(row: WarehouseRow): WarehouseFormState {
  return {
    name: row.name ?? '',
    type: row.type ?? 'own',
    is_default: row.is_default ?? false,
    is_active: row.is_active ?? true,
    company_contact_id: row.company_contact_id ?? '',
    street: row.street ?? '',
    city: row.city ?? '',
    postal_code: row.postal_code ?? '',
    country: row.country ?? '',
    notes: row.notes ?? '',
  };
}

export function formToWarehousePayload(form: WarehouseFormState) {
  return {
    name: form.name.trim(),
    type: form.type,
    is_default: form.is_default,
    is_active: form.is_active,
    company_contact_id: form.company_contact_id.trim() || null,
    street: form.street.trim() || null,
    city: form.city.trim() || null,
    postal_code: form.postal_code.trim() || null,
    country: form.country.trim().toUpperCase() || null,
    notes: form.notes.trim() || null,
  };
}

export function warehouseTypeLabelKey(type: CompanyWarehouseType): string {
  return `invoicing.warehouse_type_${type}`;
}

export function useWarehouseRoutes(companyId: { value: string }) {
  const listTo = () => ({
    name: 'invoicing-warehouses',
    params: { companyId: companyId.value },
  });

  const newTo = () => ({
    name: 'invoicing-warehouse-new',
    params: { companyId: companyId.value },
  });

  const editTo = (warehouseId: string) => ({
    name: 'invoicing-warehouse-edit',
    params: { companyId: companyId.value, warehouseId },
  });

  return {
    listTo,
    newTo,
    editTo,
    warehouseListTo: listTo,
    warehouseNewTo: newTo,
    warehouseEditTo: editTo,
  };
}

export function useWarehousePage() {
  const route = useRoute();
  const companyId = computed(() => route.params.companyId as string);
  const warehouseId = computed(() => route.params.warehouseId as string | undefined);
  const isNew = computed(() => !warehouseId.value || route.name === 'invoicing-warehouse-new');

  return { companyId, warehouseId, isNew };
}

export function defaultWarehouseId(warehouses: WarehouseRow[]): string {
  const active = warehouses.filter((w) => w.is_active);
  return active.find((w) => w.is_default)?.id ?? active[0]?.id ?? '';
}
