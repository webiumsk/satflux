import type { CompanyWarehouseType, WarehouseRow } from "@/composables/useCompanyWarehouse";
import type { CompanyId, ContactId, WarehouseId, WarehouseType } from "./schema";

export type EvoluWarehouseRow = {
    id: WarehouseId;
    companyId: CompanyId;
    name: string;
    type: WarehouseType;
    deductOnIssue: 0 | 1 | null;
    isDefault: 0 | 1 | null;
    isActive: 0 | 1 | null;
    companyContactId: ContactId | null;
    street: string | null;
    city: string | null;
    postalCode: string | null;
    country: string | null;
    notes: string | null;
};

function sqliteBool(value: 0 | 1 | null | undefined, fallback = false): boolean {
    if (value === 1) return true;
    if (value === 0) return false;
    return fallback;
}

export function warehouseTypeDeductOnIssue(type: WarehouseType): boolean {
    return type !== "supplier_availability";
}

export function filterCompanyWarehouses(
    rows: EvoluWarehouseRow[],
    companyId: CompanyId,
    activeOnly = false,
): EvoluWarehouseRow[] {
    return rows
        .filter((row) => row.companyId === companyId)
        .filter((row) => (activeOnly ? sqliteBool(row.isActive, true) : true))
        .sort((a, b) => a.name.localeCompare(b.name));
}

export function evoluWarehouseToApi(
    row: EvoluWarehouseRow,
    contactName?: string | null,
): WarehouseRow {
    return {
        id: row.id,
        name: row.name,
        type: row.type as CompanyWarehouseType,
        deduct_on_issue: sqliteBool(row.deductOnIssue, warehouseTypeDeductOnIssue(row.type)),
        is_default: sqliteBool(row.isDefault),
        is_active: sqliteBool(row.isActive, true),
        company_contact_id: row.companyContactId,
        contact_name: contactName ?? null,
        street: row.street,
        city: row.city,
        postal_code: row.postalCode,
        country: row.country,
        notes: row.notes,
    };
}

export type WarehouseSavePayload = {
    name: string;
    type: CompanyWarehouseType;
    is_default: boolean;
    is_active: boolean;
    company_contact_id: string | null;
    street: string | null;
    city: string | null;
    postal_code: string | null;
    country: string | null;
    notes: string | null;
};
