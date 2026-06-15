import type { Evolu } from "@evolu/common/local-first";
import api from "@/services/api";
import { emptyContactForm, type ContactFormState } from "@/composables/useCompanyContact";
import type { VatPolicyCompany, VatPolicyContact } from "@/composables/useCompanyVatPolicy";
import { useCompanyVatPolicy } from "@/composables/useCompanyVatPolicy";
import { allContactsQuery, allDocumentLinesQuery, allDocumentsQuery } from "./client";
import { insertLocalContactFromForm } from "./contactCrud";
import type { EvoluContactRow } from "./contactMap";
import {
    saveLocalDocument,
    applyReservedNumberToLocalDocumentAsync,
    type DocumentLinePayload,
    type DocumentSavePayload,
} from "./documentCrud";
import type { CompanyId, ContactId, DocumentId, DocumentType, InvoicingLocalSchema } from "./schema";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { allNumberSeriesQuery } from "./client";

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
    if (linkedStoreId) {
        return `/invoicing/stores/${linkedStoreId}/integration-inbox`;
    }

    return `/invoicing/companies/${companyId}/integration-inbox`;
}

export async function fetchIntegrationInbox(
    companyId: string,
    linkedStoreId?: string | null,
): Promise<IntegrationInboxEntry[]> {
    const { data } = await api.get(integrationInboxBasePath(companyId, linkedStoreId));
    return (data.data ?? []) as IntegrationInboxEntry[];
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
    const defaultVat = vatPolicy.defaultTaxRate(company, contact ?? undefined);
    return {
        defaultVat,
        lineTaxApplies: (_line: DocumentLinePayload) =>
            vatPolicy.calculatesVatAmounts(company, contact),
        lineTaxRate: (line: DocumentLinePayload) =>
            vatPolicy.resolveLineTaxRate(company, contact, line.tax_rate ?? defaultVat),
    };
}

function buyerToContactForm(buyer: Record<string, unknown>): ContactFormState {
    const email = String(buyer.email ?? "").trim();
    const name = String(buyer.name ?? "").trim();

    return {
        ...emptyContactForm(),
        name: name || email || "WooCommerce buyer",
        email,
        registration_number: String(buyer.ico ?? ""),
        tax_id: String(buyer.dic ?? ""),
        vat_id: String(buyer.ic_dph ?? ""),
        street: String(buyer.street ?? ""),
        city: String(buyer.city ?? ""),
        postal_code: String(buyer.zip ?? ""),
        country: String(buyer.country ?? ""),
    };
}

function payloadToDocumentSave(
    payload: Record<string, unknown>,
    contactId: string,
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
        discount_percent: 0,
        note_above_lines: String(payload.note_above_lines ?? ""),
        note_footer: "",
        internal_note: String(payload.internal_note ?? ""),
        pdf_locale: "sk",
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
    const email = String(buyer.email ?? "").trim().toLowerCase();
    const existing = contacts.find(
        (contact) =>
            contact.companyId === companyId &&
            email &&
            String(contact.email ?? "").trim().toLowerCase() === email,
    );
    if (existing) {
        return { ok: true, contactId: existing.id as ContactId };
    }

    const form = buyerToContactForm(buyer);
    const inserted = insertLocalContactFromForm(evolu, companyId, form, false);
    if (!inserted.ok) {
        return { ok: false, error: "contact_create_failed" };
    }

    return { ok: true, contactId: inserted.value.id as ContactId };
}

export async function importIntegrationInboxEntry(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: string,
    entry: IntegrationInboxEntry,
    company: VatPolicyCompany,
    linkedStoreId?: string | null,
): Promise<{ ok: true } | { ok: false; error: string }> {
    const typedCompanyId = companyId as CompanyId;
    const contacts = (await evolu.loadQuery(allContactsQuery)) as EvoluContactRow[];
    const existingLines = await evolu.loadQuery(allDocumentLinesQuery);

    const buyer = (entry.payload.buyer ?? {}) as Record<string, unknown>;
    const contactResult = resolveLocalContactId(typedCompanyId, contacts, buyer, evolu);
    if (!contactResult.ok) {
        return contactResult;
    }

    const vat = vatOptionsForContact(company, { country: buyer.country as string | null });
    const documentPayload = payloadToDocumentSave(entry.payload, contactResult.contactId);
    const saveResult = saveLocalDocument(evolu, typedCompanyId, documentPayload, {
        documentId: entry.evolu_document_id as DocumentId,
        ...vat,
        existingLines,
    });

    if (!saveResult.ok) {
        return { ok: false, error: String(saveResult.error ?? "save_failed") };
    }

    const reservedNumber = String(entry.payload.number ?? "").trim();
    if (reservedNumber) {
        const allSeries = (await evolu.loadQuery(allNumberSeriesQuery)) as EvoluNumberSeriesRow[];
        const documentType = String(entry.payload.type ?? "invoice") as DocumentType;
        const applyResult = await applyReservedNumberToLocalDocumentAsync(
            evolu,
            entry.evolu_document_id as DocumentId,
            typedCompanyId,
            documentType,
            reservedNumber,
            allSeries,
            entry.payload.variable_symbol as string | null | undefined,
        );
        if (!applyResult.ok) {
            return { ok: false, error: String(applyResult.error ?? "issue_failed") };
        }
    }

    await markIntegrationInboxImported(companyId, entry.inbox_id, linkedStoreId);
    await Promise.all([
        evolu.loadQuery(allContactsQuery),
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allNumberSeriesQuery),
    ]);

    return { ok: true };
}
