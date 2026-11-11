import { isMovementTransaction, evoluBankTxToApi, type EvoluBankTransactionRow, type EvoluBankTransactionMatchRow } from "./bankMap";
import { createBankExpensePaymentIndex } from "./bankExpenseClaims";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluExpenseRow } from "./expenseMap";
import type { EvoluDocumentEventRow } from "./documentEventLog";
import { emptyActionCenterCategories, type ActionCenterCategory, type ActionCenterItem } from "@/services/companyActionCenter";

type Owned<T> = T & { ownerId?: unknown; isDeleted?: unknown };
function failureTime(event: EvoluDocumentEventRow): number {
    const recorded = Date.parse(event.createdAt);
    try {
        const metadata = JSON.parse(event.metadataJson ?? 'null');
        const started = typeof metadata?.attempt_started_at === 'string' ? Date.parse(metadata.attempt_started_at) : NaN;
        if (Number.isFinite(started) && started <= recorded) return started;
    } catch { /* Older events use their recorded timestamp. */ }
    return recorded;
}
export type ActionCenterLocalRows = {
    transactions: readonly Owned<EvoluBankTransactionRow>[];
    matches: readonly Owned<EvoluBankTransactionMatchRow>[];
    documents: readonly Owned<EvoluDocumentRow>[];
    expenses: readonly Owned<EvoluExpenseRow>[];
    events: readonly Owned<EvoluDocumentEventRow>[];
};
export type ActionCenterLocalSnapshot = {
    categories: ActionCenterCategory[];
    bankHashes: string[];
    checkedAt: string;
};

/** Compare owner partitions as well as company ids; duplicate ids in another partition are not relations. */
export function buildLocalActionCenter(companyId: string, ownerId: string, rows: ActionCenterLocalRows): ActionCenterLocalSnapshot {
    const owned = <T extends { ownerId?: unknown; isDeleted?: unknown }>(row: T) => row.ownerId === ownerId && row.isDeleted !== 1;
    const inCompany = <T extends { companyId: unknown; ownerId?: unknown; isDeleted?: unknown }>(row: T) => owned(row) && row.companyId === companyId;
    const transactions = rows.transactions.filter(inCompany);
    const transactionIds = new Set(transactions.map((row) => String(row.id)));
    const matches = rows.matches.filter((row) => owned(row) && transactionIds.has(String(row.bankTransactionId)));
    const documents = rows.documents.filter(inCompany);
    const expenses = rows.expenses.filter(inCompany);
    const documentIds = new Set(documents.map((row) => String(row.id)));
    const events = rows.events.filter((row) => owned(row) && documentIds.has(row.documentId));
    const expenseIndex = createBankExpensePaymentIndex(transactions, matches);
    const payments: ActionCenterItem[] = [];
    for (const row of transactions.filter(isMovementTransaction)) {
        const matchRows = matches.filter((entry) => entry.bankTransactionId === row.id);
        const match = matchRows.length === 1 ? matchRows[0] : null;
        const document = match ? documents.find((entry) => entry.id === match.businessDocumentId) ?? null : null;
        const expense = row.businessExpenseId ? expenses.find((entry) => entry.id === row.businessExpenseId) ?? null : null;
        const projected = evoluBankTxToApi(row, match, document, expense, { transactions, matches, index: expenseIndex });
        if (projected.match_status !== "unmatched" || projected.match || projected.expense) continue;
        payments.push({ id: String(row.id), kind: "unmatched_bank", reference: row.reference || row.variableSymbol || null,
            document_id: null, occurred_at: row.bookedAt, amount: row.amount, currency: row.currency });
    }
    const failures: ActionCenterItem[] = [];
    for (const document of documents) {
        const emailEvents = events.filter((event) => event.documentId === document.id
            && (event.action === "business_document.email_failed" || event.action === "business_document.email_sent"));
        const failed = emailEvents.filter((event) => event.action === "business_document.email_failed")
            .sort((a, b) => (failureTime(b) - failureTime(a)) || b.id.localeCompare(a.id));
        const latest = failed[0];
        if (!latest) continue;
        const failedAt = failureTime(latest);
        const successes = emailEvents.filter((event) => event.action === "business_document.email_sent").map((event) => Date.parse(event.createdAt));
        if (document.emailSentAt) successes.push(Date.parse(document.emailSentAt));
        // Only later, valid success evidence clears a failure. Unknown timestamps
        // and simultaneous conflicting events cannot silently hide a failed send.
        if (failed.every((event) => Number.isFinite(failureTime(event))) && Number.isFinite(failedAt)
            && successes.some((at) => Number.isFinite(at) && at > failedAt)) continue;
        failures.push({ id: latest.id, kind: "local_email_failed", reference: document.number,
            document_id: String(document.id), document_type: document.documentType, occurred_at: latest.createdAt,
            amount: document.total, currency: document.currency });
    }
    const categories = emptyActionCenterCategories("ready");
    for (const [key, items] of [["unmatched_payments", payments], ["failed_sends", failures]] as const) {
        const category = categories.find((entry) => entry.key === key)!;
        const sorted = [...items].sort((a, b) => (b.occurred_at ?? "").localeCompare(a.occurred_at ?? "") || a.id.localeCompare(b.id));
        category.count = sorted.length;
        category.items = sorted.slice(0, 25);
        category.has_more = sorted.length > 25;
    }
    return { categories, bankHashes: transactions.map((row) => row.dedupeHash).filter((hash): hash is string => Boolean(hash)), checkedAt: new Date().toISOString() };
}

/** Read encrypted local rows only; no reconciliation, import, issuance or server writes occur. */
export async function loadLocalActionCenter(companyId: string, signal: AbortSignal): Promise<ActionCenterLocalSnapshot> {
    const [client, shares, { loadEvoluQueryWithTimeout, toAppRows }] = await Promise.all([
        import("./client"), import("./companyShareRegistry"), import("./queryLoad"),
    ]);
    await shares.waitCompanyShareRegistryReady(signal);
    const routing = shares.companyShareRoutingKey(companyId);
    const current = () => !signal.aborted && routing === shares.companyShareRoutingKey(companyId);
    const companies = await loadEvoluQueryWithTimeout(client.evolu, client.allCompaniesDetailQuery);
    if (!current()) throw new Error("action_center_context_changed");
    const selected = companies.filter((row) => row.id === companyId);
    const company = selected[0];
    if (selected.length !== 1 || !company || !("ownerId" in company) || typeof company.ownerId !== "string") throw new Error("action_center_company_unavailable");
    const share = shares.companyShareInfo(companyId);
    const owner = share?.ownerId ?? (await client.evolu.appOwner).id;
    if (!current() || company.ownerId !== owner || (share && share.status !== "active" && share.status !== "migrating")) {
        throw new Error("action_center_company_unavailable");
    }
    const [transactions, matches, documents, expenses, events] = await Promise.all([
        loadEvoluQueryWithTimeout(client.evolu, client.allBankTransactionsQuery),
        loadEvoluQueryWithTimeout(client.evolu, client.allBankTransactionMatchesQuery),
        loadEvoluQueryWithTimeout(client.evolu, client.allDocumentsQuery),
        loadEvoluQueryWithTimeout(client.evolu, client.allExpensesQuery),
        loadEvoluQueryWithTimeout(client.evolu, client.allDocumentEventsQuery),
    ]);
    if (!current()) throw new Error("action_center_context_changed");
    return buildLocalActionCenter(companyId, owner, {
        transactions: toAppRows(transactions), matches: toAppRows(matches), documents: toAppRows(documents),
        expenses: toAppRows(expenses), events: toAppRows(events),
    });
}
