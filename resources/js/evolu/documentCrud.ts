import {
    booleanToSqliteBoolean,
    maxLength,
    NonEmptyString,
    sqliteTrue,
} from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema, CompanyId, DocumentId, DocumentLineId } from "./schema";
import {
    evoluDocumentToApi,
    type EvoluDocumentLineRow,
    type EvoluDocumentRow,
} from "./documentMap";
import {
    allocateNextNumberForIssue,
    commitNumberSeriesCounter,
    syncLocalSeriesCounterFromIssuedNumber,
} from "./numberSeriesCrud";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { documentVariableSymbol } from "./documentNumber";
import type { EvoluCompanyRow } from "./companyMap";
import { logDocumentEvent } from "./documentEventLog";
import { applyDocumentStockOnIssueAsync, reverseDocumentStockOnCancelAsync } from "./documentStockMovement";
import type { DocumentType } from "./schema";
import { allDocumentsQuery, allNumberSeriesQuery } from "./client";

export type DocumentLinePayload = {
    name: string;
    description: string | null;
    quantity: number;
    unit: string;
    unit_price: number;
    line_discount_percent: number;
    tax_rate: number;
    company_stock_item_id: string | null;
    company_warehouse_id: string | null;
};

export type DocumentSavePayload = {
    type: string;
    company_contact_id: string;
    store_id: string;
    title: string;
    issue_date: string;
    delivery_date: string;
    due_date: string;
    variable_symbol: string;
    constant_symbol: string;
    specific_symbol: string;
    currency: string;
    discount_percent: number;
    note_above_lines: string;
    note_footer: string;
    internal_note: string;
    pdf_locale: string;
    pdf_show_signature: boolean;
    pdf_show_payment_info: boolean;
    payment_bank_enabled: boolean;
    payment_btc_enabled: boolean;
    tags: string[];
    lines: DocumentLinePayload[];
};

const TitleType = maxLength(1000)(NonEmptyString);
const LineNameType = maxLength(255)(NonEmptyString);
const CurrencyType = maxLength(3)(NonEmptyString);

function fmtDec(value: number, digits = 2): string {
    return value.toFixed(digits);
}

export function calcDocumentTotals(
    lines: DocumentLinePayload[],
    documentDiscountPercent: number,
    defaultVat: number,
    lineTaxApplies: (line: DocumentLinePayload) => boolean,
    lineTaxRate: (line: DocumentLinePayload) => number,
) {
    let subtotal = 0;
    let tax = 0;
    const lineTotals: number[] = [];

    for (const line of lines) {
        const net =
            (line.quantity || 0)
            * (line.unit_price || 0)
            * (1 - (line.line_discount_percent || 0) / 100);
        const rate = lineTaxApplies(line) ? lineTaxRate(line) : 0;
        const lineTax = net * (rate / 100);
        subtotal += net;
        tax += lineTax;
        lineTotals.push(net + lineTax);
    }

    const gross = subtotal + tax;
    const total = gross * (1 - (documentDiscountPercent || 0) / 100);
    if (documentDiscountPercent > 0 && gross > 0) {
        const ratio = total / gross;
        subtotal *= ratio;
        tax *= ratio;
        for (let i = 0; i < lineTotals.length; i++) {
            lineTotals[i] *= ratio;
        }
    }

    return {
        subtotal: fmtDec(subtotal),
        taxTotal: fmtDec(tax),
        total: fmtDec(total),
        lineTotals: lineTotals.map((v) => fmtDec(v)),
    };
}

function buildDocumentFields(
    payload: DocumentSavePayload,
    totals: ReturnType<typeof calcDocumentTotals>,
    status: string,
    quoteStatus: string | null,
    number: string | null,
    sourceDocumentId: DocumentId | null,
) {
    const title = TitleType.from(payload.title.trim() || "Document");
    if (!title.ok) return title;
    const currency = CurrencyType.from(payload.currency || "EUR");
    if (!currency.ok) return currency;

    const paymentFlags =
        payload.type === "quote"
            ? { bank: false, btc: false, pdfPay: false }
            : {
                bank: payload.payment_bank_enabled,
                btc: payload.payment_btc_enabled,
                pdfPay: payload.pdf_show_payment_info,
            };

    return {
        ok: true as const,
        value: {
            contactId: payload.company_contact_id || null,
            documentType: payload.type,
            status,
            quoteStatus: payload.type === "quote" ? quoteStatus : null,
            title: title.value,
            number,
            sourceDocumentId,
            issueDate: payload.issue_date || null,
            deliveryDate: payload.delivery_date || null,
            dueDate: payload.due_date || null,
            variableSymbol: documentVariableSymbol(payload.variable_symbol) || null,
            constantSymbol: payload.constant_symbol || null,
            specificSymbol: payload.specific_symbol || null,
            currency: currency.value,
            subtotal: totals.subtotal,
            taxTotal: totals.taxTotal,
            discountPercent: fmtDec(payload.discount_percent || 0),
            total: totals.total,
            noteAboveLines: payload.note_above_lines || null,
            noteFooter: payload.note_footer || null,
            internalNote: payload.internal_note || null,
            pdfLocale: payload.pdf_locale || "sk",
            pdfShowSignature: booleanToSqliteBoolean(payload.pdf_show_signature),
            pdfShowPaymentInfo: booleanToSqliteBoolean(paymentFlags.pdfPay),
            paymentBankEnabled: booleanToSqliteBoolean(paymentFlags.bank),
            paymentBtcEnabled: booleanToSqliteBoolean(paymentFlags.btc),
            storeId: payload.store_id || null,
            tagsJson: payload.tags.length ? JSON.stringify(payload.tags) : null,
            paidAt: null,
            amountPaid: null,
        },
    };
}

function syncDocumentLines(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    payload: DocumentSavePayload,
    lineTotals: string[],
    existingLines: EvoluDocumentLineRow[],
) {
    for (const line of existingLines) {
        if (line.documentId !== documentId) continue;
        evolu.update("documentLine", { id: line.id as DocumentLineId, isDeleted: sqliteTrue });
    }

    payload.lines.forEach((line, index) => {
        const name = LineNameType.from(line.name.trim());
        if (!name.ok) return;
        evolu.insert("documentLine", {
            documentId,
            sortOrder: String(index),
            name: name.value,
            description: line.description,
            quantity: fmtDec(line.quantity, 4),
            unit: line.unit || "ks",
            unitPrice: fmtDec(line.unit_price, 4),
            lineDiscountPercent: fmtDec(line.line_discount_percent || 0),
            taxRate: fmtDec(line.tax_rate ?? 0),
            lineTotal: lineTotals[index] || "0",
            companyStockItemId: line.company_stock_item_id,
            companyWarehouseId: line.company_warehouse_id,
        });
    });
}

export function saveLocalDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: DocumentSavePayload,
    options: {
        documentId?: DocumentId;
        existingDocument?: EvoluDocumentRow | null;
        defaultVat: number;
        lineTaxApplies: (line: DocumentLinePayload) => boolean;
        lineTaxRate: (line: DocumentLinePayload) => number;
        existingLines: EvoluDocumentLineRow[];
        sourceDocumentId?: DocumentId | null;
    },
) {
    if (!payload.lines.length) {
        return { ok: false as const, error: "lines_required" };
    }

    const totals = calcDocumentTotals(
        payload.lines,
        payload.discount_percent,
        options.defaultVat,
        options.lineTaxApplies,
        options.lineTaxRate as (line: DocumentLinePayload) => number,
    );

    const existing = options.existingDocument ?? null;
    const statusForSave = existing?.status ?? "draft";
    const numberForSave = existing?.number ?? null;
    const quoteStatusForSave =
        payload.type === "quote"
            ? (existing?.quoteStatus ?? "pending")
            : null;

    const fields = buildDocumentFields(
        payload,
        totals,
        statusForSave,
        quoteStatusForSave,
        numberForSave,
        options.sourceDocumentId ?? existing?.sourceDocumentId ?? null,
    );
    if (!fields.ok) return fields;

    if (existing) {
        fields.value.paidAt = existing.paidAt;
        fields.value.amountPaid = existing.amountPaid;
    }

    let docId = options.documentId;
    if (docId) {
        const result = evolu.update("document", { id: docId, ...fields.value });
        if (!result.ok) return result;
    } else {
        const result = evolu.insert("document", {
            companyId,
            ...fields.value,
        });
        if (!result.ok) return result;
        docId = result.value.id;
    }

    syncDocumentLines(evolu, docId, payload, totals.lineTotals, options.existingLines);
    return { ok: true as const, value: { id: docId } };
}

async function loadIssueContext(evolu: Evolu<InvoicingLocalSchema>): Promise<{
    documents: EvoluDocumentRow[];
    series: EvoluNumberSeriesRow[];
}> {
    const [documents, series] = await Promise.all([
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allNumberSeriesQuery),
    ]);
    return {
        documents: documents as EvoluDocumentRow[],
        series: series as EvoluNumberSeriesRow[],
    };
}

async function waitForDraftDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
): Promise<EvoluDocumentRow | null> {
    for (let attempt = 0; attempt < 8; attempt += 1) {
        const documents = (await evolu.loadQuery(allDocumentsQuery)) as EvoluDocumentRow[];
        const doc = documents.find((row) => row.id === documentId);
        if (doc) {
            return doc;
        }
        await new Promise((resolve) => {
            setTimeout(resolve, attempt === 0 ? 0 : 40);
        });
    }
    return null;
}

export function issueLocalDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    company: EvoluCompanyRow,
    allDocuments: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
) {
    const doc = allDocuments.find((d) => d.id === documentId);
    if (!doc || doc.status !== "draft") {
        return { ok: false as const, error: "not_draft" };
    }

    const allocation = allocateNextNumberForIssue(
        evolu,
        company.id,
        doc.documentType,
        allDocuments,
        allSeries,
        documentId,
    );
    if (!allocation.ok) {
        return allocation;
    }
    const number = allocation.value;
    const variableSymbol = documentVariableSymbol(doc.variableSymbol, number);
    const quoteStatus = doc.documentType === "quote" ? "pending" : doc.quoteStatus;

    const result = evolu.update("document", {
        id: documentId,
        status: "issued",
        number,
        variableSymbol,
        quoteStatus,
    });
    if (!result.ok) {
        return result;
    }

    const committed = commitNumberSeriesCounter(evolu, allocation);
    if (!committed.ok) {
        evolu.update("document", {
            id: documentId,
            status: "draft",
            number: null,
            variableSymbol: doc.variableSymbol,
            quoteStatus: doc.quoteStatus,
        });
        return committed;
    }

    logDocumentEvent(evolu, documentId, "business_document.issued", { number });
    return result;
}

async function finalizeIssueStock(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    issueResult: { ok: boolean },
) {
    if (issueResult.ok) {
        await applyDocumentStockOnIssueAsync(evolu, documentId);
    }
}

export async function issueLocalDocumentAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    company: EvoluCompanyRow,
) {
    const draft = await waitForDraftDocument(evolu, documentId);
    if (!draft || draft.status !== "draft") {
        return { ok: false as const, error: "not_draft" };
    }

    const { documents, series } = await loadIssueContext(evolu);
    const result = issueLocalDocument(evolu, documentId, company, documents, series);
    await finalizeIssueStock(evolu, documentId, result);
    return result;
}

export function applyReservedNumberToLocalDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    companyId: CompanyId,
    documentType: DocumentType,
    number: string,
    allSeries: EvoluNumberSeriesRow[],
    variableSymbol?: string | null,
) {
    syncLocalSeriesCounterFromIssuedNumber(evolu, companyId, documentType, number, allSeries);
    const quoteStatus = documentType === "quote" ? "pending" : null;
    const result = evolu.update("document", {
        id: documentId,
        status: "issued",
        number,
        variableSymbol: documentVariableSymbol(variableSymbol, number),
        quoteStatus,
    });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.issued", { number, source: "woo_inbox" });
    }
    return result;
}

export async function applyReservedNumberToLocalDocumentAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    companyId: CompanyId,
    documentType: DocumentType,
    number: string,
    allSeries: EvoluNumberSeriesRow[],
    variableSymbol?: string | null,
) {
    const result = applyReservedNumberToLocalDocument(
        evolu,
        documentId,
        companyId,
        documentType,
        number,
        allSeries,
        variableSymbol,
    );
    await finalizeIssueStock(evolu, documentId, result);
    return result;
}

export function getLocalDocumentApi(
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
): Record<string, unknown> | null {
    const doc = documents.find((d) => d.id === documentId);
    if (!doc) return null;
    const docLines = lines.filter((l) => l.documentId === documentId);
    return evoluDocumentToApi(doc, docLines, documents);
}

export function duplicateLocalDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    payload: DocumentSavePayload,
    options: Parameters<typeof saveLocalDocument>[3],
) {
    const doc = documents.find((d) => d.id === documentId);
    if (!doc) return { ok: false as const, error: "not_found" };

    const copyPayload: DocumentSavePayload = {
        ...payload,
        title: payload.title ? `${payload.title} (copy)` : "Copy",
    };
    return saveLocalDocument(evolu, doc.companyId, copyPayload, {
        ...options,
        documentId: undefined,
        sourceDocumentId: null,
    });
}

export function deleteLocalDocument(evolu: Evolu<InvoicingLocalSchema>, documentId: DocumentId) {
    const result = evolu.update("document", { id: documentId, isDeleted: sqliteTrue });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.deleted");
    }
    return result;
}

export function cancelLocalDocument(evolu: Evolu<InvoicingLocalSchema>, documentId: DocumentId) {
    const result = evolu.update("document", { id: documentId, status: "cancelled" });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.cancelled");
    }
    return result;
}

export async function cancelLocalDocumentAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
) {
    const doc = documents.find((row) => row.id === documentId);
    const result = cancelLocalDocument(evolu, documentId);
    if (!result.ok || doc?.status !== "issued") {
        return result;
    }
    await reverseDocumentStockOnCancelAsync(evolu, documentId);
    return result;
}

export function markLocalDocumentPaid(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
) {
    const doc = documents.find((d) => d.id === documentId);
    if (!doc) return { ok: false as const, error: "not_found" };
    const total = parseFloat(doc.total || "0");
    return markLocalDocumentPaidWithAmount(evolu, documentId, documents, total);
}

export function markLocalDocumentPaidWithAmount(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
    amountPaid: number,
    tolerance = 0.01,
) {
    const doc = documents.find((d) => d.id === documentId);
    if (!doc) return { ok: false as const, error: "not_found" };
    if (doc.status === "cancelled") {
        return { ok: false as const, error: "cancelled" };
    }
    if (doc.status === "paid") {
        return { ok: true as const, value: { id: documentId } };
    }
    if (doc.status !== "issued") {
        return { ok: false as const, error: "not_issued" };
    }

    const total = parseFloat(doc.total || "0");
    const fullyPaid = Math.abs(amountPaid - total) <= tolerance;
    const result = evolu.update("document", {
        id: documentId,
        status: fullyPaid ? "paid" : "issued",
        paidAt: fullyPaid ? new Date().toISOString() : doc.paidAt,
        amountPaid: amountPaid.toFixed(2),
    });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.marked_paid", {
            amount_paid: amountPaid,
        });
    }
    return result;
}

export function unmarkLocalDocumentPaid(evolu: Evolu<InvoicingLocalSchema>, documentId: DocumentId) {
    const result = evolu.update("document", {
        id: documentId,
        status: "issued",
        paidAt: null,
        amountPaid: null,
    });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.unmarked_paid");
    }
    return result;
}

export function markLocalDocumentEmailSent(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    sentAt?: string,
) {
    const result = evolu.update("document", {
        id: documentId,
        emailSentAt: sentAt ?? new Date().toISOString(),
    });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.email_sent");
    }
    return result;
}

export function approveLocalQuote(evolu: Evolu<InvoicingLocalSchema>, documentId: DocumentId) {
    const result = evolu.update("document", { id: documentId, quoteStatus: "approved" });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.quote_approved");
    }
    return result;
}

export function rejectLocalQuote(evolu: Evolu<InvoicingLocalSchema>, documentId: DocumentId) {
    const result = evolu.update("document", { id: documentId, quoteStatus: "rejected" });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.quote_rejected");
    }
    return result;
}

export function createLocalInvoiceFromQuote(
    evolu: Evolu<InvoicingLocalSchema>,
    quoteId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    buildPayloadFromDoc: (doc: Record<string, unknown>) => DocumentSavePayload,
    saveOptions: Parameters<typeof saveLocalDocument>[3],
) {
    const api = getLocalDocumentApi(quoteId, documents, lines);
    if (!api) return { ok: false as const, error: "not_found" };
    const payload = buildPayloadFromDoc(api);
    payload.type = "invoice";
    const doc = documents.find((d) => d.id === quoteId)!;
    const result = saveLocalDocument(evolu, doc.companyId, payload, {
        ...saveOptions,
        sourceDocumentId: quoteId,
    });
    if (result.ok) {
        logDocumentEvent(evolu, result.value.id, "business_document.invoice_from_quote", {
            quote_id: quoteId,
            quote_number: doc.number,
        });
    }
    return result;
}

export function createLocalFinalInvoiceFromProforma(
    evolu: Evolu<InvoicingLocalSchema>,
    proformaId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    buildPayloadFromDoc: (doc: Record<string, unknown>) => DocumentSavePayload,
    saveOptions: Parameters<typeof saveLocalDocument>[3],
) {
    const api = getLocalDocumentApi(proformaId, documents, lines);
    if (!api) return { ok: false as const, error: "not_found" };
    const payload = buildPayloadFromDoc(api);
    payload.type = "invoice";
    const doc = documents.find((d) => d.id === proformaId)!;
    const result = saveLocalDocument(evolu, doc.companyId, payload, {
        ...saveOptions,
        sourceDocumentId: proformaId,
    });
    if (result.ok) {
        logDocumentEvent(evolu, result.value.id, "business_document.final_from_proforma", {
            proforma_id: proformaId,
            proforma_number: doc.number,
        });
    }
    return result;
}

export function createLocalCreditNoteFromInvoice(
    evolu: Evolu<InvoicingLocalSchema>,
    invoiceId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    saveOptions: Parameters<typeof saveLocalDocument>[3],
    referenceNote?: string,
) {
    const invoice = documents.find((d) => d.id === invoiceId);
    if (!invoice) return { ok: false as const, error: "not_found" };
    if (invoice.documentType !== "invoice") {
        return { ok: false as const, error: "not_invoice" };
    }
    if (invoice.status === "cancelled") {
        return { ok: false as const, error: "cancelled" };
    }
    if (!["issued", "paid"].includes(invoice.status)) {
        return { ok: false as const, error: "not_issued" };
    }
    if (!invoice.number) {
        return { ok: false as const, error: "no_number" };
    }

    const api = getLocalDocumentApi(invoiceId, documents, lines);
    if (!api) return { ok: false as const, error: "not_found" };

    const today = new Date().toISOString().slice(0, 10);
    const payload = payloadFromApiDocument(api);
    payload.type = "credit_note";
    payload.title = "";
    payload.issue_date = today;
    payload.due_date = today;
    payload.note_above_lines = referenceNote ?? `For invoice ${invoice.number}.`;
    payload.pdf_show_payment_info = false;
    payload.payment_btc_enabled = false;
    payload.payment_bank_enabled = false;

    const result = saveLocalDocument(evolu, invoice.companyId, payload, {
        ...saveOptions,
        documentId: undefined,
        sourceDocumentId: invoiceId,
    });
    if (result.ok) {
        logDocumentEvent(evolu, result.value.id, "business_document.credit_note_from_invoice", {
            invoice_id: invoiceId,
            invoice_number: invoice.number,
        });
    }
    return result;
}

export function payloadFromApiDocument(doc: Record<string, unknown>): DocumentSavePayload {
    return {
        type: String(doc.type || "invoice"),
        company_contact_id: String(doc.company_contact_id || ""),
        store_id: String(doc.store_id || ""),
        title: String(doc.title || ""),
        issue_date: String(doc.issue_date || "").slice(0, 10),
        delivery_date: String(doc.delivery_date || "").slice(0, 10),
        due_date: String(doc.due_date || "").slice(0, 10),
        variable_symbol: String(doc.variable_symbol || ""),
        constant_symbol: String(doc.constant_symbol || ""),
        specific_symbol: String(doc.specific_symbol || ""),
        currency: String(doc.currency || "EUR"),
        discount_percent: Number(doc.discount_percent) || 0,
        note_above_lines: String(doc.note_above_lines || ""),
        note_footer: String(doc.note_footer || ""),
        internal_note: String(doc.internal_note || ""),
        pdf_locale: String(doc.pdf_locale || "sk"),
        pdf_show_signature: doc.pdf_show_signature !== false,
        pdf_show_payment_info: doc.pdf_show_payment_info !== false,
        payment_bank_enabled: doc.payment_bank_enabled !== false,
        payment_btc_enabled: Boolean(doc.payment_btc_enabled),
        tags: Array.isArray(doc.tags) ? doc.tags.map(String) : [],
        lines: ((doc.lines as DocumentLinePayload[]) || []).map((l) => ({
            name: String(l.name || ""),
            description: l.description ? String(l.description) : null,
            quantity: Number(l.quantity) || 0,
            unit: String(l.unit || "ks"),
            unit_price: Number(l.unit_price) || 0,
            line_discount_percent: Number(l.line_discount_percent) || 0,
            tax_rate: Number(l.tax_rate) || 0,
            company_stock_item_id: l.company_stock_item_id ? String(l.company_stock_item_id) : null,
            company_warehouse_id: l.company_warehouse_id ? String(l.company_warehouse_id) : null,
        })),
    };
}
