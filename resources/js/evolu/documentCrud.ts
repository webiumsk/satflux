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
    resolveDefaultSeries,
    syncLocalSeriesCounterFromIssuedNumber,
    syncNumberSeriesCounterFromDocuments,
} from "./numberSeriesCrud";
import { dateForPeriodKey, localIsoDate, rederiveNumberDerivedFields } from "./numberSeriesFormat";
import i18n from "@/i18n";
import {
    formatNumberFromStoreCounter,
    localHighCounterForStoreBridge,
} from "./numberSequenceBridge";
import { confirmIssueNumber, reserveIssueNumber } from "./numberAllocatorBridge";
import { companyShareInfo } from "./companyShareRegistry";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { documentVariableSymbol } from "./documentNumber";
import { evoluCompanyToApi, type EvoluCompanyRow } from "./companyMap";
import { evoluContactToApi, type EvoluContactRow } from "./contactMap";
import { logDocumentEvent } from "./documentEventLog";
import { applyDocumentStockOnIssueAsync, reverseDocumentStockOnCancelAsync } from "./documentStockMovement";
import {
    ISSUED_SNAPSHOT_FORMAT_VERSION,
    buildIssuedSnapshotContentV1,
    insertIssuedDocumentSnapshot,
} from "./documentSnapshotCrud";
import type { DocumentType } from "./schema";
import { toAppRows } from "./queryLoad";
import {
    allCompaniesDetailQuery,
    allContactsQuery,
    allDocumentLinesQuery,
    allDocumentsQuery,
    allNumberSeriesQuery,
} from "./client";
import type { LocalDocumentDeletionPolicy } from "./documentBulkLocal";

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
    /** Bank QR standard on the PDF: auto|paybysquare|epc|swiss|none (null = auto). */
    pdf_bank_qr?: string | null;
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
            pdfBankQr: payload.pdf_bank_qr || null,
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

/** Lines + buyer needed to freeze an issue snapshot (audit F2). */
export type IssueSnapshotContext = {
    lines: EvoluDocumentLineRow[];
    contact: EvoluContactRow | null;
};

function snapshotLinesFromRows(documentId: DocumentId, lines: EvoluDocumentLineRow[]): Record<string, unknown>[] {
    return lines
        .filter((line) => line.documentId === documentId)
        .sort((a, b) => Number(a.sortOrder ?? 0) - Number(b.sortOrder ?? 0))
        .map((line) => ({
            name: line.name,
            description: line.description,
            quantity: line.quantity || "0",
            unit: line.unit || "ks",
            unit_price: line.unitPrice || "0",
            line_discount_percent: line.lineDiscountPercent || "0",
            tax_rate: line.taxRate || "0",
            line_total: line.lineTotal || "0",
        }));
}

function snapshotLinesFromPayload(payload: DocumentSavePayload, lineTotals: string[]): Record<string, unknown>[] {
    return payload.lines.map((line, index) => ({
        name: line.name.trim(),
        description: line.description,
        quantity: fmtDec(line.quantity, 4),
        unit: line.unit || "ks",
        unit_price: fmtDec(line.unit_price, 4),
        line_discount_percent: fmtDec(line.line_discount_percent || 0),
        tax_rate: fmtDec(line.tax_rate ?? 0),
        line_total: lineTotals[index] || "0",
    }));
}

/**
 * Freezes the document being issued (with its just-allocated number) into a
 * documentSnapshot row. Called BEFORE the status flips to issued - a failed
 * snapshot aborts the issue, never the other way around.
 */
function writeIssueSnapshot(
    evolu: Evolu<InvoicingLocalSchema>,
    doc: EvoluDocumentRow,
    number: string,
    variableSymbol: string | null,
    company: EvoluCompanyRow,
    context: IssueSnapshotContext,
    allDocuments: EvoluDocumentRow[],
): { ok: true; payloadJson: string } | { ok: false; error: string } {
    const apiDoc = evoluDocumentToApi(
        { ...doc, status: "issued", number, variableSymbol },
        [],
        allDocuments,
    );
    const content = buildIssuedSnapshotContentV1({
        company: evoluCompanyToApi(company) as unknown as Record<string, unknown>,
        contact: context.contact
            ? (evoluContactToApi(context.contact) as unknown as Record<string, unknown>)
            : null,
        document: apiDoc,
        lines: snapshotLinesFromRows(doc.id as DocumentId, context.lines),
    });
    if (!content.ok) return content;
    return insertIssuedDocumentSnapshot(evolu, doc.id as DocumentId, content.value);
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
        /**
         * API-shaped supplier/buyer for re-freezing the issued snapshot when
         * an already issued document is edited ("edit + re-freeze" model).
         * Without it, editing an issued document leaves the previous snapshot
         * version in place.
         */
        refreezeContext?: {
            company: Record<string, unknown> | null;
            contact: Record<string, unknown> | null;
        };
        /**
         * GoBD immutability (DE companies): an issued document must never be
         * retro-edited - corrections go through storno or a credit note. When
         * set, any save against a non-draft document is rejected.
         */
        lockIssuedContent?: boolean;
    },
) {
    const existing = options.existingDocument ?? null;

    // GoBD lock first, and against PERSISTED state - the caller's
    // existingDocument copy may be missing or stale, and an issued document
    // must stay immutable even then. An unverifiable update (a documentId
    // whose row cannot be found) is rejected rather than trusted.
    if (options.lockIssuedContent) {
        const persisted = options.documentId
            ? toAppRows<EvoluDocumentRow>(evolu.getQueryRows(allDocumentsQuery))
                .find((row) => row.id === options.documentId) ?? existing
            : existing;
        if (options.documentId && !persisted) {
            return { ok: false as const, error: "issued_locked" };
        }
        if (persisted && persisted.status !== "draft") {
            return { ok: false as const, error: "issued_locked" };
        }
    }

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
        const result = evolu.upsert("document", {
            id: docId,
            companyId,
            ...fields.value,
        });
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

    // Edit + re-freeze: saving an already issued document appends a new
    // snapshot version reflecting the edited content. Best-effort - the save
    // itself has succeeded; a failed re-freeze keeps the previous version.
    if (
        existing
        && existing.status !== "draft"
        && numberForSave
        && options.refreezeContext?.company
    ) {
        const content = buildIssuedSnapshotContentV1({
            company: options.refreezeContext.company,
            contact: options.refreezeContext.contact,
            document: {
                type: payload.type,
                title: fields.value.title,
                number: numberForSave,
                variable_symbol: fields.value.variableSymbol,
                constant_symbol: fields.value.constantSymbol,
                specific_symbol: fields.value.specificSymbol,
                issue_date: fields.value.issueDate,
                delivery_date: fields.value.deliveryDate,
                due_date: fields.value.dueDate,
                currency: fields.value.currency,
                subtotal: totals.subtotal,
                tax_total: totals.taxTotal,
                discount_percent: fields.value.discountPercent,
                total: totals.total,
                note_above_lines: fields.value.noteAboveLines,
                note_footer: fields.value.noteFooter,
                internal_note: fields.value.internalNote,
                pdf_locale: fields.value.pdfLocale,
                pdf_bank_qr: fields.value.pdfBankQr ?? null,
                pdf_show_signature: payload.pdf_show_signature,
                pdf_show_payment_info: payload.pdf_show_payment_info,
                payment_bank_enabled: payload.payment_bank_enabled,
                payment_btc_enabled: payload.payment_btc_enabled,
            },
            lines: snapshotLinesFromPayload(payload, totals.lineTotals),
        });
        if (content.ok) {
            insertIssuedDocumentSnapshot(evolu, docId, content.value);
        }
    }

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
        documents: toAppRows<EvoluDocumentRow>(documents),
        series: toAppRows<EvoluNumberSeriesRow>(series),
    };
}

async function waitForDraftDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
): Promise<EvoluDocumentRow | null> {
    for (let attempt = 0; attempt < 8; attempt += 1) {
        const documents = toAppRows<EvoluDocumentRow>((await evolu.loadQuery(allDocumentsQuery)));
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
    const [lineRows, contactRows] = await Promise.all([
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allContactsQuery),
    ]);
    const snapshotContext: IssueSnapshotContext = {
        lines: toAppRows<EvoluDocumentLineRow>(lineRows),
        contact:
            (toAppRows<EvoluContactRow>(contactRows).find((c) => c.id === draft.contactId)
                ?? null),
    };

    // Server number allocator (audit F3): the number is reserved atomically
    // on the server, keyed by the document id so a retried issue gets the
    // same number back. There is deliberately NO local fallback - definitive
    // issuing requires being online; offline the document stays a draft.
    // One local "now" for the period: the server runs in UTC, the user sees
    // their own calendar (first hours of a year / month).
    const periodDate = new Date();
    const localHigh = localHighCounterForStoreBridge(
        company.id,
        draft.documentType,
        documents,
        series,
    );
    const reserved = await reserveIssueNumber(
        {
            legal_name: company.legalName ?? null,
            registration_number: company.registrationNumber ?? null,
            jurisdiction: company.jurisdiction ?? null,
            country: company.country ?? null,
            default_currency: company.defaultCurrency ?? null,
            bridge_company_id: companyShareInfo(company.id)?.bridgeCompanyId ?? null,
        },
        draft.documentType,
        documentId,
        localHigh,
        periodDate,
    );
    if (!reserved.ok) {
        return { ok: false as const, error: reserved.error };
    }
    // A voided reservation never becomes a document number.
    if (reserved.value.status === "voided") {
        return { ok: false as const, error: "reserve_failed" as const };
    }

    // The reserved COUNTER is authoritative; the visible number is formatted
    // with the LOCAL series format. The server bridge sequence may carry a
    // different default (e.g. INVYYYYNNNN) than the user's configured series.
    // A retried issue gets its original reservation back - format it in the
    // reservation's period, not today's (a new-year retry of a Dec 31
    // reservation must not produce a new-year number the new year reissues).
    const number = formatNumberFromStoreCounter(
        company.id,
        draft.documentType,
        reserved.value.counter,
        series,
        dateForPeriodKey(reserved.value.periodKey, periodDate),
    );

    // Title and VS were rendered from the preview (or inherited from a
    // duplicated / converted source) - align them with the real number. An
    // empty title (stored as the "Document" fallback) gets the type's title.
    const seriesFormat = resolveDefaultSeries(series, company.id, draft.documentType)?.format ?? "YYYYNNNN";
    const derived = rederiveNumberDerivedFields(seriesFormat, number, reserved.value.counter, {
        title: isFallbackTitle(draft.title) ? defaultDocumentTitle(draft.documentType, number) : draft.title,
        variableSymbol: draft.variableSymbol,
    });
    let issuedDraft = draft;
    if (derived.title !== (draft.title ?? null) && derived.title) {
        const titleUpdate = evolu.update("document", { id: documentId, title: derived.title });
        if (titleUpdate.ok) {
            issuedDraft = { ...draft, title: derived.title };
        }
    }

    const result = await applyReservedNumberToLocalDocumentAsync(
        evolu,
        documentId,
        company.id,
        draft.documentType,
        number,
        series,
        derived.variableSymbol,
        {
            company,
            doc: issuedDraft,
            allDocuments: documents,
            ...snapshotContext,
        },
    );
    if (result.ok) {
        // Best-effort content commitment; the reservation stays "reserved"
        // on the server when this fails and can be confirmed on a retry.
        void confirmIssueNumber(
            reserved.value.bridgeCompanyId,
            draft.documentType,
            documentId,
            "snapshotPayloadJson" in result ? result.snapshotPayloadJson : null,
            ISSUED_SNAPSHOT_FORMAT_VERSION,
        );
        markFinalInvoicePaidFromProforma(evolu, draft, documents);
    }
    return result;
}

/** saveLocalDocument stores an empty title as this literal. */
const FALLBACK_DOCUMENT_TITLE = "Document";

export function isFallbackTitle(title: string | null | undefined): boolean {
    const value = String(title ?? "").trim();
    return value === "" || value === FALLBACK_DOCUMENT_TITLE;
}

const TITLE_PREFIX_KEYS: Partial<Record<string, string>> = {
    invoice: "invoicing.invoice_title_prefix",
    proforma: "invoicing.proforma_title_prefix",
    quote: "invoicing.quote_title_prefix",
    credit_note: "invoicing.credit_note_title_prefix",
};

/** "<type prefix> <number>" - the form's own default title. */
export function defaultDocumentTitle(documentType: string, number: string): string | null {
    const key = TITLE_PREFIX_KEYS[documentType];
    return key ? `${String(i18n.global.t(key))} ${number}` : null;
}

/**
 * A final invoice settles an already PAID proforma: it is issued as paid
 * with the proforma's payment (server parity - BusinessDocumentFromProforma
 * Service). It used to stay "issued", showing a payment QR for money the
 * customer had already sent.
 */
function markFinalInvoicePaidFromProforma(
    evolu: Evolu<InvoicingLocalSchema>,
    draft: EvoluDocumentRow,
    documents: EvoluDocumentRow[],
) {
    if (draft.documentType !== "invoice" || !draft.sourceDocumentId) return;
    const proforma = documents.find((row) => row.id === draft.sourceDocumentId);
    if (!proforma || proforma.documentType !== "proforma" || proforma.status !== "paid") return;

    const amountPaid = proforma.amountPaid ?? proforma.total ?? "0";
    const result = evolu.update("document", {
        id: draft.id,
        status: "paid",
        paidAt: proforma.paidAt ?? new Date().toISOString(),
        amountPaid,
    });
    if (result.ok) {
        logDocumentEvent(evolu, draft.id, "business_document.marked_paid", {
            amount_paid: Number(amountPaid),
            source: "proforma",
        });
    }
}

export type ReservedIssueSnapshotContext = IssueSnapshotContext & {
    company: EvoluCompanyRow;
    doc: EvoluDocumentRow;
    allDocuments: EvoluDocumentRow[];
};

export function applyReservedNumberToLocalDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    companyId: CompanyId,
    documentType: DocumentType,
    number: string,
    allSeries: EvoluNumberSeriesRow[],
    variableSymbol?: string | null,
    snapshotContext?: ReservedIssueSnapshotContext,
) {
    const quoteStatus = documentType === "quote" ? "pending" : null;

    // Snapshot before status flip (audit F2): a document may only become
    // issued once its frozen copy is persisted.
    let snapshotPayloadJson: string | null = null;
    if (snapshotContext) {
        const snapshot = writeIssueSnapshot(
            evolu,
            snapshotContext.doc,
            number,
            documentVariableSymbol(variableSymbol, number),
            snapshotContext.company,
            snapshotContext,
            snapshotContext.allDocuments,
        );
        if (!snapshot.ok) return snapshot;
        snapshotPayloadJson = snapshot.payloadJson;
    }

    const result = evolu.update("document", {
        id: documentId,
        status: "issued",
        number,
        variableSymbol: documentVariableSymbol(variableSymbol, number),
        quoteStatus,
    });
    if (!result.ok) {
        return result;
    }
    // Advance the local series counter only after the document is issued, so
    // a failed snapshot or update leaves the counter untouched.
    syncLocalSeriesCounterFromIssuedNumber(evolu, companyId, documentType, number, allSeries);
    logDocumentEvent(evolu, documentId, "business_document.issued", { number });
    return { ok: true as const, value: result.value, snapshotPayloadJson };
}

export async function applyReservedNumberToLocalDocumentAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    companyId: CompanyId,
    documentType: DocumentType,
    number: string,
    allSeries: EvoluNumberSeriesRow[],
    variableSymbol?: string | null,
    snapshotContext?: ReservedIssueSnapshotContext,
) {
    // Callers without local row context (e.g. Woo inbox import) get it loaded
    // here so their issues also freeze a snapshot. Best-effort: a missing
    // company row falls back to issuing without a snapshot (legacy behavior).
    let context = snapshotContext;
    if (!context) {
        const doc = await waitForDraftDocument(evolu, documentId);
        if (doc) {
            const [lineRows, contactRows, companyRows, documents] = await Promise.all([
                evolu.loadQuery(allDocumentLinesQuery),
                evolu.loadQuery(allContactsQuery),
                evolu.loadQuery(allCompaniesDetailQuery),
                evolu.loadQuery(allDocumentsQuery),
            ]);
            const company = toAppRows<EvoluCompanyRow>(companyRows).find(
                (row) => row.id === companyId,
            );
            if (company) {
                context = {
                    company,
                    doc,
                    allDocuments: toAppRows<EvoluDocumentRow>(documents),
                    lines: toAppRows<EvoluDocumentLineRow>(lineRows),
                    contact:
                        (toAppRows<EvoluContactRow>(contactRows).find(
                            (c) => c.id === doc.contactId,
                        ) ?? null),
                };
            }
        }
    }

    const result = applyReservedNumberToLocalDocument(
        evolu,
        documentId,
        companyId,
        documentType,
        number,
        allSeries,
        variableSymbol,
        context,
    );
    await finalizeIssueStock(evolu, documentId, result);
    return result;
}

export function getLocalDocumentApi(
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    policy: LocalDocumentDeletionPolicy = {},
): Record<string, unknown> | null {
    const doc = documents.find((d) => d.id === documentId);
    if (!doc) return null;
    const docLines = lines.filter((l) => l.documentId === documentId);
    return evoluDocumentToApi(doc, docLines, documents, policy);
}

export function deleteLocalDocument(evolu: Evolu<InvoicingLocalSchema>, documentId: DocumentId) {
    const result = evolu.update("document", { id: documentId, isDeleted: sqliteTrue });
    if (result.ok) {
        logDocumentEvent(evolu, documentId, "business_document.deleted");
    }
    return result;
}

export async function deleteLocalDocumentAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    documents: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
    policy: LocalDocumentDeletionPolicy = {},
) {
    const doc = documents.find((row) => row.id === documentId);

    if (
        doc
        && doc.status !== "draft"
        && policy.jurisdictionByCompanyId?.get(String(doc.companyId)) === "eu_de"
    ) {
        return { ok: false as const, error: "issued_locked" };
    }

    // The same guard as the list's can_delete and the bulk path - the UI
    // flag may be stale (a credit note synced in from another member, a
    // newer invoice issued on another device).
    const { canDeleteLocalDocument, holdsSequenceNumber } = await import("./documentBulkLocal");
    if (doc && !canDeleteLocalDocument(doc, documents, { ...policy, allSeries: policy.allSeries ?? allSeries })) {
        return { ok: false as const, error: "not_deletable" };
    }

    // Gapless numbering (P3): a numbered document (issued, paid or
    // cancelled) frees its number on the server allocator FIRST - only
    // then is it deleted locally. A failed release (offline, or the number
    // is no longer the series top) blocks the deletion so the sequence can
    // never end up with a hole.
    const numbered = doc ? holdsSequenceNumber(doc) : false;
    if (doc && numbered) {
        const { releaseIssuedNumber } = await import("./numberReleaseBridge");
        const release = await releaseIssuedNumber(
            doc.companyId,
            String(doc.documentType),
            String(doc.number),
            String(doc.id),
        );
        if (!release.ok) {
            return { ok: false as const, error: release.error };
        }
    }

    // Return the stock before the row disappears (the reversal reads the
    // live document; idempotent - a cancelled document was reversed).
    if (doc && (doc.status === "issued" || doc.status === "paid")) {
        await reverseDocumentStockOnCancelAsync(evolu, documentId);
    }

    const result = deleteLocalDocument(evolu, documentId);
    if (!result.ok || !doc || !numbered) {
        return result;
    }
    const remaining = documents.filter((row) => row.id !== documentId);
    syncNumberSeriesCounterFromDocuments(
        evolu,
        doc.companyId,
        doc.documentType,
        remaining,
        allSeries,
        { releasedNumbers: [String(doc.number)] },
    );
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
    // Paid documents deducted stock at issue too (the server reverses both).
    if (!result.ok || (doc?.status !== "issued" && doc?.status !== "paid")) {
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

function addDaysIso(isoDate: string, days: number): string {
    const [year, month, day] = isoDate.split("-").map(Number);
    return localIsoDate(new Date(year, month - 1, day + days));
}

function daysBetweenIso(from: string, to: string): number | null {
    const a = /^(\d{4})-(\d{2})-(\d{2})/.exec(from);
    const b = /^(\d{4})-(\d{2})-(\d{2})/.exec(to);
    if (!a || !b) return null;
    const start = Date.UTC(Number(a[1]), Number(a[2]) - 1, Number(a[3]));
    const end = Date.UTC(Number(b[1]), Number(b[2]) - 1, Number(b[3]));
    return Math.round((end - start) / 86_400_000);
}

/**
 * A copy (duplicate, invoice from a quote, final invoice from a proforma)
 * is a NEW document: it must not carry the source's number-derived title
 * and variable symbol (the issued copy printed the source's number and was
 * bank-matched by the source's VS), nor its dates. Today's local date is
 * the issue date; the source's payment term (days between issue and due
 * date) carries over; a delivery date becomes today. The empty title is
 * filled from the type and the real number at issue.
 */
export function prepareCopiedPayload(
    payload: DocumentSavePayload,
    today: string = localIsoDate(),
): DocumentSavePayload {
    const term = payload.issue_date && payload.due_date
        ? daysBetweenIso(payload.issue_date, payload.due_date)
        : null;
    return {
        ...payload,
        title: "",
        variable_symbol: "",
        issue_date: today,
        due_date: term !== null && term >= 0 ? addDaysIso(today, term) : "",
        delivery_date: payload.delivery_date ? today : "",
    };
}

export function createLocalInvoiceFromQuote(
    evolu: Evolu<InvoicingLocalSchema>,
    quoteId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    buildPayloadFromDoc: (doc: Record<string, unknown>) => DocumentSavePayload,
    saveOptions: Parameters<typeof saveLocalDocument>[3],
) {
    // Domain guards (server parity): the UI checks these too, but its state
    // can be stale across tabs / relay sync.
    const doc = documents.find((d) => d.id === quoteId);
    if (!doc) return { ok: false as const, error: "not_found" };
    if (doc.documentType !== "quote") return { ok: false as const, error: "not_quote" };
    if (!doc.number) return { ok: false as const, error: "not_issued" };
    if (doc.quoteStatus !== "approved") return { ok: false as const, error: "not_approved" };
    if (hasActiveDerivedInvoice(doc.id, documents)) {
        return { ok: false as const, error: "already_exists" };
    }
    const api = getLocalDocumentApi(quoteId, documents, lines);
    if (!api) return { ok: false as const, error: "not_found" };
    const payload = prepareCopiedPayload(buildPayloadFromDoc(api));
    payload.type = "invoice";
    // Quotes are stored with payment off - an invoice asks for payment.
    payload.pdf_show_payment_info = true;
    payload.payment_bank_enabled = true;
    payload.payment_btc_enabled = false;
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

function hasActiveDerivedInvoice(sourceId: DocumentId, documents: EvoluDocumentRow[]): boolean {
    return documents.some(
        (row) => row.sourceDocumentId === sourceId
            && row.documentType === "invoice"
            && row.status !== "cancelled",
    );
}

export function createLocalFinalInvoiceFromProforma(
    evolu: Evolu<InvoicingLocalSchema>,
    proformaId: DocumentId,
    documents: EvoluDocumentRow[],
    lines: EvoluDocumentLineRow[],
    buildPayloadFromDoc: (doc: Record<string, unknown>) => DocumentSavePayload,
    saveOptions: Parameters<typeof saveLocalDocument>[3],
    options: { variableSymbolFromProforma?: boolean } = {},
) {
    const doc = documents.find((d) => d.id === proformaId);
    if (!doc) return { ok: false as const, error: "not_found" };
    if (doc.documentType !== "proforma") return { ok: false as const, error: "not_proforma" };
    if (!doc.number || doc.status !== "paid") return { ok: false as const, error: "not_paid" };
    if (hasActiveDerivedInvoice(doc.id, documents)) {
        return { ok: false as const, error: "already_exists" };
    }
    const api = getLocalDocumentApi(proformaId, documents, lines);
    if (!api) return { ok: false as const, error: "not_found" };
    const payload = prepareCopiedPayload(buildPayloadFromDoc(api));
    payload.type = "invoice";
    // Settled through the proforma (it is issued as paid): no payment block,
    // QR or BTC checkout for money already received.
    payload.pdf_show_payment_info = false;
    payload.payment_bank_enabled = false;
    payload.payment_btc_enabled = false;
    if (options.variableSymbolFromProforma && doc.variableSymbol) {
        payload.variable_symbol = doc.variableSymbol;
    }
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

    // Local calendar date - toISOString() is UTC (the day before around
    // midnight in Central Europe).
    const today = localIsoDate();
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
        pdf_bank_qr: (doc.pdf_bank_qr as string | null | undefined) ?? null,
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
