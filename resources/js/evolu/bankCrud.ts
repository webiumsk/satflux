import { activeBankExpenseClaim, bankExpenseScopeKey, createBankExpenseClaim, createBankExpensePaymentIndex, releasedBankExpenseReceipt } from "./bankExpenseClaims";
import { sqliteTrue, createId, createRandomBytes } from "@evolu/common";
import { ExpenseId } from "./schema";
import type { Evolu, OwnerId } from "@evolu/common/local-first";
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
import {
    bankPaymentMatchReason,
    documentMatchesBankPaymentHints,
    hasBankPaymentMatchHints,
} from "./bankPaymentDocumentMatcher";
import { bankTransactionDedupeHash } from "./bankDedupe";
import { bankPaymentCurrency, tryAutoMatchBankEmailExpense } from "./bankExpensePaymentMatcher";
import { awaitBankMutation, readBankReconciliationContext, serializeBankEmailReconciliation } from "./bankReconciliation";
import {
    documentAmountDue,
    evoluBankBatchToApi,
    evoluBankTxToApi,
    type BankTxApiRow,
    type EvoluBankImportBatchRow,
    type EvoluBankTransactionMatchRow,
    type EvoluBankTransactionRow,
} from "./bankMap";
import type { EvoluDocumentRow } from "./documentMap";
import { markLocalDocumentPaidWithAmount, unmarkLocalDocumentPaid } from "./documentCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { insertLocalExpenseInReconciliation, mapLocalExpensePayloadFields, nextLocalExpenseInternalNumber, type ExpenseSavePayload } from "./expenseCrud";
import { withExpenseReconciliation, assertExpenseCompanyOwner, expenseReconciliationScope, commitExpenseMutation, type ExpenseReconciliationScope } from "./expenseReconciliation";
import { toAppRows } from "./queryLoad";
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
        batches: toAppRows<EvoluBankImportBatchRow>(batches),
        transactions: toAppRows<EvoluBankTransactionRow>(transactions),
        matches: toAppRows<EvoluBankTransactionMatchRow>(matches),
        documents: toAppRows<EvoluDocumentRow>(documents),
        expenses: toAppRows<EvoluExpenseRow>(expenses),
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
    if (!hasBankPaymentMatchHints(transaction.variableSymbol, transaction.reference)) {
        return null;
    }

    const candidates = documents.filter(
        (doc) =>
            doc.companyId === companyId
            && doc.status === "issued"
            && (doc.documentType === "invoice" || doc.documentType === "proforma")
            && documentMatchesBankPaymentHints(doc, transaction.variableSymbol, transaction.reference),
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

/**
 * Queue a match row and the existing bank/document bookkeeping synchronously.
 * Return false on match-row validation rejection; gated callers still prove persistence.
 */
function applyMatch(
    evolu: Evolu<InvoicingLocalSchema>,
    transaction: EvoluBankTransactionRow,
    document: EvoluDocumentRow,
    matchedAmount: number,
    matchType: BankMatchType,
    ctx: BankPaymentContext,
): boolean {
    const match = evolu.insert("bankTransactionMatch", {
        bankTransactionId: transaction.id,
        businessDocumentId: document.id,
        matchedAmount: matchedAmount.toFixed(2),
        matchType,
        matchedAt: new Date().toISOString(),
    });
    if (!match.ok) {
        return false;
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
        "bank_transfer",
    );

    const docInCtx = ctx.documents.find((row) => row.id === document.id);
    if (docInCtx) {
        const total = parseFloat(docInCtx.total || "0");
        const fullyPaid = Math.abs(matchedAmount - total) <= AMOUNT_TOLERANCE;
        docInCtx.status = fullyPaid ? "paid" : "issued";
        docInCtx.amountPaid = matchedAmount.toFixed(2);
        if (fullyPaid) {
            docInCtx.paidAt = new Date().toISOString();
            docInCtx.paidPaymentMethod = "bank_transfer";
        }
    }

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
    return true;
}

/**
 * Dispatch eligible email debits to expense reconciliation and credits to exact document matching.
 * Return the actual insertion result so rejected matches cannot inflate automatic counts.
 */
export async function tryAutoMatchTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transaction: EvoluBankTransactionRow,
    ctx: BankPaymentContext,
): Promise<boolean> {
    if (transaction.bankPaymentClaimJson != null) return false;
    if (transaction.direction === "debit") return tryAutoMatchBankEmailExpense(evolu, transaction, ctx);
    if (transaction.matchStatus === "matched") return true;
    if (resolvedBankDirection(transaction) !== "credit") return false;
    if (!hasBankPaymentMatchHints(transaction.variableSymbol, transaction.reference)) return false;

    const document = findExactMatchDocument(transaction.companyId, transaction, ctx.documents);
    if (!document) return false;

    return applyMatch(
        evolu,
        transaction,
        document,
        parseFloat(transaction.amount || "0"),
        "auto",
        ctx,
    );
}

export function bankMatchSuggestions(
    transaction: EvoluBankTransactionRow,
    documents: EvoluDocumentRow[],
    limit = 10,
): { document: EvoluDocumentRow; reason: string }[] {
    if (resolvedBankDirection(transaction) !== "credit") return [];

    if (!hasBankPaymentMatchHints(transaction.variableSymbol, transaction.reference)) {
        return [];
    }

    const candidates = documents
        .filter(
            (doc) =>
                doc.companyId === transaction.companyId
                && doc.status === "issued"
                && (doc.documentType === "invoice" || doc.documentType === "proforma")
                && documentMatchesBankPaymentHints(
                    doc,
                    transaction.variableSymbol,
                    transaction.reference,
                ),
        )
        .sort((a, b) => (b.issueDate || "").localeCompare(a.issueDate || ""))
        .slice(0, 50);

    const out: { document: EvoluDocumentRow; reason: string }[] = [];
    const txAmount = parseFloat(transaction.amount || "0");
    const txCurrency = (transaction.currency || "EUR").toUpperCase();

    for (const document of candidates) {
        let reason =
            bankPaymentMatchReason(document, transaction.variableSymbol, transaction.reference)
            ?? "variable_symbol";
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

    return importLocalBankRows(evolu, companyId, rows, source, file.name.slice(0, 255));
}

export type WiseBankRowInput = {
    booked_at: string;
    amount: number | string;
    currency: string;
    direction: string;
    variable_symbol?: string | null;
    constant_symbol?: string | null;
    specific_symbol?: string | null;
    counterparty_name?: string | null;
    counterparty_iban?: string | null;
    reference?: string | null;
    bank_transaction_id?: string | null;
};

function wiseRowsToParsed(rows: WiseBankRowInput[]): ParsedBankRow[] {
    return rows.map((row) => ({
        bookedAt: row.booked_at,
        amount: typeof row.amount === "number" ? row.amount : parseFloat(String(row.amount)) || 0,
        currency: (row.currency || "USD").toUpperCase(),
        direction: row.direction === "debit" ? "debit" : "credit",
        variableSymbol: row.variable_symbol ?? null,
        constantSymbol: row.constant_symbol ?? null,
        specificSymbol: row.specific_symbol ?? null,
        counterpartyName: row.counterparty_name ?? null,
        counterpartyIban: row.counterparty_iban ?? null,
        reference: row.reference ?? null,
        bankTransactionId: row.bank_transaction_id ?? null,
    }));
}

/**
 * Import normalized file or Wise rows with company-local deduplication and batch counters.
 * This basic-import path retains its existing unlocked behavior and does not grant debit expense-matching authority.
 */
export async function importLocalBankRows(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: ParsedBankRow[] | WiseBankRowInput[],
    source: BankImportSource,
    filename = "import",
): Promise<{ imported: number; auto_matched: number; skipped_duplicates: number }> {
    const parsedRows = rows.length > 0 && "bookedAt" in rows[0]
        ? (rows as ParsedBankRow[])
        : wiseRowsToParsed(rows as WiseBankRowInput[]);
    const ctx = await loadBankPaymentContext(evolu);

    const batchResult = evolu.insert("bankImportBatch", {
        companyId,
        source,
        filename: filename.slice(0, 255),
        rowCount: String(parsedRows.length),
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

    for (const row of parsedRows) {
        const dedupeHash = await bankTransactionDedupeHash(companyId, {
            bookedAt: row.bookedAt,
            amount: row.amount,
            currency: row.currency,
            direction: row.direction,
            variableSymbol: row.variableSymbol,
            reference: row.reference,
            bankTransactionId: row.bankTransactionId,
        });

        const duplicate = ctx.transactions.some(
            (tx) => tx.companyId === companyId && tx.dedupeHash === dedupeHash,
        );
        if (duplicate) {
            skipped++;
            continue;
        }

        const insertResult = evolu.insert("bankTransaction", {
            companyId,
            bankImportBatchId: batchId,
            bookedAt: row.bookedAt,
            amount: row.amount.toFixed(2),
            currency: row.currency,
            direction: row.direction,
            matchStatus: "unmatched",
            businessExpenseId: null,
            variableSymbol: row.variableSymbol,
            constantSymbol: row.constantSymbol,
            specificSymbol: row.specificSymbol,
            counterpartyName: row.counterpartyName,
            counterpartyIban: row.counterpartyIban,
            reference: row.reference,
            bankTransactionId: row.bankTransactionId,
            dedupeHash,
            source,
        });

        if (insertResult.ok) {
            const createdRow: EvoluBankTransactionRow = {
                id: insertResult.value.id,
                companyId,
                bankImportBatchId: batchId,
                bookedAt: row.bookedAt,
                amount: row.amount.toFixed(2),
                currency: row.currency,
                direction: row.direction,
                matchStatus: "unmatched",
                businessExpenseId: null,
                variableSymbol: row.variableSymbol,
                constantSymbol: row.constantSymbol,
                specificSymbol: row.specificSymbol,
                counterpartyName: row.counterpartyName,
                counterpartyIban: row.counterpartyIban,
                reference: row.reference,
                bankTransactionId: row.bankTransactionId,
                dedupeHash,
                source,
            };
            ctx.transactions.push(createdRow);
            created.push(createdRow);
            imported++;
        } else {
            skipped++;
        }
    }

    for (const transaction of created) {
        if (await tryAutoMatchTransaction(evolu, transaction, ctx)) {
            autoMatched++;
        }
    }

    if (autoMatched > 0) {
        await evolu.loadQuery(allDocumentsQuery);
    }

    evolu.update("bankImportBatch", {
        id: batchId,
        importedCount: String(imported),
        skippedDuplicates: String(skipped),
        autoMatchedCount: String(autoMatched),
    });

    return { imported, auto_matched: autoMatched, skipped_duplicates: skipped };
}

export type ServerBankTransactionRow = {
    booked_at: string;
    amount: string;
    currency: string;
    direction: string;
    recorded_direction?: string;
    variable_symbol?: string | null;
    constant_symbol?: string | null;
    specific_symbol?: string | null;
    counterparty_name?: string | null;
    counterparty_iban?: string | null;
    reference?: string | null;
    bank_transaction_id?: string | null;
    dedupe_hash?: string | null;
};

/** Import b-mail transactions fetched from the server bridge company into Evolu. */
export async function importInboundServerTransactionsAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: ServerBankTransactionRow[],
): Promise<{ imported: number; auto_matched: number }> {
    return runInboundServerImport(evolu, companyId, rows);
}

/** Scope admission is separate from row coverage: only admitted incomplete pages may yield their retry-frame turn. */
export type BankInboundPageResult = { imported: number; auto_matched: number; complete: boolean; scope_verified: boolean };

/** Cursor pages acknowledge coverage only after terminal proof in the captured financial owner partition. */
export async function importInboundServerTransactionsPageAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: ServerBankTransactionRow[],
    expectedOwnerId: string,
): Promise<BankInboundPageResult> {
    const result = await runInboundServerImport(evolu, companyId, rows, expectedOwnerId);
    return { imported: result.imported, auto_matched: result.auto_matched, complete: result.complete === true,
        scope_verified: result.scope_verified === true };
}

/** Preserve legacy result semantics while sharing the same reconciliation and worker proof boundaries. */
async function runInboundServerImport(
    evolu: Evolu<InvoicingLocalSchema>, companyId: CompanyId, rows: ServerBankTransactionRow[], pageOwnerId?: string,
): Promise<{ imported: number; auto_matched: number; complete?: boolean; scope_verified?: boolean }> {
    return serializeBankEmailReconciliation(evolu, (allowExpenseMatching, lease) => allowExpenseMatching
        ? withExpenseReconciliation(evolu, (scope) => {
            assertExpenseCompanyOwner(scope, companyId, pageOwnerId);
            return importInboundServerTransactions(scope.evolu, companyId, rows, true, pageOwnerId);
        }, lease)
        : pageOwnerId === undefined ? importInboundServerTransactions(evolu, companyId, rows, false)
            : Promise.resolve({ imported: 0, auto_matched: 0, complete: false, scope_verified: false }));
}

/**
 * Persist email direction evidence, deduplicate rows and verify every inserted identity before counting.
 * The caller supplies the locking decision; unlocked imports skip automatic expense payment.
 * Known row-batch rejection is explicit, while uncertain worker outcomes propagate without retry.
 */
async function importInboundServerTransactions(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: ServerBankTransactionRow[],
    allowExpenseMatching: boolean,
    pageOwnerId?: string,
): Promise<{ imported: number; auto_matched: number; complete?: boolean; scope_verified?: boolean }> {
    const result = (imported: number, auto_matched: number, complete: boolean) => pageOwnerId === undefined
        ? { imported, auto_matched } : { imported, auto_matched, complete, scope_verified: true };
    const inOwner = (row: object) => pageOwnerId === undefined || ("ownerId" in row && row.ownerId === pageOwnerId);
    const ownerOptions = pageOwnerId === undefined ? {} : { ownerId: pageOwnerId as OwnerId };
    if (rows.length === 0) return result(0, 0, true);
    const createdBatch: { id?: BankImportBatchId } = {};
    const started = await awaitBankMutation(evolu, (onComplete) => {
        const result = evolu.insert("bankImportBatch", {
            companyId, source: "inbound_email", filename: "b-mail-sync",
            rowCount: String(rows.length), importedCount: "0", skippedDuplicates: "0", autoMatchedCount: "0",
        }, { ...ownerOptions, onComplete });
        if (result.ok) createdBatch.id = result.value.id;
        return result;
    });
    const batchId = createdBatch.id;
    if (!started || !batchId) return result(0, 0, false);
    // A legitimate batch mutation invalidates the private, unsubscribed keys.
    // Their fresh worker responses run after the preceding handler's COMMIT.
    let ctx = await readBankReconciliationContext(evolu);
    if (!ctx.batches.some((batch) => batch.id === batchId && batch.companyId === companyId && inOwner(batch))) {
        if (pageOwnerId !== undefined) return result(0, 0, false);
        throw new Error("bank_import_batch_missing");
    }
    let imported = 0;
    let skipped = 0;
    let autoMatched = 0;
    const hashes = new Set(ctx.transactions.filter((transaction) => transaction.companyId === companyId && inOwner(transaction))
        .map((transaction) => transaction.dedupeHash));
    const coverageHashes = new Set<string>();
    const prepared: Array<{ payload: Parameters<typeof evolu.insert<"bankTransaction">>[1]; dedupeHash: string }> = [];

    for (const row of rows) {
        const bookedAt = row.booked_at?.includes("T") ? row.booked_at : `${row.booked_at}T12:00:00`;
        const amount = Math.abs(parseFloat(row.amount || "0"));
        const recordedDirection = row.recorded_direction === "debit" || row.recorded_direction === "credit"
            ? row.recorded_direction : null;
        const direction = recordedDirection ?? (row.direction === "debit" ? "debit" : "credit");
        const currency = direction === "debit" ? bankPaymentCurrency(row.currency) : (row.currency || "EUR").toUpperCase();
        // Preserve malformed debit evidence for later explicit reconciliation.
        const amountText = direction === "debit"
            ? (typeof row.amount === "string" && row.amount.length <= 32 ? row.amount.trim() : null)
            : amount.toFixed(2);
        const dedupeHash = row.dedupe_hash?.trim() || await bankTransactionDedupeHash(companyId, {
            bookedAt, amount, currency: currency ?? "", direction,
            variableSymbol: row.variable_symbol, reference: row.reference, bankTransactionId: row.bank_transaction_id,
        });
        coverageHashes.add(dedupeHash);
        if (hashes.has(dedupeHash)) {
            skipped++;
            continue;
        }
        const payload: Parameters<typeof evolu.insert<"bankTransaction">>[1] = {
            companyId, bankImportBatchId: batchId, bookedAt, amount: amountText, currency, direction,
            recordedDirectionEvidence: recordedDirection ?? "unverified",
            matchStatus: "unmatched", businessExpenseId: null,
            variableSymbol: row.variable_symbol ?? null, constantSymbol: row.constant_symbol ?? null,
            specificSymbol: row.specific_symbol ?? null, counterpartyName: row.counterparty_name ?? null,
            counterpartyIban: row.counterparty_iban ?? null, reference: row.reference ?? null,
            bankTransactionId: row.bank_transaction_id ?? null, dedupeHash, source: "inbound_email",
        };
        if (!evolu.insert("bankTransaction", payload, { ...ownerOptions, onlyValidate: true }).ok) {
            skipped++;
            continue;
        }
        hashes.add(dedupeHash);
        prepared.push({ payload, dedupeHash });
    }
    const created: Array<{ id: BankTransactionId; dedupeHash: string }> = [];
    if (prepared.length > 0) {
        /**
         * Queue every prepared row in one synchronous batch with the receipt on the final insert.
         * Captured IDs remain provisional until the post-handler identity check succeeds.
         */
        const insertPrepared = (onComplete: () => void) => {
            // Validation of any actual insert aborts Evolu's whole synchronous
            // batch. Never report earlier rows as imported after that abort.
            for (const [index, { payload, dedupeHash }] of prepared.entries()) {
                const result = evolu.insert("bankTransaction", payload,
                    index === prepared.length - 1 ? { ...ownerOptions, onComplete } : ownerOptions);
                if (!result.ok) return result;
                created.push({ id: result.value.id, dedupeHash });
            }
            return { ok: true };
        };
        const completed = allowExpenseMatching
            ? (await commitExpenseMutation(evolu, insertPrepared)).ok
            : await awaitBankMutation(evolu, insertPrepared);
        if (!completed) throw new Error("bank_import_row_batch_rejected");
        ctx = allowExpenseMatching ? expenseReconciliationScope(evolu).context : await readBankReconciliationContext(evolu);
    }
    const transactionsById = new Map(ctx.transactions.map((transaction) => [transaction.id, transaction]));
    for (const inserted of created) {
        const transaction = transactionsById.get(inserted.id);
        if (!transaction || transaction.companyId !== companyId || transaction.dedupeHash !== inserted.dedupeHash || !inOwner(transaction)) {
            throw new Error("bank_import_row_missing");
        }
    }
    imported = created.length;
    for (const { id } of created) {
        const transaction = transactionsById.get(id);
        if (transaction && (transaction.direction !== "debit" || allowExpenseMatching)
            && await tryAutoMatchTransaction(evolu, transaction, ctx)) autoMatched++;
    }
    const counted = await awaitBankMutation(evolu, (onComplete) => evolu.update("bankImportBatch", {
        id: batchId, companyId, importedCount: String(imported), skippedDuplicates: String(skipped),
        autoMatchedCount: String(autoMatched),
    }, { ...ownerOptions, onComplete }));
    if (!counted) throw new Error("bank_import_batch_update_rejected");
    const final = await readBankReconciliationContext(evolu);
    const batch = final.batches.find((row) => row.id === batchId && row.companyId === companyId && inOwner(row));
    if (!batch || batch.importedCount !== String(imported) || batch.autoMatchedCount !== String(autoMatched)
        || batch.skippedDuplicates !== String(skipped)) throw new Error("bank_import_batch_state_changed");
    const covered = new Set(final.transactions.filter((row) => row.companyId === companyId && inOwner(row)).map((row) => row.dedupeHash));
    return result(imported, autoMatched, [...coverageHashes].every((hash) => covered.has(hash)));
}

/**
 * Serialize explicit batch reconciliation across cooperating clients.
 * Automatic expense payment still requires an acquired origin lock; uncertain outcomes are not retried.
 */
export async function autoMatchLocalBatchAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    batchId: BankImportBatchId,
): Promise<{ auto_matched: number }> {
    return serializeBankEmailReconciliation(evolu, (allowExpenseMatching, lease) => allowExpenseMatching
        ? withExpenseReconciliation(evolu, (scope) => autoMatchLocalBatch(scope.evolu, batchId, true), lease)
        : autoMatchLocalBatch(evolu, batchId, false));
}

/**
 * Refresh a known, live batch before explicit matching and prove the final counter update.
 * Honor the caller's locking decision when deciding whether expenses may be paid.
 */
async function autoMatchLocalBatch(
    evolu: Evolu<InvoicingLocalSchema>,
    batchId: BankImportBatchId,
    allowExpenseMatching: boolean,
): Promise<{ auto_matched: number }> {
    const knownBatches = await evolu.loadQuery(allBankImportBatchesQuery);
    const known = knownBatches.find((row) => row.id === batchId);
    const companyId = known?.companyId;
    if (!companyId) return { auto_matched: 0 };
    if (allowExpenseMatching) assertExpenseCompanyOwner(expenseReconciliationScope(evolu), companyId);
    // Only metadata of a known batch can act as this legitimate action's barrier.
    // Do not create a phantom batch or touch a financial row to refresh queries.
    const refreshed = await awaitBankMutation(evolu, (onComplete) => evolu.update("bankImportBatch", {
        id: batchId, companyId,
    }, { onComplete }));
    if (!refreshed) return { auto_matched: 0 };
    const ctx = await readBankReconciliationContext(evolu);
    const batch = ctx.batches.find((row) => row.id === batchId && row.companyId === companyId);
    if (!batch) return { auto_matched: 0 };
    const transactions = ctx.transactions.filter((row) => row.bankImportBatchId === batchId
        && row.companyId === batch.companyId && row.matchStatus === "unmatched");
    let autoMatched = 0;
    for (const transaction of transactions) {
        if ((transaction.direction !== "debit" || allowExpenseMatching)
            && await tryAutoMatchTransaction(evolu, transaction, ctx)) autoMatched++;
    }
    const previous = parseInt(batch.autoMatchedCount || "0", 10) || 0;
    const counted = await awaitBankMutation(evolu, (onComplete) => evolu.update("bankImportBatch", {
        id: batchId, companyId: batch.companyId, autoMatchedCount: String(previous + autoMatched),
    }, { onComplete }));
    if (!counted) throw new Error("bank_import_batch_update_rejected");
    const final = await readBankReconciliationContext(evolu);
    const committed = final.batches.find((row) => row.id === batchId && row.companyId === batch.companyId);
    if (!committed || committed.autoMatchedCount !== String(previous + autoMatched)) {
        throw new Error("bank_import_batch_state_changed");
    }
    return { auto_matched: autoMatched };
}

export async function manualMatchLocalTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    documentId: DocumentId,
    ctx: BankPaymentContext,
    matchedAmount?: number,
): Promise<void> {
    const scope = await withExpenseReconciliation(evolu, (scope) => {
        manualMatchLocalTransactionCore(scope.evolu, transactionId, documentId, scope.context, matchedAmount);
        return scope;
    });
    Object.assign(ctx, scope.context);
}

/**
 * Apply a manual match using the writer gate's fresh context.
 * Reject missing, cross-company or already matched targets and surface match-row rejection.
 */
function manualMatchLocalTransactionCore(
    evolu: Evolu<InvoicingLocalSchema>, transactionId: BankTransactionId, documentId: DocumentId,
    ctx: BankPaymentContext, matchedAmount?: number,
): void {
    const transaction = ctx.transactions.find((row) => row.id === transactionId);
    const document = ctx.documents.find((row) => row.id === documentId);
    if (!transaction || !document) {
        throw new Error("Transaction or document not found.");
    }
    if (transaction.bankPaymentClaimJson != null) throw new Error("bank_expense_claim_changed");
    if (transaction.matchStatus === "matched") {
        throw new Error("Transaction is already matched.");
    }
    if (transaction.companyId !== document.companyId) {
        throw new Error("Transaction and document belong to different companies.");
    }

    const matched = applyMatch(
        evolu,
        transaction,
        document,
        matchedAmount ?? parseFloat(transaction.amount || "0"),
        "manual",
        ctx,
    );
    if (!matched) throw new Error("expense_mutation_batch_rejected");
}

/**
 * Ignore only a fresh, unmatched movement under the required writer gate.
 * Matched, linked or missing targets are left alone; caller context changes only after persistence proof.
 */
export async function ignoreLocalBankTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    ctx: BankPaymentContext,
): Promise<void> {
    const scope = await withExpenseReconciliation(evolu, (scope) => {
    const tx = scope.context.transactions.find((row) => row.id === transactionId);
    if (tx?.bankPaymentClaimJson != null) throw new Error("bank_expense_claim_changed");
    if (!tx || tx.matchStatus !== "unmatched" || tx.businessExpenseId) return scope;
    const result = scope.evolu.update("bankTransaction", {
        id: transactionId,
        matchStatus: "ignored",
    });
    if (!result.ok) throw new Error("expense_mutation_batch_rejected");
    return scope;
    });
    Object.assign(ctx, scope.context);
}

export async function unmatchLocalBankTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    ctx: BankPaymentContext,
    expected?: { companyId: string; claim: string | null },
): Promise<void> {
    const scope = await withExpenseReconciliation(evolu, async (scope) => {
        await unmatchLocalBankTransactionCore(scope.evolu, transactionId, scope.context, expected);
        return scope;
    });
    Object.assign(ctx, scope.context);
}

/**
 * Remove the bank link and reverse invoice payment bookkeeping when applicable.
 * Unlinking an expense deliberately leaves its paid status unchanged.
 */
async function unmatchLocalBankTransactionCore(
    evolu: Evolu<InvoicingLocalSchema>, transactionId: BankTransactionId, ctx: BankPaymentContext,
    expected?: { companyId: string; claim: string | null },
): Promise<void> {
    const candidates = ctx.transactions.filter((row) => row.id === transactionId);
    if (candidates.length !== 1) throw new Error("bank_expense_claim_changed");
    const transaction = candidates[0]!;
    if (expected && (transaction.companyId !== expected.companyId || (transaction.bankPaymentClaimJson ?? null) !== expected.claim)) {
        throw new Error("bank_expense_claim_changed");
    }
    if (transaction.bankPaymentClaimJson != null) {
        if (!expected) throw new Error("bank_expense_claim_changed");
        const ownerId = Reflect.get(transaction, "ownerId");
        if (typeof ownerId !== "string") throw new Error("bank_expense_claim_changed");
        assertExpenseCompanyOwner(expenseReconciliationScope(evolu), transaction.companyId, ownerId);
        const expense = ctx.expenses.find((row) => row.id === transaction.businessExpenseId
            && row.companyId === transaction.companyId && Reflect.get(row, "ownerId") === ownerId);
        const reciprocal = expense?.bankPaymentClaimJson === transaction.bankPaymentClaimJson;
        const claim = expense ? activeBankExpenseClaim(transaction, expense, ctx.matches) : null;
        const transactionPatch = { id: transaction.id, companyId: transaction.companyId, businessExpenseId: null,
            matchStatus: "unmatched" as const, bankPaymentClaimJson: null };
        // A losing selection never clears the winning expense cell. Winning release is independent paid history,
        // represented by the same atomic cell rather than overwriting separately merged manual status/date fields.
        const expensePatch = expense && reciprocal ? { id: expense.id, companyId: expense.companyId,
            bankPaymentClaimJson: claim?.paidIntent ? JSON.stringify({ ...claim, state: "released" }) : null } : null;
        if (!evolu.update("bankTransaction", transactionPatch, { onlyValidate: true }).ok
            || (expensePatch && !evolu.update("expense", expensePatch, { onlyValidate: true }).ok)) throw new Error("expense_mutation_batch_rejected");
        const result = await commitExpenseMutation(evolu, (onComplete) => {
            const transactionResult = evolu.update("bankTransaction", transactionPatch, expensePatch ? undefined : { onComplete });
            const expenseResult = expensePatch ? evolu.update("expense", expensePatch, { onComplete }) : { ok: true };
            return { ok: transactionResult.ok && expenseResult.ok };
        });
        if (!result.ok) throw new Error("expense_mutation_batch_rejected");
        return;
    }

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

/** Explicit expense-side recovery for a losing/orphan selection; never touches another expense's winning bank claim. */
export async function releaseLocalExpenseBankClaim(
    evolu: Evolu<InvoicingLocalSchema>, companyId: CompanyId, expenseId: ExpenseId, expectedClaim: string,
): Promise<void> {
    await withExpenseReconciliation(evolu, async (scope) => {
        const expenses = scope.context.expenses.filter((row) => row.id === expenseId && row.companyId === companyId);
        const expense = expenses[0];
        if (expenses.length !== 1 || !expense || expense.bankPaymentClaimJson !== expectedClaim) throw new Error("bank_expense_claim_changed");
        const ownerId = Reflect.get(expense, "ownerId");
        if (typeof ownerId !== "string") throw new Error("bank_expense_claim_changed");
        assertExpenseCompanyOwner(scope, companyId, ownerId);
        if (releasedBankExpenseReceipt(expense)) return; // Independent paid-preserving history is already detached.
        const ownTransactions = scope.context.transactions.filter((row) => row.companyId === companyId
            && Reflect.get(row, "ownerId") === ownerId && row.businessExpenseId === expenseId
            && row.bankPaymentClaimJson === expectedClaim);
        if (ownTransactions.length === 1) {
            await unmatchLocalBankTransactionCore(scope.evolu, ownTransactions[0]!.id, scope.context, { companyId, claim: expectedClaim });
            return;
        }
        if (ownTransactions.length > 1) throw new Error("bank_expense_claim_changed");
        const cleared = await commitExpenseMutation(scope.evolu, (onComplete) => scope.evolu.update("expense", {
            id: expenseId, companyId, bankPaymentClaimJson: null,
        }, { onComplete }));
        if (!cleared.ok) throw new Error("expense_mutation_batch_rejected");
    });
}

/**
 * Create and link an expense under the shared writer gate.
 * Publish the resulting expense and linkage into caller context only after persistence is verified.
 */
export async function createLocalExpenseFromBankTransaction(
    evolu: Evolu<InvoicingLocalSchema>,
    transactionId: BankTransactionId,
    payload: ExpenseSavePayload & { supplier?: string; category?: string },
    ctx: BankPaymentContext,
): Promise<EvoluExpenseRow> {
    const saved = await withExpenseReconciliation(evolu, (scope) => ({
        id: createExpenseFromBankTransactionInReconciliation(scope, transactionId, payload).id, scope,
    }));
    const fresh = saved.scope.context;
    const expense = fresh.expenses.find((row) => row.id === saved.id);
    const transaction = fresh.transactions.find((row) => row.id === transactionId);
    if (!expense || !transaction || !activeBankExpenseClaim(transaction, expense, fresh.matches)) throw new Error("bank_reconciliation_state_changed");
    Object.assign(ctx, fresh);
    return expense;
}

/**
 * Build an expense from a fresh outgoing movement and explicit user overrides.
 * A live lease is required; expense creation and bank linkage stay in one synchronous mutation batch.
 */
function createExpenseFromBankTransactionInReconciliation(
    scope: ExpenseReconciliationScope, transactionId: BankTransactionId,
    payload: ExpenseSavePayload & { supplier?: string; category?: string },
): EvoluExpenseRow {
    const evolu = scope.evolu;
    const ctx = scope.context;
    const transaction = ctx.transactions.find((row) => row.id === transactionId);
    if (!transaction) throw new Error("Transaction not found.");
    if (transaction.bankPaymentClaimJson != null) throw new Error("bank_expense_claim_changed");
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
        mark_paid: false,
    };
    const paidIntent = payload.mark_paid !== false;
    const paidAt = paidIntent ? new Date().toISOString().slice(0, 10) : null;

    const internalNumber = nextLocalExpenseInternalNumber(transaction.companyId, ctx.expenses);

    const mapped = mapLocalExpensePayloadFields(expensePayload);
    if (!mapped.ok) throw new Error("Could not create expense.");
    const id = ExpenseId.orThrow(createId({ randomBytes: createRandomBytes() }));
    const ownerId = Reflect.get(transaction, "ownerId");
    if (typeof ownerId !== "string") throw new Error("expense_reconciliation_owner_unavailable");
    const expenseRow = { id, companyId: transaction.companyId, ownerId, internalNumber,
        ...mapped.value, status: "recorded", paidAt: null, cancelledAt: null } as unknown as EvoluExpenseRow;
    const claimJson = createBankExpenseClaim(transaction, expenseRow, "create", { paid: paidIntent, paidAt });
    expenseRow.bankPaymentClaimJson = claimJson;
    const link = { id: transactionId, companyId: transaction.companyId, businessExpenseId: id,
        matchStatus: "matched" as const, bankPaymentClaimJson: claimJson };
    if (!evolu.update("bankTransaction", link, { onlyValidate: true }).ok) throw new Error("Could not link expense.");
    const result = insertLocalExpenseInReconciliation(scope, transaction.companyId, expensePayload,
        { id, internalNumber, bankPaymentClaimJson: claimJson });
    if (!result.ok) throw new Error("Could not create expense.");
    if (!evolu.update("bankTransaction", link).ok) throw new Error("expense_mutation_batch_rejected");

    return expenseRow;
}

/** Index related rows once, reserving duplicate keys as ambiguous instead of choosing an arbitrary record. */
function indexBankApiRows<T extends object>(rows: readonly T[], key: (row: T) => string): Map<string, T | null> {
    const index = new Map<string, T | null>();
    for (const row of rows) {
        if (Reflect.get(row, "isDeleted") === 1) continue;
        const rowKey = key(row);
        index.set(rowKey, index.has(rowKey) ? null : row);
    }
    return index;
}

/** Resolve scoped relations once and project only candidates needed by the effective-status filter and limit. */
export function mapLocalBankTransactionsToApi(
    rows: readonly EvoluBankTransactionRow[],
    ctx: BankPaymentContext,
    options: { matchStatus?: string; limit?: number } = {},
) {
    const result: BankTxApiRow[] = [];
    const limit = Math.max(0, options.limit ?? Infinity);
    if (rows.length === 0 || limit === 0) return result;
    const requested = options.matchStatus;
    const selective = requested && requested !== "all";
    const matches = indexBankApiRows(ctx.matches, (row) => bankExpenseScopeKey(Reflect.get(row, "ownerId"), "", row.bankTransactionId));
    const documents = indexBankApiRows(ctx.documents, (row) => bankExpenseScopeKey(Reflect.get(row, "ownerId"), row.companyId, row.id));
    const expenses = indexBankApiRows(ctx.expenses, (row) => bankExpenseScopeKey(Reflect.get(row, "ownerId"), row.companyId, row.id));
    const evidence = { transactions: ctx.transactions, matches: ctx.matches, index: createBankExpensePaymentIndex(ctx.transactions, ctx.matches) };
    for (const row of rows) {
        // Claims can turn any raw status into a conflict/unmatched row, but cannot promote it to another status.
        if (selective && requested !== "unmatched" && row.matchStatus !== requested) continue;
        const owner = Reflect.get(row, "ownerId");
        const expense = row.businessExpenseId
            ? expenses.get(bankExpenseScopeKey(owner, row.companyId, row.businessExpenseId)) ?? null : null;
        if (selective && requested === "unmatched" && row.matchStatus !== requested
            && row.bankPaymentClaimJson == null && expense?.bankPaymentClaimJson == null) continue;
        const match = matches.get(bankExpenseScopeKey(owner, "", row.id)) ?? null;
        const document = match
            ? documents.get(bankExpenseScopeKey(owner, row.companyId, match.businessDocumentId)) ?? null : null;
        const projected = evoluBankTxToApi(row, match, document, expense, evidence);
        if (selective && projected.match_status !== requested) continue;
        result.push(projected);
        if (result.length >= limit) break;
    }
    return result;
}

/**
 * Project the selected company's recent batches and derive recovery availability from persisted unmatched movements.
 * Original import counters alone do not determine whether another match attempt is available.
 */
export function mapLocalBankBatchesToApi(
    batches: EvoluBankImportBatchRow[],
    companyId: CompanyId,
    transactions: EvoluBankTransactionRow[],
) {
    const unmatchedBatches = new Set(transactions
        .filter((row) => row.companyId === companyId && row.matchStatus === "unmatched")
        .map((row) => row.bankImportBatchId));
    return batches
        .filter((row) => row.companyId === companyId)
        .sort((a, b) => (b.createdAt || "").localeCompare(a.createdAt || ""))
        .slice(0, 20)
        .map((row) => ({ ...evoluBankBatchToApi(row), can_auto_match: unmatchedBatches.has(row.id) }));
}

export { filterLocalBankTransactions, buildBankSummary } from "./bankMap";
