import { sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import {
    allBankImportBatchesQuery,
    allBankTransactionMatchesQuery,
    allBankTransactionsQuery,
    allDocumentsQuery,
    allExpensesQuery,
} from "./client";
import { resolvedBankDirection } from "./bankDirectionGuesser";
import { parseCamt053BankStatement, supportsCamt053File } from "./bankCamt053Parser";
import { parseCsvBankStatement, supportsCsvBankFile, type ParsedBankRow } from "./bankCsvParser";
import { bankTransactionDedupeHash } from "./bankDedupe";
import {
    documentAmountDue,
    evoluBankBatchToApi,
    evoluBankTxToApi,
    matchesDocumentVariableSymbol,
    type EvoluBankImportBatchRow,
    type EvoluBankTransactionMatchRow,
    type EvoluBankTransactionRow,
} from "./bankMap";
import type { EvoluDocumentRow } from "./documentMap";
import { markLocalDocumentPaidWithAmount, unmarkLocalDocumentPaid } from "./documentCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { insertLocalExpense, nextLocalExpenseInternalNumber, type ExpenseSavePayload } from "./expenseCrud";
import type {
    BankImportBatchId,
    BankImportSource,
    BankMatchType,
    BankTransactionId,
    CompanyId,
    DocumentId,
    InvoicingLocalSchema,
} from "./schema";

const AMOUNT_TOLERANCE = 0.01;

export type BankPaymentContext = {
    batches: EvoluBankImportBatchRow[];
    transactions: EvoluBankTransactionRow[];
    matches: EvoluBankTransactionMatchRow[];
    documents: EvoluDocumentRow[];
    expenses: EvoluExpenseRow[];
};

export async function loadBankPaymentContext(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<BankPaymentContext> {
    const [batches, transactions, matches, documents, expenses] = await Promise.all([
        evolu.loadQuery(allBankImportBatchesQuery),
        evolu.loadQuery(allBankTransactionsQuery),
        evolu.loadQuery(allBankTransactionMatchesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allExpensesQuery),
    ]);

    return {
        batches: batches as EvoluBankImportBatchRow[],
        transactions: transactions as EvoluBankTransactionRow[],
        matches: matches as EvoluBankTransactionMatchRow[],
        documents: documents as EvoluDocumentRow[],
        expenses: expenses as EvoluExpenseRow[],
    };
}

function parseBankFile(
    filename: string,
    contents: string,
    format?: string | null,
): { rows: ParsedBankRow[]; source: BankImportSource } {
    if (format === "camt053") {
        return { rows: parseCamt053BankStatement(contents), source: "camt053" };
    }
    if (format === "csv") {
        return { rows: parseCsvBankStatement(contents), source: "csv" };
    }
    if (supportsCamt053File(filename, contents)) {
        return { rows: parseCamt053BankStatement(contents), source: "camt053" };
    }
    if (supportsCsvBankFile(filename)) {
        return { rows: parseCsvBankStatement(contents), source: "csv" };
    }
    throw new Error("Unsupported bank statement format. Upload CAMT.053 XML or CSV.");
}

function findExactMatchDocument(
    companyId: CompanyId,
    transaction: EvoluBankTransactionRow,
    documents: EvoluDocumentRow[],
): EvoluDocumentRow | null {
    const vs = transaction.variableSymbol;
    if (!vs) return null;

    const candidates = documents.filter(
        (doc) =>
            doc.companyId === companyId
            && doc.status === "issued"
            && (doc.documentType === "invoice" || doc.documentType === "proforma")
            && matchesDocumentVariableSymbol(doc, vs),
    );

    if (candidates.length === 0) return null;

    const currency = (transaction.currency || "EUR").toUpperCase();
    const amount = parseFloat(transaction.amount || "0");

    const exact = candidates.filter((doc) => {
        return (
            (doc.currency || "EUR").toUpperCase() === currency
            && Math.abs(documentAmountDue(doc) - amount) <= AMOUNT_TOLERANCE
        );
    });

    return exact.length === 1 ? exact[0] : null;
}

function applyMatch(
    evolu: Evolu<InvoicingLocalSchema>,
    transaction: EvoluBankTransactionRow,
    document: EvoluDocumentRow,
    matchedAmount: number,
    matchType: BankMatchType,
    ctx: BankPaymentContext,
): void {
    const match = evolu.insert("bankTransactionMatch", {
        bankTransactionId: transaction.id,
        businessDocumentId: document.id,
        matchedAmount: matchedAmount.toFixed(2),
        matchType,
        matchedAt: new Date().toISOString(),
    });
    if (!match.ok) {
        return;
    }

    evolu.update("bankTransaction", {
        id: transaction.id,
        matchStatus: "matched",
    });

    markLocalDocumentPaidWithAmount(
        evolu,
        document.id,
        ctx.documents,
        matchedAmount,
        AMOUNT_TOLERANCE,
    );

    ctx.matches.push({
        id: match.value.id,
        bankTransactionId: transaction.id,
        businessDocumentId: document.id,
        matchedAmount: matchedAmount.toFixed(2),
        matchType,
        matchedAt: new Date().toISOString(),
    });

    const tx = ctx.transactions.find((row) => row.id === transaction.id);
    if (tx) tx.matchStatus = "matched";
}

export function tryAutoMatchTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transaction: EvoluBankTransactionRow,
    ctx: BankPaymentContext,
): boolean {
    if (transaction.matchStatus === "matched") return true;
    if (resolvedBankDirection(transaction) !== "credit") return false;
    if (!transaction.variableSymbol) return false;

    const document = findExactMatchDocument(transaction.companyId, transaction, ctx.documents);
    if (!document) return false;

    applyMatch(
        evolu,
        transaction,
        document,
        parseFloat(transaction.amount || "0"),
        "auto",
        ctx,
    );
    return true;
}

export function bankMatchSuggestions(
    transaction: EvoluBankTransactionRow,
    documents: EvoluDocumentRow[],
    limit = 10,
): { document: EvoluDocumentRow; reason: string }[] {
    if (resolvedBankDirection(transaction) !== "credit") return [];

    const vs = transaction.variableSymbol;
    const candidates = documents
        .filter(
            (doc) =>
                doc.companyId === transaction.companyId
                && doc.status === "issued"
                && (doc.documentType === "invoice" || doc.documentType === "proforma")
                && matchesDocumentVariableSymbol(doc, vs),
        )
        .sort((a, b) => (b.issueDate || "").localeCompare(a.issueDate || ""))
        .slice(0, 50);

    const out: { document: EvoluDocumentRow; reason: string }[] = [];
    const txAmount = parseFloat(transaction.amount || "0");
    const txCurrency = (transaction.currency || "EUR").toUpperCase();

    for (const document of candidates) {
        let reason = "variable_symbol";
        if (Math.abs(documentAmountDue(document) - txAmount) > AMOUNT_TOLERANCE) {
            reason = "amount_mismatch";
        }
        if ((document.currency || "EUR").toUpperCase() !== txCurrency) {
            reason = "currency_mismatch";
        }
        out.push({ document, reason });
        if (out.length >= limit) break;
    }

    return out;
}

export async function importLocalBankFileAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    file: File,
    format?: string | null,
): Promise<{ imported: number; auto_matched: number; skipped_duplicates: number }> {
    const contents = await file.text();
    const { rows, source } = parseBankFile(file.name, contents, format);
    const ctx = await loadBankPaymentContext(evolu);

    const batchResult = evolu.insert("bankImportBatch", {
        companyId,
        source,
        filename: file.name.slice(0, 255),
        rowCount: String(rows.length),
        importedCount: "0",
        skippedDuplicates: "0",
        autoMatchedCount: "0",
    });

    if (!batchResult.ok) {
        throw new Error("Could not create import batch.");
    }

    const batchId = batchResult.value.id as BankImportBatchId;
    let imported = 0;
    let skipped = 0;
    let autoMatched = 0;
    const created: EvoluBankTransactionRow[] = [];

    for (const parsed of rows) {
        const dedupeHash = await bankTransactionDedupeHash(companyId, {
            bookedAt: parsed.bookedAt,
            amount: parsed.amount,
            currency: parsed.currency,
            direction: parsed.direction,
            variableSymbol: parsed.variableSymbol,
            reference: parsed.reference,
            bankTransactionId: parsed.bankTransactionId,
        });

        const duplicate = ctx.transactions.some(
            (row) => row.companyId === companyId && row.dedupeHash === dedupeHash,
        );
        if (duplicate) {
            skipped++;
            continue;
        }

        const insertResult = evolu.insert("bankTransaction", {
            companyId,
            bankImportBatchId: batchId,
            bookedAt: parsed.bookedAt,
            amount: parsed.amount.toFixed(2),
            currency: parsed.currency,
            direction: parsed.direction,
            matchStatus: "unmatched",
            businessExpenseId: null,
            variableSymbol: parsed.variableSymbol,
            constantSymbol: parsed.constantSymbol,
            specificSymbol: parsed.specificSymbol,
            counterpartyName: parsed.counterpartyName,
            counterpartyIban: parsed.counterpartyIban,
            reference: parsed.reference,
            bankTransactionId: parsed.bankTransactionId,
            dedupeHash,
            source,
        });

        if (insertResult.ok) {
            const row: EvoluBankTransactionRow = {
                id: insertResult.value.id,
                companyId,
                bankImportBatchId: batchId,
                bookedAt: parsed.bookedAt,
                amount: parsed.amount.toFixed(2),
                currency: parsed.currency,
                direction: parsed.direction,
                matchStatus: "unmatched",
                businessExpenseId: null,
                variableSymbol: parsed.variableSymbol,
                constantSymbol: parsed.constantSymbol,
                specificSymbol: parsed.specificSymbol,
                counterpartyName: parsed.counterpartyName,
                counterpartyIban: parsed.counterpartyIban,
                reference: parsed.reference,
                bankTransactionId: parsed.bankTransactionId,
                dedupeHash,
                source,
            };
            ctx.transactions.push(row);
            created.push(row);
            imported++;
        } else {
            skipped++;
        }
    }

    for (const transaction of created) {
        if (tryAutoMatchTransaction(evolu, transaction, ctx)) {
            autoMatched++;
        }
    }

    evolu.update("bankImportBatch", {
        id: batchId,
        importedCount: String(imported),
        skippedDuplicates: String(skipped),
        autoMatchedCount: String(autoMatched),
    });

    return { imported, auto_matched: autoMatched, skipped_duplicates: skipped };
}

export async function autoMatchLocalBatchAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    batchId: BankImportBatchId,
): Promise<{ auto_matched: number }> {
    const ctx = await loadBankPaymentContext(evolu);
    const transactions = ctx.transactions.filter(
        (row) => row.bankImportBatchId === batchId && row.matchStatus === "unmatched",
    );

    let autoMatched = 0;
    for (const transaction of transactions) {
        if (tryAutoMatchTransaction(evolu, transaction, ctx)) {
            autoMatched++;
        }
    }

    const batch = ctx.batches.find((row) => row.id === batchId);
    if (batch) {
        const prev = parseInt(batch.autoMatchedCount || "0", 10) || 0;
        evolu.update("bankImportBatch", {
            id: batchId,
            autoMatchedCount: String(prev + autoMatched),
        });
    }

    return { auto_matched: autoMatched };
}

export function manualMatchLocalTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    documentId: DocumentId,
    ctx: BankPaymentContext,
    matchedAmount?: number,
): void {
    const transaction = ctx.transactions.find((row) => row.id === transactionId);
    const document = ctx.documents.find((row) => row.id === documentId);
    if (!transaction || !document) {
        throw new Error("Transaction or document not found.");
    }
    if (transaction.matchStatus === "matched") {
        throw new Error("Transaction is already matched.");
    }
    if (transaction.companyId !== document.companyId) {
        throw new Error("Transaction and document belong to different companies.");
    }

    applyMatch(
        evolu,
        transaction,
        document,
        matchedAmount ?? parseFloat(transaction.amount || "0"),
        "manual",
        ctx,
    );
}

export function ignoreLocalBankTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    ctx: BankPaymentContext,
): void {
    evolu.update("bankTransaction", {
        id: transactionId,
        matchStatus: "ignored",
    });
    const tx = ctx.transactions.find((row) => row.id === transactionId);
    if (tx) tx.matchStatus = "ignored";
}

export function unmatchLocalBankTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    ctx: BankPaymentContext,
): void {
    const transaction = ctx.transactions.find((row) => row.id === transactionId);
    if (!transaction) return;

    if (transaction.businessExpenseId) {
        evolu.update("bankTransaction", {
            id: transactionId,
            businessExpenseId: null,
            matchStatus: "unmatched",
        });
        transaction.businessExpenseId = null;
        transaction.matchStatus = "unmatched";
        return;
    }

    const match = ctx.matches.find((row) => row.bankTransactionId === transactionId);
    if (match) {
        unmarkLocalDocumentPaid(evolu, match.businessDocumentId);
        evolu.update("bankTransactionMatch", { id: match.id, isDeleted: sqliteTrue });
        ctx.matches = ctx.matches.filter((row) => row.id !== match.id);
    }

    evolu.update("bankTransaction", {
        id: transactionId,
        matchStatus: "unmatched",
    });
    transaction.matchStatus = "unmatched";
}

export function createLocalExpenseFromBankTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    payload: ExpenseSavePayload & { supplier?: string; category?: string },
    ctx: BankPaymentContext,
): EvoluExpenseRow {
    const transaction = ctx.transactions.find((row) => row.id === transactionId);
    if (!transaction) throw new Error("Transaction not found.");
    if (resolvedBankDirection(transaction) !== "debit") {
        throw new Error("Expenses can only be created from outgoing bank movements.");
    }
    if (transaction.businessExpenseId) {
        throw new Error("This movement is already linked to an expense.");
    }
    if (transaction.matchStatus === "matched" && ctx.matches.some((m) => m.bankTransactionId === transactionId)) {
        throw new Error("This movement is already matched to an invoice.");
    }

    const supplier = (payload.supplier || transaction.counterpartyName || "").trim();
    let title = (payload.title || "").trim();
    if (!title) {
        title = supplier || transaction.reference?.trim() || "Bank expense";
    }

    const expensePayload: ExpenseSavePayload = {
        title,
        variable_symbol: payload.variable_symbol ?? transaction.variableSymbol ?? undefined,
        constant_symbol: payload.constant_symbol ?? transaction.constantSymbol ?? undefined,
        specific_symbol: payload.specific_symbol ?? transaction.specificSymbol ?? undefined,
        issue_date: payload.issue_date || transaction.bookedAt?.slice(0, 10) || new Date().toISOString().slice(0, 10),
        delivery_date: payload.delivery_date ?? payload.issue_date,
        due_date: payload.due_date ?? payload.issue_date,
        total: payload.total ?? transaction.amount ?? "0",
        currency: payload.currency ?? transaction.currency ?? "EUR",
        internal_note: payload.internal_note ?? transaction.reference ?? undefined,
        mark_paid: payload.mark_paid !== false,
    };

    const internalNumber = nextLocalExpenseInternalNumber(transaction.companyId, ctx.expenses);

    const result = insertLocalExpense(
        evolu,
        transaction.companyId,
        expensePayload,
        ctx.expenses,
    );
    if (!result.ok) {
        throw new Error("Could not create expense.");
    }

    evolu.update("bankTransaction", {
        id: transactionId,
        businessExpenseId: result.value.id,
        matchStatus: "matched",
    });

    const paidAt = expensePayload.mark_paid ? new Date().toISOString().slice(0, 10) : null;
    const expenseRow: EvoluExpenseRow = {
        id: result.value.id,
        companyId: transaction.companyId,
        status: expensePayload.mark_paid ? "paid" : "recorded",
        internalNumber,
        externalNumber: null,
        title: expensePayload.title ?? null,
        variableSymbol: expensePayload.variable_symbol ?? null,
        constantSymbol: expensePayload.constant_symbol ?? null,
        specificSymbol: expensePayload.specific_symbol ?? null,
        issueDate: expensePayload.issue_date,
        deliveryDate: expensePayload.delivery_date ?? null,
        dueDate: expensePayload.due_date ?? null,
        paidAt,
        cancelledAt: null,
        total: typeof expensePayload.total === "number"
            ? expensePayload.total.toFixed(2)
            : String(expensePayload.total),
        currency: expensePayload.currency ?? "EUR",
        internalNote: expensePayload.internal_note ?? null,
    };

    ctx.expenses.push(expenseRow);
    transaction.businessExpenseId = result.value.id;
    transaction.matchStatus = "matched";

    return expenseRow;
}

export function mapLocalBankTransactionsToApi(
    rows: EvoluBankTransactionRow[],
    ctx: BankPaymentContext,
) {
    return rows.map((row) => {
        const match = ctx.matches.find((m) => m.bankTransactionId === row.id) ?? null;
        const document = match
            ? ctx.documents.find((d) => d.id === match.businessDocumentId) ?? null
            : null;
        const expense = row.businessExpenseId
            ? ctx.expenses.find((e) => e.id === row.businessExpenseId) ?? null
            : null;
        return evoluBankTxToApi(row, match, document, expense);
    });
}

export function mapLocalBankBatchesToApi(
    batches: EvoluBankImportBatchRow[],
    companyId: CompanyId,
) {
    return batches
        .filter((row) => row.companyId === companyId)
        .sort((a, b) => (b.createdAt || "").localeCompare(a.createdAt || ""))
        .slice(0, 20)
        .map(evoluBankBatchToApi);
}

export { filterLocalBankTransactions, buildBankSummary } from "./bankMap";
