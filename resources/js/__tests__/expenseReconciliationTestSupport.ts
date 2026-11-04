import { vi } from "vitest";
import { bankTestQueryTable } from "./bankReconciliationTestSupport";

type Row = Record<string, unknown>;
type Options = { ownerId?: string; onlyValidate?: boolean; onComplete?: () => void };
type Result = { ok: boolean; value?: { id: string }; error?: unknown };

export function expenseTestQueryExports() {
    const tables = { Batches: "bankImportBatch", Transactions: "bankTransaction", Matches: "bankTransactionMatch", Documents: "document", Expenses: "expense" };
    const exports: Record<string, string> = {};
    for (const [name, table] of Object.entries(tables)) {
        exports[`bankReconciliation${name}Query`] = `fresh:${table}`;
        exports[`all${name === "Batches" ? "BankImportBatches" : name === "Transactions" ? "BankTransactions" : name === "Matches" ? "BankTransactionMatches" : name}Query`] = `ui:${table}`;
    }
    exports.allCompaniesQuery = "ui:company";
    exports.allCompaniesDetailQuery = "ui:company";
    exports.allCompanySharesQuery = "ui:companyShare";
    return exports;
}

/** Synthetic storage adapter; native-worker concurrency is independently tested. */
export function createExpenseWriterTestAdapter(existing: readonly Row[] = [], companyId = "company-a") {
    const appOwnerId = "app-owner";
    const state: Record<string, Row[]> = { expense: existing.map((row) => ({ ...row, ownerId: row.ownerId ?? appOwnerId })),
        company: [...new Set([companyId, ...existing.map((row) => String(row.companyId))])]
            .map((id) => ({ id, ownerId: appOwnerId, legalName: "Synthetic company" })),
        companyShare: [], bankImportBatch: [], bankTransaction: [], bankTransactionMatch: [], document: [], _expenseReconciliation: [] };
    let sequence = 0;
    const queryRows = (query: unknown) => {
        const table = typeof query === "string" && /^(?:fresh|ui):/.test(query) ? query.slice(query.indexOf(":") + 1) : bankTestQueryTable(query);
        return (state[table ?? ""] ?? []).map((row) => ({ ...row }));
    };
    const mutate = (kind: "insert" | "update" | "upsert", table: string, props: Row, options?: Options): Result => {
        const id = kind === "insert" ? `expense-${++sequence}` : String(props.id);
        if (!options?.onlyValidate) {
            const rows = state[table] ??= [];
            const ownerId = options?.ownerId ?? appOwnerId;
            let row = rows.find((candidate) => candidate.id === id && candidate.ownerId === ownerId);
            if (!row) { row = { id, ownerId }; rows.push(row); }
            Object.assign(row, props);
            queueMicrotask(() => options?.onComplete?.());
        }
        return { ok: true, value: { id } };
    };
    const evolu = {
        appOwner: Promise.resolve({ id: appOwnerId }), getError: () => null, subscribeError: () => () => {},
        loadQuery: vi.fn(async (query: unknown) => queryRows(query)), getQueryRows: vi.fn(queryRows),
        insert: vi.fn((table: string, props: Row, options?: Options): Result => mutate("insert", table, props, options)),
        update: vi.fn((table: string, props: Row, options?: Options): Result => mutate("update", table, props, options)),
        upsert: vi.fn((table: string, props: Row, options?: Options): Result => mutate("upsert", table, props, options)),
    };
    return { evolu, state, appOwnerId };
}
