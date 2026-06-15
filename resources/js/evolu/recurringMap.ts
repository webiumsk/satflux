import type { CompanyId, ContactId, DocumentId, RecurringProfileId } from "./schema";

export type EvoluRecurringProfileLineRow = {
    id: string;
    recurringProfileId: RecurringProfileId;
    sortOrder: string | null;
    name: string;
    description: string | null;
    quantity: string | null;
    unit: string | null;
    unitPrice: string | null;
    lineDiscountPercent: string | null;
    taxRate: string | null;
    lineTotal: string | null;
};

export type EvoluRecurringProfileRow = {
    id: RecurringProfileId;
    companyId: CompanyId;
    contactId: ContactId | null;
    storeId: string | null;
    documentType: string;
    isActive: 0 | 1 | null;
    recurrenceInterval: string;
    firstIssueDate: string | null;
    nextIssueDate: string | null;
    endsAt: string | null;
    repeatIndefinitely: 0 | 1 | null;
    issueLastDayOfMonth: 0 | 1 | null;
    title: string | null;
    variableSymbol: string | null;
    constantSymbol: string | null;
    specificSymbol: string | null;
    paymentTermsDays: string | null;
    deliveryDateMode: string;
    currency: string | null;
    discountPercent: string | null;
    subtotal: string | null;
    taxTotal: string | null;
    total: string | null;
    noteAboveLines: string | null;
    noteFooter: string | null;
    internalNote: string | null;
    pdfLocale: string | null;
    pdfShowSignature: 0 | 1 | null;
    pdfShowPaymentInfo: 0 | 1 | null;
    paymentBtcEnabled: 0 | 1 | null;
    paymentBankEnabled: 0 | 1 | null;
    sendEmailAfterIssue: 0 | 1 | null;
    emailBcc: string | null;
    tagsJson: string | null;
    lastGeneratedDocumentId: DocumentId | null;
    lastGeneratedAt: string | null;
};

export type RecurringProfileApiRow = {
    id: string;
    company_id: string;
    company_contact_id: string | null;
    store_id: string | null;
    document_type: string;
    is_active: boolean;
    recurrence_interval: string;
    first_issue_date: string | null;
    next_issue_date: string | null;
    ends_at: string | null;
    repeat_indefinitely: boolean;
    issue_last_day_of_month: boolean;
    title: string | null;
    variable_symbol: string | null;
    constant_symbol: string | null;
    specific_symbol: string | null;
    payment_terms_days: number;
    delivery_date_mode: string;
    currency: string;
    discount_percent: string;
    subtotal: string;
    tax_total: string;
    total: string;
    note_above_lines: string | null;
    note_footer: string | null;
    internal_note: string | null;
    pdf_locale: string;
    pdf_show_signature: boolean;
    pdf_show_payment_info: boolean;
    payment_btc_enabled: boolean;
    payment_bank_enabled: boolean;
    send_email_after_issue: boolean;
    email_bcc: string | null;
    tags: string[];
    last_generated_document_id: string | null;
    last_generated_at: string | null;
    contact?: { id: string; name: string } | null;
    lines?: Array<{
        name: string;
        description: string | null;
        quantity: number;
        unit: string;
        unit_price: number;
        line_discount_percent: number;
        tax_rate: number;
        line_total: number;
    }>;
};

function sqliteBool(value: 0 | 1 | null | undefined): boolean {
    return value === 1;
}

function parseTags(json: string | null): string[] {
    if (!json?.trim()) return [];
    try {
        const parsed = JSON.parse(json);
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
        return [];
    }
}

export function filterRecurringProfiles(
    rows: EvoluRecurringProfileRow[],
    companyId: CompanyId,
    filter: "all" | "active" | "inactive",
): EvoluRecurringProfileRow[] {
    return rows
        .filter((row) => row.companyId === companyId)
        .filter((row) => {
            if (filter === "active") return sqliteBool(row.isActive);
            if (filter === "inactive") return !sqliteBool(row.isActive);
            return true;
        })
        .sort((a, b) => {
            const aDate = a.nextIssueDate ?? "";
            const bDate = b.nextIssueDate ?? "";
            return aDate.localeCompare(bDate);
        });
}

export function evoluRecurringProfileToApi(
    row: EvoluRecurringProfileRow,
    lines: EvoluRecurringProfileLineRow[],
    contact?: { id: string; name: string } | null,
): RecurringProfileApiRow {
    const profileLines = lines
        .filter((line) => line.recurringProfileId === row.id)
        .sort((a, b) => Number(a.sortOrder ?? 0) - Number(b.sortOrder ?? 0));

    return {
        id: row.id,
        company_id: row.companyId,
        company_contact_id: row.contactId,
        store_id: row.storeId,
        document_type: row.documentType,
        is_active: sqliteBool(row.isActive),
        recurrence_interval: row.recurrenceInterval,
        first_issue_date: row.firstIssueDate,
        next_issue_date: row.nextIssueDate,
        ends_at: row.endsAt,
        repeat_indefinitely: sqliteBool(row.repeatIndefinitely),
        issue_last_day_of_month: sqliteBool(row.issueLastDayOfMonth),
        title: row.title,
        variable_symbol: row.variableSymbol,
        constant_symbol: row.constantSymbol,
        specific_symbol: row.specificSymbol,
        payment_terms_days: parseInt(row.paymentTermsDays || "14", 10) || 14,
        delivery_date_mode: row.deliveryDateMode,
        currency: row.currency || "EUR",
        discount_percent: row.discountPercent || "0",
        subtotal: row.subtotal || "0",
        tax_total: row.taxTotal || "0",
        total: row.total || "0",
        note_above_lines: row.noteAboveLines,
        note_footer: row.noteFooter,
        internal_note: row.internalNote,
        pdf_locale: row.pdfLocale || "sk",
        pdf_show_signature: sqliteBool(row.pdfShowSignature),
        pdf_show_payment_info: sqliteBool(row.pdfShowPaymentInfo),
        payment_btc_enabled: sqliteBool(row.paymentBtcEnabled),
        payment_bank_enabled: sqliteBool(row.paymentBankEnabled),
        send_email_after_issue: sqliteBool(row.sendEmailAfterIssue),
        email_bcc: row.emailBcc,
        tags: parseTags(row.tagsJson),
        last_generated_document_id: row.lastGeneratedDocumentId,
        last_generated_at: row.lastGeneratedAt,
        contact: contact ?? null,
        lines: profileLines.map((line) => ({
            name: line.name,
            description: line.description,
            quantity: parseFloat(line.quantity || "0") || 0,
            unit: line.unit || "pcs",
            unit_price: parseFloat(line.unitPrice || "0") || 0,
            line_discount_percent: parseFloat(line.lineDiscountPercent || "0") || 0,
            tax_rate: parseFloat(line.taxRate || "0") || 0,
            line_total: parseFloat(line.lineTotal || "0") || 0,
        })),
    };
}
