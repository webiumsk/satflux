import type { Evolu } from "@evolu/common/local-first";
import api from "@/services/api";
import { emptyContactForm, type ContactFormState } from "@/composables/useCompanyContact";
import type { VatPolicyCompany, VatPolicyContact } from "@/composables/useCompanyVatPolicy";
import { useCompanyVatPolicy } from "@/composables/useCompanyVatPolicy";
import { allContactsQuery, allDocumentLinesQuery, allDocumentsQuery, allCompaniesQuery } from "./client";
import { insertLocalContactFromForm, updateLocalContactFromForm } from "./contactCrud";
import type { EvoluContactRow } from "./contactMap";
import {
    saveLocalDocument,
    applyReservedNumberToLocalDocumentAsync,
    issueLocalDocumentAsync,
    markLocalDocumentPaidWithAmount,
    type DocumentLinePayload,
    type DocumentSavePayload,
} from "./documentCrud";
import type { CompanyId, ContactId, DocumentId, DocumentType, InvoicingLocalSchema } from "./schema";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluCompanyRow } from "./companyMap";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { allNumberSeriesQuery } from "./client";
import { defaultPdfLocaleForJurisdiction } from "@/config/jurisdictionRules";
import { resolveImportSettleActions } from "./integrationInboxPaid";
import { resolveIntegrationInboxBasePath } from "./integrationInboxPaths";
import {
    findLocalDocumentForInboxEntry,
    resolveLinkedSourceDocumentId,
    stableDocumentIdFromInboxUuid,
} from "./inboxDocumentStableId";
import { waitForInvoicingDataSettled } from "./relaySyncWait";

export { IntegrationInboxPathError } from "./integrationInboxPaths";

export type IntegrationInboxEntry = {
    inbox_id: string;
    evolu_document_id: string;
    woocommerce_order_id: number | null;
    status: string;
    created_at: string | null;
    payload: Record<string, unknown>;
    summary: {
        type: string;
        currency: string;
        line_count: number;
        buyer_name: string;
    };
};

function integrationInboxBasePath(companyId: string, linkedStoreId?: string | null): string {
    return resolveIntegrationInboxBasePath(companyId, linkedStoreId);
}

export async function fetchIntegrationInbox(
    companyId: string,
    linkedStoreId?: string | null,
): Promise<IntegrationInboxEntry[]> {
    const { data } = await api.get(integrationInboxBasePath(companyId, linkedStoreId));
    return (data.data ?? []) as IntegrationInboxEntry[];
}

/**
 * A stamped (auto-issued) entry may only be auto-cleared when the matched
 * local document carries the SAME number - otherwise silently marking it
 * imported would erase an issued (and possibly already emailed) invoice
 * number from the books. Such entries stay pending for a manual decision.
 */
export function canAutoReconcileEntry(
    entry: Pick<IntegrationInboxEntry, "payload">,
    existingNumber: string | null | undefined,
): boolean {
    const stampedNumber = String(entry.payload?.number ?? "").trim();
    if (stampedNumber === "") {
        return true;
    }

    return String(existingNumber ?? "").trim() === stampedNumber;
}

/** Drop inbox rows already present locally (relay import on another device) and clear them on the server. */
export async function reconcileIntegrationInboxWithLocalDocuments(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: string,
    entries: IntegrationInboxEntry[],
    linkedStoreId?: string | null,
): Promise<IntegrationInboxEntry[]> {
    const typedCompanyId = companyId as CompanyId;
    const documents = (await evolu.loadQuery(allDocumentsQuery)) as EvoluDocumentRow[];
    const remaining: IntegrationInboxEntry[] = [];

    for (const entry of entries) {
        const existing = findLocalDocumentForInboxEntry(documents, typedCompanyId, entry);
        if (!existing || !canAutoReconcileEntry(entry, existing.number)) {
            remaining.push(entry);
            continue;
        }
        try {
            await markIntegrationInboxImported(companyId, entry.inbox_id, linkedStoreId);
        } catch {
            // Server inbox may already be cleared; still hide locally.
        }
    }

    return remaining;
}

export async function dismissIntegrationInboxItem(
    companyId: string,
    inboxId: string,
    linkedStoreId?: string | null,
): Promise<void> {
    await api.post(`${integrationInboxBasePath(companyId, linkedStoreId)}/${inboxId}/dismiss`);
}

export async function markIntegrationInboxImported(
    companyId: string,
    inboxId: string,
    linkedStoreId?: string | null,
): Promise<void> {
    await api.post(`${integrationInboxBasePath(companyId, linkedStoreId)}/${inboxId}/imported`);
}

function vatOptionsForContact(company: VatPolicyCompany, contact: VatPolicyContact | null) {
    const vatPolicy = useCompanyVatPolicy();
    const defaultVat = vatPolicy.defaultTaxRate(company);
    return {
        defaultVat,
        lineTaxApplies: () => vatPolicy.calculatesVatAmounts(company),
        lineTaxRate: (line: DocumentLinePayload) =>
            vatPolicy.resolveLineTaxRate(company, contact, line.tax_rate ?? defaultVat),
    };
}

function buyerToContactForm(buyer: Record<string, unknown>): ContactFormState {
    const emailRaw = String(buyer.email ?? "").trim();
    const email = emailRaw.includes("@") ? emailRaw : "";
    const name = String(buyer.name ?? "").trim();
    const company = String(buyer.company ?? "").trim();

    return {
        ...emptyContactForm(),
        name: name || company || email || "WooCommerce buyer",
        email,
        registration_number: String(buyer.ico ?? ""),
        tax_id: String(buyer.dic ?? ""),
        vat_id: String(buyer.ic_dph ?? ""),
        street: String(buyer.street ?? ""),
        city: String(buyer.city ?? ""),
        postal_code: String(buyer.zip ?? ""),
        country: String(buyer.country ?? "").trim().slice(0, 2).toUpperCase(),
    };
}

function payloadToDocumentSave(
    payload: Record<string, unknown>,
    contactId: string,
    jurisdiction: string,
): DocumentSavePayload {
    const rawLines = Array.isArray(payload.lines) ? payload.lines : [];
    const lines: DocumentLinePayload[] = rawLines.map((line) => {
        const row = line as Record<string, unknown>;
        return {
            name: String(row.name ?? "Item"),
            description: null,
            quantity: Number(row.quantity) || 0,
            unit: String(row.unit ?? "pcs"),
            unit_price: Number(row.unit_price) || 0,
            line_discount_percent: 0,
            tax_rate: Number(row.tax_rate) || 0,
            company_stock_item_id: null,
            company_warehouse_id: null,
        };
    });

    return {
        type: String(payload.type ?? "invoice"),
        company_contact_id: contactId,
        store_id: String(payload.store_id ?? ""),
        title: "",
        issue_date: String(payload.issue_date ?? "").slice(0, 10),
        delivery_date: String(payload.delivery_date ?? "").slice(0, 10),
        due_date: String(payload.due_date ?? "").slice(0, 10),
        variable_symbol: "",
        constant_symbol: "",
        specific_symbol: "",
        currency: String(payload.currency ?? "EUR"),
        discount_percent: Number(payload.discount_percent) || 0,
        note_above_lines: String(payload.note_above_lines ?? ""),
        note_footer: "",
        internal_note: String(payload.internal_note ?? ""),
        pdf_locale:
            String(payload.pdf_locale ?? "")
            || defaultPdfLocaleForJurisdiction(jurisdiction),
        pdf_show_signature: true,
        pdf_show_payment_info: true,
        payment_bank_enabled: payload.payment_bank_enabled !== false,
        payment_btc_enabled: Boolean(payload.payment_btc_enabled),
        tags: Array.isArray(payload.tags) ? payload.tags.map(String) : [],
        lines,
    };
}

function resolveLocalContactId(
    companyId: CompanyId,
    contacts: EvoluContactRow[],
    buyer: Record<string, unknown>,
    evolu: Evolu<InvoicingLocalSchema>,
): { ok: true; contactId: ContactId } | { ok: false; error: string } {
    const form = buyerToContactForm(buyer);
    const email = form.email.trim().toLowerCase();
    const existing = contacts.find(
        (contact) =>
            contact.companyId === companyId &&
            email &&
            String(contact.email ?? "").trim().toLowerCase() === email,
    );
    if (existing) {
        const updated = updateLocalContactFromForm(evolu, existing.id as ContactId, form, false);
        if (!updated.ok) {
            return { ok: false, error: "contact_create_failed" };
        }
        return { ok: true, contactId: existing.id as ContactId };
    }

    const inserted = insertLocalContactFromForm(evolu, companyId, form, false);
    if (!inserted.ok) {
        return { ok: false, error: "contact_create_failed" };
    }

    return { ok: true, contactId: inserted.value.id as ContactId };
}

async function issueImportedDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    documentId: DocumentId,
): Promise<{ ok: true } | { ok: false; error: string }> {
    const companies = (await evolu.loadQuery(allCompaniesQuery)) as EvoluCompanyRow[];
    const companyRow = companies.find((row) => row.id === companyId);
    if (!companyRow) {
        return { ok: false, error: "company_not_found" };
    }

    const issueResult = await issueLocalDocumentAsync(evolu, documentId, companyRow);
    if (!issueResult.ok) {
        return { ok: false, error: String(issueResult.error ?? "issue_failed") };
    }

    return { ok: true };
}

async function markImportedDocumentPaid(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    payload: Record<string, unknown>,
): Promise<{ ok: true } | { ok: false; error: string }> {
    const documents = (await evolu.loadQuery(allDocumentsQuery)) as EvoluDocumentRow[];
    const doc = documents.find((row) => row.id === documentId);
    const docTotal = parseFloat(doc?.total ?? "0");
    const orderTotal = Number(payload.order_total);
    const paidAmount = Number.isFinite(orderTotal) && orderTotal > 0 ? orderTotal : docTotal;

    const markResult = markLocalDocumentPaidWithAmount(evolu, documentId, documents, paidAmount);
    if (!markResult.ok) {
        return { ok: false, error: String(markResult.error ?? "mark_paid_failed") };
    }

    return { ok: true };
}

/**
 * The customer's payment settles the linked proforma together with the final
 * invoice. Best-effort: the proforma may not be imported yet (or was deleted),
 * and a failure here must never abort the invoice import itself.
 */
async function markLinkedProformaPaid(
    evolu: Evolu<InvoicingLocalSchema>,
    sourceDocumentId: DocumentId,
    payload: Record<string, unknown>,
): Promise<void> {
    try {
        const rows = await evolu.loadQuery(allDocumentsQuery);
        const documents = rows as unknown as EvoluDocumentRow[];
        const proforma = documents.find((row) => row.id === sourceDocumentId);
        if (!proforma) {
            return;
        }
        const orderTotal = Number(payload.order_total);
        const amount =
            Number.isFinite(orderTotal) && orderTotal > 0
                ? orderTotal
                : parseFloat(proforma.total ?? "0");
        markLocalDocumentPaidWithAmount(evolu, sourceDocumentId, documents, amount);
    } catch {
        // Best-effort by design.
    }
}

function resolveStoreIdForApi(
    linkedStoreId: string | null | undefined,
    payload: Record<string, unknown>,
): string | null {
    const linked = typeof linkedStoreId === "string" ? linkedStoreId.trim() : "";
    if (linked) {
        return linked;
    }
    const fromPayload = String(payload.store_id ?? "").trim();
    return fromPayload || null;
}

export async function importIntegrationInboxEntry(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: string,
    entry: IntegrationInboxEntry,
    company: VatPolicyCompany,
    linkedStoreId?: string | null,
): Promise<{ ok: true } | { ok: false; error: string }> {
    const typedCompanyId = companyId as CompanyId;
    const [contacts, existingLines, existingDocuments] = await Promise.all([
        evolu.loadQuery(allContactsQuery) as Promise<EvoluContactRow[]>,
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allDocumentsQuery) as Promise<EvoluDocumentRow[]>,
    ]);

    const alreadyImported = findLocalDocumentForInboxEntry(
        existingDocuments,
        typedCompanyId,
        entry,
    );
    // A stamped entry whose number is NOT represented locally must fall
    // through to a real import (the order-id match may point at an OLDER
    // invoice for the same order) - short-circuiting here would erase an
    // issued, possibly already emailed number from the books.
    if (alreadyImported && canAutoReconcileEntry(entry, alreadyImported.number)) {
        const storeIdForApi = resolveStoreIdForApi(linkedStoreId, entry.payload);
        await markIntegrationInboxImported(companyId, entry.inbox_id, storeIdForApi);
        return { ok: true };
    }

    const stableDocumentId = stableDocumentIdFromInboxUuid(entry.evolu_document_id);
    if (!stableDocumentId) {
        return { ok: false, error: "invalid_inbox_document_id" };
    }

    const buyer = (entry.payload.buyer ?? {}) as Record<string, unknown>;
    const contactResult = resolveLocalContactId(typedCompanyId, contacts, buyer, evolu);
    if (!contactResult.ok) {
        return contactResult;
    }

    const vat = vatOptionsForContact(company, { country: buyer.country as string | null });
    const documentPayload = payloadToDocumentSave(
        entry.payload,
        contactResult.contactId,
        String(company?.jurisdiction ?? ""),
    );
    const existingDocument =
        existingDocuments.find((row) => row.id === stableDocumentId) ?? null;

    // Deferred-payment flow: the final invoice references the proforma's
    // inbox uuid; the same stable-id derivation both sides use for imports
    // turns it into the proforma's LOCAL document id, so the link works
    // regardless of which entry is imported first.
    const sourceDocumentId = resolveLinkedSourceDocumentId(entry.payload);

    const saveResult = saveLocalDocument(evolu, typedCompanyId, documentPayload, {
        documentId: stableDocumentId,
        existingDocument,
        sourceDocumentId,
        ...vat,
        existingLines,
    });

    if (!saveResult.ok) {
        return { ok: false, error: String(saveResult.error ?? "save_failed") };
    }

    const savedDocumentId = saveResult.value.id;
    const actions = resolveImportSettleActions(entry.payload);
    if (actions.reservedNumber) {
        const allSeries = (await evolu.loadQuery(allNumberSeriesQuery)) as EvoluNumberSeriesRow[];
        const documentType = String(entry.payload.type ?? "invoice") as DocumentType;
        const applyResult = await applyReservedNumberToLocalDocumentAsync(
            evolu,
            savedDocumentId,
            typedCompanyId,
            documentType,
            actions.reservedNumber,
            allSeries,
            entry.payload.variable_symbol as string | null | undefined,
        );
        if (!applyResult.ok) {
            return { ok: false, error: String(applyResult.error ?? "issue_failed") };
        }
    } else if (actions.issueLocally) {
        const issueResult = await issueImportedDocument(evolu, typedCompanyId, savedDocumentId);
        if (!issueResult.ok) {
            return issueResult;
        }
    }

    if (actions.markPaid) {
        const markResult = await markImportedDocumentPaid(evolu, savedDocumentId, entry.payload);
        if (!markResult.ok) {
            return markResult;
        }
        if (sourceDocumentId) {
            await markLinkedProformaPaid(evolu, sourceDocumentId, entry.payload);
        }
    }

    const storeIdForApi = resolveStoreIdForApi(linkedStoreId, entry.payload);
    await markIntegrationInboxImported(companyId, entry.inbox_id, storeIdForApi);
    await Promise.all([
        evolu.loadQuery(allContactsQuery),
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allNumberSeriesQuery),
    ]);
    await waitForInvoicingDataSettled(evolu, { minWaitMs: 1500, timeoutMs: 8000 });

    return { ok: true };
}
