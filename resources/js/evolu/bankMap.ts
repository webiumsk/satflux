import { resolvedBankDirection } from "./bankDirectionGuesser";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluExpenseRow } from "./expenseMap";
import type {
    BankImportBatchId,
    BankImportSource,
    BankMatchType,
    BankTransactionDirection,
    BankTransactionId,
    BankTransactionMatchId,
    BankTransactionMatchStatus,
    CompanyId,
    DocumentId,
    ExpenseId,
} from "./schema";

export type EvoluBankImportBatchRow = {
    id: BankImportBatchId;
    companyId: CompanyId;
    source: BankImportSource;
    filename: string | null;
    rowCount: string | null;
    importedCount: string | null;
    skippedDuplicates: string | null;
    autoMatchedCount: string | null;
    createdAt?: string | null;
};

export type EvoluBankTransactionRow = {
    id: BankTransactionId;
    companyId: CompanyId;
    bankImportBatchId: BankImportBatchId | null;
    bookedAt: string | null;
    amount: string | null;
    currency: string | null;
    direction: BankTransactionDirection;
    matchStatus: BankTransactionMatchStatus;
    businessExpenseId: ExpenseId | null;
    variableSymbol: string | null;
    constantSymbol: string | null;
    specificSymbol: string | null;
    counterpartyName: string | null;
    counterpartyIban: string | null;
    reference: string | null;
    bankTransactionId: string | null;
    dedupeHash: string | null;
    source: BankImportSource;
};

export type EvoluBankTransactionMatchRow = {
    id: BankTransactionMatchId;
    bankTransactionId: BankTransactionId;
    businessDocumentId: DocumentId;
    matchedAmount: string | null;
    matchType: BankMatchType;
    matchedAt: string | null;
};

export type BankTxApiRow = {
    id: string;
    booked_at: string;
    amount: string;
    currency: string;
    direction: string;
    variable_symbol?: string | null;
    counterparty_name?: string | null;
    reference?: string | null;
    match_status: string;
    match?: {
        id: string;
        matched_amount: string;
        match_type: string;
        matched_at: string | null;
        document?: {
            id: string;
            number?: string | null;
            status?: string;
            type?: string;
        } | null;
    };
    expense?: {
        id: string;
        internal_number: string;
        title?: string | null;
        status?: string;
        total?: string | null;
        currency?: string | null;
    } | null;
};

export type BankBatchApiRow = {
    id: string;
    source: string;
    filename?: string | null;
    row_count: number;
    imported_count: number;
    skipped_duplicates: number;
    auto_matched_count: number;
};

export type BankSummarySection = {
    currency: string;
    credit_count: number;
    credit_total: string;
    debit_count: number;
    debit_total: string;
    balance: string;
};

export type BankSummary = {
    credit_count: number;
    credit_total: string | null;
    debit_count: number;
    debit_total: string | null;
    balance: string | null;
    currency: string | null;
    by_currency: BankSummarySection[];
    account_balance?: {
        amount: string;
        currency: string;
        as_of: string;
    } | null;
};

function formatMoney(amount: number): string {
    return amount.toFixed(2);
}

function isBalanceSnapshot(row: EvoluBankTransactionRow): boolean {
    for (const value of [row.counterpartyName, row.reference]) {
        if (!value?.trim()) continue;
        const lower = value.toLowerCase();
        if (lower.includes("stav na ucte") || lower.includes("stav na účte")) {
            return true;
        }
    }
    return false;
}

export function isMovementTransaction(row: EvoluBankTransactionRow): boolean {
    return !isBalanceSnapshot(row);
}

export function evoluBankBatchToApi(row: EvoluBankImportBatchRow): BankBatchApiRow {
    return {
        id: row.id,
        source: row.source,
        filename: row.filename,
        row_count: parseInt(row.rowCount || "0", 10) || 0,
        imported_count: parseInt(row.importedCount || "0", 10) || 0,
        skipped_duplicates: parseInt(row.skippedDuplicates || "0", 10) || 0,
        auto_matched_count: parseInt(row.autoMatchedCount || "0", 10) || 0,
    };
}

export function evoluBankTxToApi(
    row: EvoluBankTransactionRow,
    match: EvoluBankTransactionMatchRow | null,
    document: EvoluDocumentRow | null,
    expense: EvoluExpenseRow | null,
): BankTxApiRow {
    const direction = resolvedBankDirection(row);
    const payload: BankTxApiRow = {
        id: row.id,
        booked_at: row.bookedAt || "",
        amount: row.amount || "0.00",
        currency: row.currency || "EUR",
        direction,
        variable_symbol: row.variableSymbol,
        counterparty_name: row.counterpartyName,
        reference: row.reference,
        match_status: row.matchStatus,
    };

    if (match && document) {
        payload.match = {
            id: match.id,
            matched_amount: match.matchedAmount || row.amount || "0.00",
            match_type: match.matchType,
            matched_at: match.matchedAt,
            document: {
                id: document.id,
                number: document.number,
                status: document.status,
                type: document.documentType,
            },
        };
    }

    if (expense) {
        payload.expense = {
            id: expense.id,
            internal_number: expense.internalNumber,
            title: expense.title,
            status: expense.status,
            total: expense.total,
            currency: expense.currency,
        };
    }

    return payload;
}

export function filterLocalBankTransactions(
    rows: EvoluBankTransactionRow[],
    companyId: CompanyId,
    matchStatus?: string,
): EvoluBankTransactionRow[] {
    return rows
        .filter((row) => row.companyId === companyId && isMovementTransaction(row))
        .filter((row) => !matchStatus || matchStatus === "all" || row.matchStatus === matchStatus)
        .sort((a, b) => (b.bookedAt || "").localeCompare(a.bookedAt || ""));
}

export function buildBankSummary(rows: EvoluBankTransactionRow[]): BankSummary {
    const byCurrency = new Map<
        string,
        { credit_count: number; credit_total: number; debit_count: number; debit_total: number }
    >();

    let latestSnapshot: EvoluBankTransactionRow | null = null;

    for (const row of rows) {
        if (isBalanceSnapshot(row)) {
            if (
                !latestSnapshot
                || (row.bookedAt || "") > (latestSnapshot.bookedAt || "")
            ) {
                latestSnapshot = row;
            }
            continue;
        }

        const currency = row.currency || "EUR";
        if (!byCurrency.has(currency)) {
            byCurrency.set(currency, {
                credit_count: 0,
                credit_total: 0,
                debit_count: 0,
                debit_total: 0,
            });
        }

        const entry = byCurrency.get(currency)!;
        const amount = Math.abs(parseFloat(row.amount || "0"));
        const direction = resolvedBankDirection(row);

        if (direction === "credit") {
            entry.credit_count++;
            entry.credit_total += amount;
        } else {
            entry.debit_count++;
            entry.debit_total += amount;
        }
    }

    const byCurrencyFormatted: BankSummarySection[] = [...byCurrency.entries()].map(
        ([currency, entry]) => ({
            currency,
            credit_count: entry.credit_count,
            credit_total: formatMoney(entry.credit_total),
            debit_count: entry.debit_count,
            debit_total: formatMoney(entry.debit_total),
            balance: formatMoney(entry.credit_total - entry.debit_total),
        }),
    );

    let creditCount = 0;
    let creditTotal = 0;
    let debitCount = 0;
    let debitTotal = 0;
    for (const entry of byCurrencyFormatted) {
        creditCount += entry.credit_count;
        creditTotal += parseFloat(entry.credit_total);
        debitCount += entry.debit_count;
        debitTotal += parseFloat(entry.debit_total);
    }

    const singleCurrency = byCurrencyFormatted.length === 1 ? byCurrencyFormatted[0].currency : null;

    return {
        credit_count: creditCount,
        credit_total: singleCurrency != null ? formatMoney(creditTotal) : null,
        debit_count: debitCount,
        debit_total: singleCurrency != null ? formatMoney(debitTotal) : null,
        balance: singleCurrency != null ? formatMoney(creditTotal - debitTotal) : null,
        currency: singleCurrency,
        by_currency: byCurrencyFormatted,
        account_balance: latestSnapshot
            ? {
                amount: formatMoney(Math.abs(parseFloat(latestSnapshot.amount || "0"))),
                currency: latestSnapshot.currency || "EUR",
                as_of: latestSnapshot.bookedAt || "",
            }
            : null,
    };
}

export function matchesDocumentVariableSymbol(
    document: EvoluDocumentRow,
    vs: string | null | undefined,
): boolean {
    if (!vs) return false;
    if (document.variableSymbol === vs) return true;
    const fromNumber = (document.number || "").replace(/\D/g, "");
    return fromNumber !== "" && fromNumber === vs;
}

export function documentAmountDue(document: EvoluDocumentRow): number {
    const total = parseFloat(document.total || "0");
    const paid = parseFloat(document.amountPaid || "0");
    return Math.max(0, total - paid);
}
