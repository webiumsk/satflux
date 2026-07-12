import { booleanToSqliteBoolean, maxLength, NonEmptyString, sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { EvoluCompanyRow } from "./companyMap";
import {
    calcDocumentTotals,
    issueLocalDocumentAsync,
    saveLocalDocument,
    type DocumentLinePayload,
} from "./documentCrud";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "./documentMap";
import { previewNextDocumentNumber, documentVariableSymbol } from "./documentNumber";
import { allDocumentsQuery } from "./client";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { advanceRecurringNextDate } from "./recurringNextDate";
import type { EvoluRecurringProfileLineRow, EvoluRecurringProfileRow } from "./recurringMap";
import { parseRecurringTags } from "./recurringMap";
import { resolveRecurringPlaceholders } from "./recurringPlaceholder";
import type {
    CompanyId,
    ContactId,
    DocumentId,
    InvoicingLocalSchema,
    RecurringDeliveryDateMode,
    RecurringDocType,
    RecurringInterval,
    RecurringProfileId,
    RecurringProfileLineId,
} from "./schema";

export type RecurringLinePayload = {
    name: string;
    description: string | null;
    quantity: number;
    unit: string;
    unit_price: number;
    line_discount_percent: number;
    tax_rate: number;
};

export type RecurringProfileSavePayload = {
    document_type: RecurringDocType;
    company_contact_id: string;
    store_id: string;
    is_active: boolean;
    recurrence_interval: RecurringInterval;
    first_issue_date: string;
    next_issue_date: string;
    repeat_indefinitely: boolean;
    ends_at?: string | null;
    issue_last_day_of_month: boolean;
    title: string;
    variable_symbol: string;
    constant_symbol: string;
    specific_symbol?: string;
    payment_terms_days: number;
    delivery_date_mode: RecurringDeliveryDateMode;
    currency: string;
    discount_percent: number;
    note_above_lines: string;
    note_footer: string;
    internal_note: string;
    pdf_locale: string;
    pdf_show_signature: boolean;
    pdf_show_payment_info: boolean;
    payment_btc_enabled: boolean;
    payment_bank_enabled: boolean;
    send_email_after_issue: boolean;
    email_bcc?: string | null;
    tags: string[];
    lines: RecurringLinePayload[];
};

const Opt1000 = maxLength(1000)(NonEmptyString);
const LineNameType = maxLength(255)(NonEmptyString);

function parseOpt(value: string | null | undefined, type: ReturnType<typeof maxLength>) {
    if (value == null || value.trim() === "") return { ok: true as const, value: null };
    return type.from(value.trim());
}

function addDays(isoDate: string, days: number): string {
    const date = new Date(`${isoDate.slice(0, 10)}T12:00:00`);
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
}

function syncRecurringLines(
    evolu: Evolu<InvoicingLocalSchema>,
    profileId: RecurringProfileId,
    lines: RecurringLinePayload[],
    lineTotals: string[],
    existingLines: EvoluRecurringProfileLineRow[],
): { ok: true } | { ok: false; error: string } {
    const existing = existingLines.filter((row) => row.recurringProfileId === profileId);
    for (const row of existing) {
        const deleted = evolu.update("recurringProfileLine", {
            id: row.id as RecurringProfileLineId,
            isDeleted: sqliteTrue,
        });
        if (!deleted.ok) return deleted;
    }

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        const name = LineNameType.from(line.name.trim() || "Item");
        if (!name.ok) return name;
        const inserted = evolu.insert("recurringProfileLine", {
            recurringProfileId: profileId,
            sortOrder: String(index),
            name: name.value,
            description: line.description?.trim() || null,
            quantity: String(line.quantity ?? 0),
            unit: line.unit || "pcs",
            unitPrice: String(line.unit_price ?? 0),
            lineDiscountPercent: String(line.line_discount_percent ?? 0),
            taxRate: String(line.tax_rate ?? 0),
            lineTotal: lineTotals[index] ?? "0.00",
        });
        if (!inserted.ok) return inserted;
    }

    return { ok: true };
}

export function saveLocalRecurringProfile(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: RecurringProfileSavePayload,
    options: {
        profileId?: RecurringProfileId;
        defaultVat: number;
        lineTaxApplies: (line: RecurringLinePayload) => boolean;
        lineTaxRate: (line: RecurringLinePayload) => number;
        existingLines: EvoluRecurringProfileLineRow[];
    },
) {
    if (!payload.lines.length) {
        return { ok: false as const, error: "lines_required" };
    }

    const docLines: DocumentLinePayload[] = payload.lines.map((line) => ({
        name: line.name,
        description: line.description,
        quantity: line.quantity,
        unit: line.unit,
        unit_price: line.unit_price,
        line_discount_percent: line.line_discount_percent,
        tax_rate: line.tax_rate,
        company_stock_item_id: null,
        company_warehouse_id: null,
    }));

    const totals = calcDocumentTotals(
        docLines,
        payload.discount_percent,
        options.defaultVat,
        options.lineTaxApplies,
        options.lineTaxRate,
    );

    const title = parseOpt(payload.title, Opt1000);
    if (!title.ok) return title;
    const contactId = payload.company_contact_id
        ? (payload.company_contact_id as ContactId)
        : null;

    const fields = {
        companyId,
        contactId,
        storeId: payload.store_id || null,
        documentType: payload.document_type,
        isActive: booleanToSqliteBoolean(payload.is_active),
        recurrenceInterval: payload.recurrence_interval,
        firstIssueDate: payload.first_issue_date.slice(0, 10),
        nextIssueDate: payload.next_issue_date.slice(0, 10),
        endsAt: payload.repeat_indefinitely ? null : (payload.ends_at?.slice(0, 10) ?? null),
        repeatIndefinitely: booleanToSqliteBoolean(payload.repeat_indefinitely),
        issueLastDayOfMonth: booleanToSqliteBoolean(payload.issue_last_day_of_month),
        title: title.value,
        variableSymbol: payload.variable_symbol || null,
        constantSymbol: payload.constant_symbol || null,
        specificSymbol: payload.specific_symbol || null,
        paymentTermsDays: String(payload.payment_terms_days ?? 14),
        deliveryDateMode: payload.delivery_date_mode,
        currency: payload.currency || "EUR",
        discountPercent: String(payload.discount_percent ?? 0),
        subtotal: totals.subtotal,
        taxTotal: totals.taxTotal,
        total: totals.total,
        noteAboveLines: payload.note_above_lines || null,
        noteFooter: payload.note_footer || null,
        internalNote: payload.internal_note || null,
        pdfLocale: payload.pdf_locale || "sk",
        pdfShowSignature: booleanToSqliteBoolean(payload.pdf_show_signature),
        pdfShowPaymentInfo: booleanToSqliteBoolean(payload.pdf_show_payment_info),
        paymentBtcEnabled: booleanToSqliteBoolean(payload.payment_btc_enabled),
        paymentBankEnabled: booleanToSqliteBoolean(payload.payment_bank_enabled),
        sendEmailAfterIssue: booleanToSqliteBoolean(payload.send_email_after_issue),
        emailBcc: payload.email_bcc?.trim() || null,
        tagsJson: payload.tags.length ? JSON.stringify(payload.tags) : null,
    };

    let profileId = options.profileId;
    if (profileId) {
        const result = evolu.update("recurringProfile", { id: profileId, ...fields });
        if (!result.ok) return result;
    } else {
        const result = evolu.insert("recurringProfile", fields);
        if (!result.ok) return result;
        profileId = result.value.id;
    }

    const linesResult = syncRecurringLines(
        evolu,
        profileId,
        payload.lines,
        totals.lineTotals,
        options.existingLines,
    );
    if (!linesResult.ok) return linesResult;
    return { ok: true as const, value: { id: profileId } };
}

export function deleteLocalRecurringProfile(
    evolu: Evolu<InvoicingLocalSchema>,
    profileId: RecurringProfileId,
    existingLines: EvoluRecurringProfileLineRow[],
) {
    for (const line of existingLines.filter((row) => row.recurringProfileId === profileId)) {
        evolu.update("recurringProfileLine", { id: line.id as RecurringProfileLineId, isDeleted: sqliteTrue });
    }
    return evolu.update("recurringProfile", { id: profileId, isDeleted: sqliteTrue });
}

export async function generateLocalRecurringDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    profileId: RecurringProfileId,
    company: EvoluCompanyRow,
    profileRows: EvoluRecurringProfileRow[],
    profileLineRows: EvoluRecurringProfileLineRow[],
    documentRows: EvoluDocumentRow[],
    documentLineRows: EvoluDocumentLineRow[],
    seriesRows: EvoluNumberSeriesRow[],
    options: {
        defaultVat: number;
        lineTaxApplies: (line: DocumentLinePayload) => boolean;
        lineTaxRate: (line: DocumentLinePayload) => number;
        issueDate?: string;
    },
): Promise<{ ok: true; value: { documentId: DocumentId; number: string } } | { ok: false; error: string }> {
    const profile = profileRows.find((row) => row.id === profileId);
    if (!profile) return { ok: false, error: "not_found" };

    const issueDate = (options.issueDate ?? profile.nextIssueDate ?? new Date().toISOString().slice(0, 10)).slice(
        0,
        10,
    );
    const previewNumber = previewNextDocumentNumber(
        documentRows,
        company.id,
        profile.documentType,
        null,
        seriesRows,
    );
    const vsTemplate = profile.variableSymbol || "#VARIABLE_SYMBOL#";
    const dueDate = addDays(issueDate, parseInt(profile.paymentTermsDays || "14", 10) || 14);
    const deliveryDate = profile.deliveryDateMode === "on_issue" ? issueDate : "";

    const lines = profileLineRows
        .filter((line) => line.recurringProfileId === profileId)
        .sort((a, b) => Number(a.sortOrder ?? 0) - Number(b.sortOrder ?? 0));

    const documentLines: DocumentLinePayload[] = lines.map((line) => ({
        name: resolveRecurringPlaceholders(line.name, issueDate, previewNumber, vsTemplate) || line.name,
        description: resolveRecurringPlaceholders(line.description, issueDate, previewNumber, vsTemplate),
        quantity: parseFloat(line.quantity || "0") || 0,
        unit: line.unit || "pcs",
        unit_price: parseFloat(line.unitPrice || "0") || 0,
        line_discount_percent: parseFloat(line.lineDiscountPercent || "0") || 0,
        tax_rate: parseFloat(line.taxRate || "0") || 0,
        company_stock_item_id: null,
        company_warehouse_id: null,
    }));

    if (!documentLines.length) return { ok: false, error: "lines_required" };

    const savePayload = {
        type: profile.documentType,
        company_contact_id: profile.contactId || "",
        store_id: profile.storeId || "",
        title:
            resolveRecurringPlaceholders(profile.title, issueDate, previewNumber, vsTemplate)
            || profile.title
            || "Document",
        issue_date: issueDate,
        delivery_date: deliveryDate,
        due_date: dueDate,
        variable_symbol:
            documentVariableSymbol(
                resolveRecurringPlaceholders(vsTemplate, issueDate, previewNumber, vsTemplate),
                previewNumber,
            ) || "",
        constant_symbol: profile.constantSymbol || "",
        specific_symbol: profile.specificSymbol || "",
        currency: profile.currency || "EUR",
        discount_percent: parseFloat(profile.discountPercent || "0") || 0,
        note_above_lines:
            resolveRecurringPlaceholders(profile.noteAboveLines, issueDate, previewNumber, vsTemplate) || "",
        note_footer: profile.noteFooter || "",
        internal_note: profile.internalNote || "",
        pdf_locale: profile.pdfLocale || "sk",
        pdf_show_signature: profile.pdfShowSignature === 1,
        pdf_show_payment_info: profile.pdfShowPaymentInfo === 1,
        payment_bank_enabled: profile.paymentBankEnabled !== 0,
        payment_btc_enabled: profile.paymentBtcEnabled === 1,
        tags: parseRecurringTags(profile.tagsJson),
        lines: documentLines,
    };

    const saveResult = saveLocalDocument(evolu, company.id, savePayload, {
        defaultVat: options.defaultVat,
        lineTaxApplies: options.lineTaxApplies,
        lineTaxRate: options.lineTaxRate,
        existingLines: documentLineRows,
    });
    if (!saveResult.ok) return saveResult;

    const issueResult = await issueLocalDocumentAsync(
        evolu,
        saveResult.value.id,
        company,
    );
    if (!issueResult.ok) {
        return {
            ok: false,
            error: typeof issueResult.error === "string" ? issueResult.error : "issue",
        };
    }

    await evolu.loadQuery(allDocumentsQuery);
    const issuedDocuments = (await evolu.loadQuery(allDocumentsQuery)) as EvoluDocumentRow[];
    const doc = issuedDocuments.find((row) => row.id === saveResult.value.id);
    const issuedNumber = doc?.number || previewNumber;

    if (profile.title) {
        const resolvedTitle = resolveRecurringPlaceholders(
            profile.title,
            issueDate,
            issuedNumber,
            doc?.variableSymbol ?? issuedNumber,
        );
        if (resolvedTitle) {
            evolu.update("document", { id: saveResult.value.id, title: resolvedTitle });
        }
    }

    evolu.update("recurringProfile", {
        id: profileId,
        lastGeneratedDocumentId: saveResult.value.id,
        lastGeneratedAt: new Date().toISOString(),
        nextIssueDate: advanceRecurringNextDate(
            profile.recurrenceInterval as RecurringInterval,
            issueDate,
            profile.issueLastDayOfMonth === 1,
        ),
    });

    return { ok: true, value: { documentId: saveResult.value.id, number: issuedNumber } };
}
