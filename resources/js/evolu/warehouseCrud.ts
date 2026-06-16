import { booleanToSqliteBoolean, maxLength, NonEmptyString, sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import {
    evoluWarehouseToApi,
    type EvoluWarehouseRow,
    type WarehouseSavePayload,
    warehouseTypeDeductOnIssue,
} from "./warehouseMap";
import type { CompanyId, ContactId, InvoicingLocalSchema, WarehouseId } from "./schema";

const NameType = maxLength(255)(NonEmptyString);
const Opt32 = maxLength(32)(NonEmptyString);
const Opt128 = maxLength(128)(NonEmptyString);
const Opt255 = maxLength(255)(NonEmptyString);
const Opt4000 = maxLength(4000)(NonEmptyString);
const CountryType = maxLength(2)(NonEmptyString);

export const DEFAULT_LOCAL_WAREHOUSE_NAME = "Hlavný sklad";

function parseOpt(value: string | null | undefined, type: ReturnType<ReturnType<typeof maxLength>>) {
    if (value == null || value.trim() === "") return { ok: true as const, value: null };
    return type.from(value.trim());
}

function clearDefaultFlag(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: EvoluWarehouseRow[],
    exceptId?: WarehouseId,
) {
    for (const row of rows) {
        if (row.companyId !== companyId || row.id === exceptId) continue;
        if (row.isDefault === 1) {
            evolu.update("companyWarehouse", { id: row.id, isDefault: booleanToSqliteBoolean(false) });
        }
    }
}

export function ensureDefaultLocalWarehouse(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: EvoluWarehouseRow[],
    name = DEFAULT_LOCAL_WAREHOUSE_NAME,
) {
    const companyRows = rows.filter((row) => row.companyId === companyId);
    const existingDefault = companyRows.find((row) => row.isDefault === 1);
    if (existingDefault) return { ok: true as const, value: existingDefault.id };

    if (companyRows.length > 0) {
        const first = companyRows.sort((a, b) => a.name.localeCompare(b.name))[0];
        evolu.update("companyWarehouse", { id: first.id, isDefault: booleanToSqliteBoolean(true) });
        return { ok: true as const, value: first.id };
    }

    const result = evolu.insert("companyWarehouse", {
        companyId,
        name,
        type: "own",
        deductOnIssue: booleanToSqliteBoolean(true),
        isDefault: booleanToSqliteBoolean(true),
        isActive: booleanToSqliteBoolean(true),
        companyContactId: null,
        street: null,
        city: null,
        postalCode: null,
        country: null,
        notes: null,
    });
    if (!result.ok) return result;
    return { ok: true as const, value: result.value.id };
}

export function saveLocalWarehouse(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: WarehouseSavePayload,
    options: {
        warehouseId?: WarehouseId;
        existingRows: EvoluWarehouseRow[];
    },
) {
    const name = NameType.from(payload.name.trim());
    if (!name.ok) return name;

    const street = parseOpt(payload.street, Opt255);
    if (!street.ok) return street;
    const city = parseOpt(payload.city, Opt128);
    if (!city.ok) return city;
    const postalCode = parseOpt(payload.postal_code, Opt32);
    if (!postalCode.ok) return postalCode;
    const country = parseOpt(payload.country, CountryType);
    if (!country.ok) return country;
    const notes = parseOpt(payload.notes, Opt4000);
    if (!notes.ok) return notes;

    const companyRows = options.existingRows.filter((row) => row.companyId === companyId);
    const isFirst = companyRows.length === 0 && !options.warehouseId;
    const isDefault = payload.is_default || isFirst;

    if (isDefault) {
        clearDefaultFlag(evolu, companyId, options.existingRows, options.warehouseId);
    }

    const contactId = payload.company_contact_id ? (payload.company_contact_id as ContactId) : null;
    const fields = {
        companyId,
        name: name.value,
        type: payload.type,
        deductOnIssue: booleanToSqliteBoolean(warehouseTypeDeductOnIssue(payload.type)),
        isDefault: booleanToSqliteBoolean(isDefault),
        isActive: booleanToSqliteBoolean(payload.is_active),
        companyContactId: contactId,
        street: street.value,
        city: city.value,
        postalCode: postalCode.value,
        country: country.value,
        notes: notes.value,
    };

    if (options.warehouseId) {
        return evolu.update("companyWarehouse", { id: options.warehouseId, ...fields });
    }

    return evolu.insert("companyWarehouse", fields);
}

export function deleteLocalWarehouse(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    warehouseId: WarehouseId,
    options: {
        warehouseRows: EvoluWarehouseRow[];
        balanceRows: Array<{ companyWarehouseId: string; quantityOnHand: string | null }>;
    },
) {
    const warehouse = options.warehouseRows.find((row) => row.id === warehouseId);
    if (!warehouse || warehouse.companyId !== companyId) {
        return { ok: false as const, error: "not_found" };
    }

    const hasStock = options.balanceRows.some((row) => {
        if (row.companyWarehouseId !== warehouseId) return false;
        const qty = parseFloat(row.quantityOnHand || "0") || 0;
        return qty !== 0;
    });
    if (hasStock) {
        return { ok: false as const, error: "has_stock" };
    }

    const wasDefault = warehouse.isDefault === 1;
    const result = evolu.update("companyWarehouse", { id: warehouseId, isDeleted: sqliteTrue });
    if (!result.ok) return result;

    if (wasDefault) {
        const next = options.warehouseRows
            .filter((row) => row.companyId === companyId && row.id !== warehouseId && row.isActive !== 0)
            .sort((a, b) => a.name.localeCompare(b.name))[0];
        if (next) {
            evolu.update("companyWarehouse", { id: next.id, isDefault: booleanToSqliteBoolean(true) });
        }
    }

    return { ok: true as const, value: { id: warehouseId } };
}

export function localWarehouseApi(
    warehouseId: WarehouseId,
    rows: EvoluWarehouseRow[],
    contactNameById: Map<string, string>,
) {
    const row = rows.find((entry) => entry.id === warehouseId);
    if (!row) return null;
    const contactName = row.companyContactId ? contactNameById.get(row.companyContactId) ?? null : null;
    return evoluWarehouseToApi(row, contactName);
}
