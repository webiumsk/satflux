import { createIdFromString, maxLength, NonEmptyString } from "@evolu/common";
import type { InvoicingDataSnapshot } from "./invoicingSnapshot";
import { stableCompanyIdFromIdentity } from "./companyStableId";
import { normalizeIsoCountryCode } from "@/utils/isoCountryCode";
import {
    BankImportBatchId,
    BankTransactionId,
    BankTransactionMatchId,
    CompanyId,
    ContactId,
    DocumentEventId,
    DocumentId,
    DocumentLineId,
    ExpenseAttachmentId,
    ExpenseId,
    NumberSeriesId,
    RecurringProfileId,
    RecurringProfileLineId,
    StockBalanceId,
    StockItemId,
    StockMovementId,
    WarehouseId,
} from "./schema";

const Opt4000 = maxLength(4000)(NonEmptyString);
const TitleType = maxLength(1000)(NonEmptyString);

type BrandedIdType = {
    from: (value: string) => { ok: boolean; value?: string };
};

const SERVER_UUID_RE =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function isServerUuid(value: unknown): value is string {
    return typeof value === "string" && SERVER_UUID_RE.test(value.trim());
}

/** Deterministic Evolu row id for a server PostgreSQL UUID (stable across devices). */
function evoluIdFromServerUuid(idType: BrandedIdType, serverUuid: string): string | null {
    const normalized = serverUuid.trim().toLowerCase();
    const stable = createIdFromString(`satflux.server-migrate.${normalized}`);
    const parsed = idType.from(stable);
    return parsed.ok ? (parsed.value as string) : null;
}

function mapUuidField(
    idType: BrandedIdType,
    value: unknown,
): string | null | undefined {
    if (value == null || value === "") {
        return null;
    }
    if (!isServerUuid(value)) {
        return typeof value === "string" ? value : null;
    }
    return evoluIdFromServerUuid(idType, value);
}

function emptyToNull(value: unknown): string | null {
    if (value == null) return null;
    const trimmed = String(value).trim();
    return trimmed === "" ? null : trimmed;
}

function truncateJsonBlob(value: unknown, maxLen = 4000): string | null {
    if (value == null || value === "") return null;
    const raw = typeof value === "string" ? value : JSON.stringify(value);
    if (!raw || raw === "null") return null;
    if (raw.length <= maxLen) return raw;
    const parsed = Opt4000.from(raw.slice(0, maxLen));
    return parsed.ok ? parsed.value : raw.slice(0, maxLen);
}

function normalizeCountry(value: unknown): string | null {
    return normalizeIsoCountryCode(typeof value === "string" ? value : null);
}

const JURISDICTIONS = new Set([
    "eu_sk",
    "eu_cz",
    "eu_other",
    "us",
    "uk",
    "offshore",
    "asia",
]);

function normalizeJurisdiction(value: unknown): string {
    const text = emptyToNull(value);
    if (text && JURISDICTIONS.has(text)) {
        return text;
    }
    return "eu_sk";
}

const VAT_STATUSES = new Set(["none", "payer", "partial"]);

function normalizeVatStatus(value: unknown): string {
    const text = emptyToNull(value);
    if (text && VAT_STATUSES.has(text)) {
        return text;
    }
    return "none";
}

function normalizeDocumentTitle(row: Record<string, unknown>): string {
    const candidates = [row.title, row.number, row.documentType, "Document"];
    for (const candidate of candidates) {
        const text = emptyToNull(candidate);
        if (!text) continue;
        const parsed = TitleType.from(text);
        if (parsed.ok) return parsed.value;
    }
    return "Document";
}

function normalizeRecurringDocumentType(value: unknown): "invoice" | "proforma" {
    const text = emptyToNull(value);
    return text === "proforma" ? "proforma" : "invoice";
}

function mapRowIds(
    row: Record<string, unknown>,
    spec: {
        id?: BrandedIdType;
        fields?: Record<string, BrandedIdType>;
    },
): Record<string, unknown> {
    const out = { ...row };
    if (spec.id && isServerUuid(out.id)) {
        const mapped = evoluIdFromServerUuid(spec.id, out.id as string);
        if (mapped) out.id = mapped;
    }
    for (const [field, idType] of Object.entries(spec.fields ?? {})) {
        if (!(field in out)) continue;
        out[field] = mapUuidField(idType, out[field]);
    }
    return out;
}

function buildCompanyIdByServerUuid(
    companies: InvoicingDataSnapshot["company"],
): Map<string, string> {
    const map = new Map<string, string>();
    for (const row of companies) {
        if (!isServerUuid(row.id)) {
            continue;
        }
        const legalName = emptyToNull(row.legalName) ?? "";
        const reg = emptyToNull(row.registrationNumber);
        const stableId =
            stableCompanyIdFromIdentity(legalName, reg)
            ?? evoluIdFromServerUuid(CompanyId, row.id);
        if (stableId) {
            map.set(row.id.trim().toLowerCase(), stableId);
        }
    }
    return map;
}

function remapCompanyFk(
    value: unknown,
    companyIdByServerUuid: Map<string, string>,
): string | null | undefined {
    if (value == null || value === "") {
        return null;
    }
    if (typeof value === "string" && isServerUuid(value)) {
        return companyIdByServerUuid.get(value.trim().toLowerCase())
            ?? mapUuidField(CompanyId, value)
            ?? null;
    }
    return typeof value === "string" ? value : null;
}

function withCompanyFk(
    mapped: Record<string, unknown>,
    companyIdByServerUuid: Map<string, string>,
): Record<string, unknown> {
    if ("companyId" in mapped) {
        mapped.companyId = remapCompanyFk(mapped.companyId, companyIdByServerUuid);
    }
    return mapped;
}

/**
 * Server export uses Laravel UUID strings; Evolu requires branded Base64Url ids.
 * Remap ids + FKs and coerce values to match local schema validators.
 */
export function prepareServerSnapshotForEvolu(snapshot: InvoicingDataSnapshot): InvoicingDataSnapshot {
    const companyIdByServerUuid = buildCompanyIdByServerUuid(snapshot.company);

    return {
        company: snapshot.company.map((row) => {
            const mapped = mapRowIds(row, { id: CompanyId });
            const legalName = emptyToNull(mapped.legalName) ?? "";
            const reg = emptyToNull(mapped.registrationNumber);
            const stableId = stableCompanyIdFromIdentity(legalName, reg);
            if (stableId) {
                mapped.id = stableId;
            }
            return {
                ...mapped,
                jurisdiction: normalizeJurisdiction(mapped.jurisdiction),
                vatStatus: normalizeVatStatus(mapped.vatStatus),
                country: normalizeCountry(mapped.country),
                appSettingsJson: truncateJsonBlob(mapped.appSettingsJson),
                emailSettingsJson: truncateJsonBlob(mapped.emailSettingsJson),
            };
        }),
        contact: snapshot.contact.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: ContactId,
                    fields: { companyId: CompanyId },
                }),
                companyIdByServerUuid,
            ),
        ),
        numberSeries: snapshot.numberSeries.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: NumberSeriesId,
                    fields: { companyId: CompanyId },
                }),
                companyIdByServerUuid,
            ),
        ),
        document: snapshot.document.map((row) => {
            const mapped = withCompanyFk(
                mapRowIds(row, {
                    id: DocumentId,
                    fields: {
                        companyId: CompanyId,
                        contactId: ContactId,
                        sourceDocumentId: DocumentId,
                    },
                }),
                companyIdByServerUuid,
            );
            return {
                ...mapped,
                title: normalizeDocumentTitle(mapped),
            };
        }),
        documentLine: snapshot.documentLine.map((row) =>
            mapRowIds(row, {
                id: DocumentLineId,
                fields: {
                    documentId: DocumentId,
                    companyStockItemId: StockItemId,
                    companyWarehouseId: WarehouseId,
                },
            }),
        ),
        documentEvent: snapshot.documentEvent.map((row) =>
            mapRowIds(row, {
                id: DocumentEventId,
                fields: { documentId: DocumentId },
            }),
        ),
        expense: snapshot.expense.map((row) => {
            const mapped = withCompanyFk(
                mapRowIds(row, {
                    id: ExpenseId,
                    fields: { companyId: CompanyId },
                }),
                companyIdByServerUuid,
            );
            const internalNumber = emptyToNull(mapped.internalNumber) ?? "1";
            return { ...mapped, internalNumber };
        }),
        expenseAttachment: snapshot.expenseAttachment.map((row) =>
            mapRowIds(row, {
                id: ExpenseAttachmentId,
                fields: { expenseId: ExpenseId },
            }),
        ),
        recurringProfile: snapshot.recurringProfile.map((row) => {
            const mapped = withCompanyFk(
                mapRowIds(row, {
                    id: RecurringProfileId,
                    fields: {
                        companyId: CompanyId,
                        contactId: ContactId,
                        lastGeneratedDocumentId: DocumentId,
                    },
                }),
                companyIdByServerUuid,
            );
            return {
                ...mapped,
                documentType: normalizeRecurringDocumentType(mapped.documentType),
            };
        }),
        recurringProfileLine: snapshot.recurringProfileLine.map((row) =>
            mapRowIds(row, {
                id: RecurringProfileLineId,
                fields: { recurringProfileId: RecurringProfileId },
            }),
        ),
        companyWarehouse: snapshot.companyWarehouse.map((row) => {
            const mapped = withCompanyFk(
                mapRowIds(row, {
                    id: WarehouseId,
                    fields: {
                        companyId: CompanyId,
                        companyContactId: ContactId,
                    },
                }),
                companyIdByServerUuid,
            );
            return { ...mapped, country: normalizeCountry(mapped.country) };
        }),
        companyStockItem: snapshot.companyStockItem.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: StockItemId,
                    fields: { companyId: CompanyId },
                }),
                companyIdByServerUuid,
            ),
        ),
        companyStockBalance: snapshot.companyStockBalance.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: StockBalanceId,
                    fields: {
                        companyId: CompanyId,
                        companyWarehouseId: WarehouseId,
                        companyStockItemId: StockItemId,
                    },
                }),
                companyIdByServerUuid,
            ),
        ),
        companyStockMovement: snapshot.companyStockMovement.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: StockMovementId,
                    fields: {
                        companyId: CompanyId,
                        companyStockItemId: StockItemId,
                        companyWarehouseId: WarehouseId,
                        businessDocumentId: DocumentId,
                    },
                }),
                companyIdByServerUuid,
            ),
        ),
        bankImportBatch: snapshot.bankImportBatch.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: BankImportBatchId,
                    fields: { companyId: CompanyId },
                }),
                companyIdByServerUuid,
            ),
        ),
        bankTransaction: snapshot.bankTransaction.map((row) =>
            withCompanyFk(
                mapRowIds(row, {
                    id: BankTransactionId,
                    fields: {
                        companyId: CompanyId,
                        bankImportBatchId: BankImportBatchId,
                        businessExpenseId: ExpenseId,
                    },
                }),
                companyIdByServerUuid,
            ),
        ),
        bankTransactionMatch: snapshot.bankTransactionMatch.map((row) =>
            mapRowIds(row, {
                id: BankTransactionMatchId,
                fields: {
                    bankTransactionId: BankTransactionId,
                    businessDocumentId: DocumentId,
                },
            }),
        ),
    };
}
