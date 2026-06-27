import type { Evolu } from "@evolu/common/local-first";
import { allCompaniesQuery, allDocumentsQuery } from "./client";
import { countCompanyInvoicesForList } from "./companyInvoiceCount";
import type { CompanyId, InvoicingLocalSchema } from "./schema";
import { snapshotInvoicingData } from "./invoicingSnapshot";

const RELAY_PUSH_AT_KEY = "satfluxRelayPushAt";
const FORCE_PUSH_AT_KEY = "satfluxForceRelayPushAt";

export type InvoicingCompanyLocalStat = {
    id: string;
    name: string;
    invoices: number;
    documents: number;
    contacts: number;
};

export type InvoicingLocalStats = {
    ownerId: string;
    ownerIdShort: string;
    counts: {
        company: number;
        contact: number;
        document: number;
        documentByType: Record<string, number>;
        expense: number;
        documentLine: number;
        documentEvent: number;
        expenseAttachment: number;
        recurringProfile: number;
        numberSeries: number;
    };
    companies: InvoicingCompanyLocalStat[];
    lastRelayPushAt: number | null;
    lastForceRelayPushAt: number | null;
    localDbBytes: number | null;
    relayError: string | null;
};

function readJsonTimestamp(raw: unknown, key: string): number | null {
    if (raw == null || raw === "") {
        return null;
    }
    try {
        const parsed = JSON.parse(String(raw)) as Record<string, unknown>;
        const value = parsed[key];
        if (typeof value === "number" && Number.isFinite(value)) {
            return value;
        }
    } catch {
        return null;
    }
    return null;
}

function maxTimestamp(current: number | null, next: number | null): number | null {
    if (next == null) {
        return current;
    }
    if (current == null) {
        return next;
    }
    return Math.max(current, next);
}

export function formatByteSize(bytes: number | null): string {
    if (bytes == null || !Number.isFinite(bytes)) {
        return "—";
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function formatRelayError(error: unknown): string | null {
    if (error == null) {
        return null;
    }
    if (typeof error === "object" && error !== null && "type" in error) {
        return String((error as { type: string }).type);
    }
    return String(error);
}

export async function loadInvoicingLocalStats(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: { includeDbExport?: boolean },
): Promise<InvoicingLocalStats> {
    const owner = await evolu.appOwner;
    const snapshot = await snapshotInvoicingData(evolu);
    const companies = await evolu.loadQuery(allCompaniesQuery);
    const documents = await evolu.loadQuery(allDocumentsQuery);

    const documentByType: Record<string, number> = {};
    for (const doc of documents) {
        const type = String(doc.documentType ?? "unknown");
        documentByType[type] = (documentByType[type] ?? 0) + 1;
    }

    let lastRelayPushAt: number | null = null;
    let lastForceRelayPushAt: number | null = null;
    for (const row of snapshot.company) {
        lastRelayPushAt = maxTimestamp(
            lastRelayPushAt,
            readJsonTimestamp(row.appSettingsJson, RELAY_PUSH_AT_KEY),
        );
        lastForceRelayPushAt = maxTimestamp(
            lastForceRelayPushAt,
            readJsonTimestamp(row.appSettingsJson, FORCE_PUSH_AT_KEY),
        );
    }

    const companyStats: InvoicingCompanyLocalStat[] = companies.map((row) => {
        const id = String(row.id) as CompanyId;
        const docsForCompany = snapshot.document.filter((d) => d.companyId === id);
        return {
            id,
            name: String(row.tradeName || row.legalName || id),
            invoices: countCompanyInvoicesForList(documents, id),
            documents: docsForCompany.length,
            contacts: snapshot.contact.filter((c) => c.companyId === id).length,
        };
    });

    companyStats.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: "base" }));

    let localDbBytes: number | null = null;
    if (options?.includeDbExport) {
        try {
            const exported = await evolu.exportDatabase();
            localDbBytes = exported.byteLength;
        } catch {
            localDbBytes = null;
        }
    }

    return {
        ownerId: String(owner.id),
        ownerIdShort: String(owner.id).slice(0, 12),
        counts: {
            company: snapshot.company.length,
            contact: snapshot.contact.length,
            document: snapshot.document.length,
            documentByType,
            expense: snapshot.expense.length,
            documentLine: snapshot.documentLine.length,
            documentEvent: snapshot.documentEvent.length,
            expenseAttachment: snapshot.expenseAttachment.length,
            recurringProfile: snapshot.recurringProfile.length,
            numberSeries: snapshot.numberSeries.length,
        },
        companies: companyStats,
        lastRelayPushAt,
        lastForceRelayPushAt,
        localDbBytes,
        relayError: formatRelayError(evolu.getError()),
    };
}
