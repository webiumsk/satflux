import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import * as XLSX from "xlsx";
import { createExpenseWriterTestAdapter } from "./expenseReconciliationTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";

vi.mock("@/evolu/client", async () => {
    const tokens = [
        "allBankImportBatchesQuery", "allBankTransactionMatchesQuery", "allBankTransactionsQuery",
        "allCompaniesDetailQuery", "allCompaniesQuery", "allCompanyStockBalancesQuery",
        "allCompanyStockItemsQuery", "allCompanyStockMovementsQuery", "allCompanyWarehousesQuery",
        "allContactsQuery", "allDocumentEventsQuery", "allDocumentLinesQuery", "allDocumentSnapshotsQuery",
        "allDocumentsQuery", "allExpensesQuery", "allExpenseAttachmentsQuery", "allNumberSeriesQuery",
        "allRecurringProfileLinesQuery", "allRecurringProfilesQuery",
    ];
    return { ...Object.fromEntries(tokens.map((token) => [token, token])),
        ...(await import("./expenseReconciliationTestSupport")).expenseTestQueryExports() };
});

import {
    importLocalExpensesFromFile,
    previewLocalExpenseImport,
    previewLocalExpenseImportDuplicates,
    suggestExpenseImportMapping,
} from "@/evolu/expenseImportLocal";
import { nextLocalExpenseInternalNumber } from "@/evolu/expenseCrud";
import type { ExpenseImportMapping } from "@/composables/useExpenseImportFields";
import type { EvoluExpenseRow } from "@/evolu/expenseMap";
import type { CompanyId } from "@/evolu/schema";

const companyId = "company-a" as CompanyId;
const HEADERS = [
    "Názov", "Kategória", "Dodávateľ", "Číslo dokladu", "Interné číslo dokladu",
    "Dátum vystavenia", "Dátum splatnosti", "Dátum dodania", "Spolu", "Mena",
    "Dátum úhrady", "Druh úhrady", "Suma úhrady", "Mena úhrady", "Kurz úhrady",
    "VS", "Tagy", "Bankový účet dodávateľa", "Dodávateľ IČO", "Dodávateľ DIČ",
    "Dodávateľ IČ DPH", "Dodávateľ telefón", "Dodávateľ email", "Dodávateľ adresa",
    "Dodávateľ PSČ", "Dodávateľ mesto", "Dodávateľ krajina", "Interná poznámka",
    "Konštantný symbol", "Špecifický symbol", "Počet položiek", "Jednotky položiek", "Položky",
];

function csvFile(rows: unknown[][], name = "expenses.csv"): File {
    const text = rows.map((row) => row.map((value) => `"${String(value ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
    return { name, size: new TextEncoder().encode(text).byteLength, text: async () => text } as File;
}

function xlsxFile(rows: unknown[][]): File {
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet(rows), "Expenses");
    const buffer = XLSX.write(workbook, { type: "array", bookType: "xlsx" }) as ArrayBuffer;
    return { name: "expenses.xlsx", size: buffer.byteLength, arrayBuffer: async () => buffer } as File;
}

function fakeEvolu(rows: EvoluExpenseRow[] = []) {
    return createExpenseWriterTestAdapter(rows as unknown as Record<string, unknown>[]).evolu;
}

function existing(internalNumber: string, company = companyId): EvoluExpenseRow {
    return { id: "existing", companyId: company, internalNumber } as EvoluExpenseRow;
}

const MINIMAL_HEADERS = ["Interné číslo", "Dátum vystavenia", "Spolu", "Dátum úhrady"];
const minimalMapping: ExpenseImportMapping = { internal_number: 0, issue_date: 1, total: 2, paid_at: 3 };

async function importMinimal(rows: unknown[][], existingRows: EvoluExpenseRow[] = []) {
    const evolu = fakeEvolu(existingRows);
    const result = await importLocalExpensesFromFile(evolu as never, companyId, csvFile([MINIMAL_HEADERS, ...rows]), minimalMapping, existingRows);
    return { evolu, result };
}
beforeEach(() => { installBankLocks(); });
afterEach(() => { vi.unstubAllGlobals(); });

describe("SuperFaktura local expense import", () => {
    it("automatically maps every column in the current 33-column export", () => {
        const mapping = suggestExpenseImportMapping(HEADERS);
        expect(Object.keys(mapping)).toHaveLength(33);
        expect(new Set(Object.values(mapping)).size).toBe(33);
        expect(mapping.internal_number).toBe(4);
        expect(mapping.supplier_street).toBe(23);
        expect(mapping.supplier_postal_code).toBe(24);
        expect(mapping.supplier_country).toBe(26);
        expect(mapping.payment_amount).toBe(12);
        expect(mapping.items).toBe(32);
    });

    it("imports synthetic XLSX core fields and all supplier/payment/item metadata", async () => {
        const row = [
            "Office supplies", "Office", "Example Supplier", "000123", "000007", "45672", "45686", "45672",
            45000, "EUR", "45673", "Bank transfer", "45000", "EUR", "1", "000123", "office, yearly",
            "EXAMPLE-ACCOUNT", "00123456", "0123456789", "EXAMPLEVAT", "+000000000", "supplier@example.test",
            "Example Street 1", "00100", "Example City", "SK", "Imported private note", "0308", "0009", "2", "pcs", "Paper; ink",
        ];
        const file = xlsxFile([HEADERS, row]);
        const preview = await previewLocalExpenseImport(file);
        expect(preview.row_count).toBe(1);
        const evolu = fakeEvolu();
        const result = await importLocalExpensesFromFile(evolu as never, companyId, file, preview.suggested_mapping, []);
        expect(result).toEqual({ imported: 1, skipped: 0, errors: [] });
        const saved = evolu.insert.mock.calls[0][1];
        expect(saved).toMatchObject({
            companyId, internalNumber: "000007", externalNumber: "000123", title: "Office supplies",
            variableSymbol: "000123", constantSymbol: "0308", specificSymbol: "0009", total: "45000.00", currency: "EUR",
            issueDate: "2025-01-15", dueDate: "2025-01-29", deliveryDate: "2025-01-15", paidAt: "2025-01-16", status: "paid",
        });
        for (const note of [
            "Imported private note", "Tags: office, yearly", "Supplier: Example Supplier", "Category: Office",
            "Supplier registration number: 00123456", "Supplier tax ID: 0123456789", "Supplier VAT ID: EXAMPLEVAT",
            "Supplier phone: +000000000", "Supplier email: supplier@example.test", "Supplier street: Example Street 1",
            "Supplier postal code: 00100", "Supplier city: Example City", "Supplier country: SK",
            "Supplier bank account: EXAMPLE-ACCOUNT", "Payment method: Bank transfer", "Payment amount: 45000",
            "Payment currency: EUR", "Payment exchange rate: 1", "Item quantity: 2", "Item units: pcs", "Items: Paper; ink",
        ]) expect(saved.internalNote).toContain(note);
        expect(evolu.loadQuery).toHaveBeenCalled();
    });

    it("preserves an original payment date for the legacy template without payment metadata", async () => {
        const { evolu, result } = await importMinimal([["A1", "15.01.2025", "1 234,50", "16.01.2025"]]);
        expect(result.imported).toBe(1);
        expect(evolu.insert.mock.calls[0][1]).toMatchObject({ total: "1234.50", paidAt: "2025-01-16", status: "paid" });
    });

    it.each([
        ["40", "EUR"], ["100", "USD"], ["0", "EUR"],
    ])("keeps partial or unresolved cross-currency payments recorded (%s %s)", async (amount, currency) => {
        const headers = ["Interné číslo", "Dátum vystavenia", "Spolu", "Dátum úhrady", "Suma úhrady", "Mena úhrady", "Mena"];
        const file = csvFile([headers, ["A1", "2025-01-15", "100", "2025-01-16", amount, currency, "EUR"]]);
        const evolu = fakeEvolu();
        const result = await importLocalExpensesFromFile(evolu as never, companyId, file, suggestExpenseImportMapping(headers), []);
        expect(result.imported).toBe(1);
        expect(evolu.insert.mock.calls[0][1]).toMatchObject({ status: "recorded", paidAt: null });
        expect(evolu.insert.mock.calls[0][1].internalNote).toContain("Payment date: 2025-01-16");
    });

    it("skips duplicates only in the selected company, including duplicates within the file", async () => {
        const { evolu, result } = await importMinimal([
            ["A1", "2025-01-01", "10", ""], ["A2", "2025-01-01", "20", ""], ["A2", "2025-01-01", "20", ""],
        ], [existing("A1"), existing("A2", "company-b" as CompanyId)]);
        expect(result.imported).toBe(1);
        expect(result.skipped).toBe(2);
        expect(evolu.insert).toHaveBeenCalledTimes(1);
        expect(result.errors.map((error) => error.message)).toEqual(["duplicate_internal_number:A1", "duplicate_internal_number:A2"]);
    });

    it("reserves explicit numbers later in the file before generating numbers", async () => {
        const prefix = `EXP${new Date().getFullYear()}`;
        const { evolu, result } = await importMinimal([
            ["", "2025-01-01", "10", ""], [`${prefix}0001`, "2025-01-01", "20", ""], ["", "2025-01-01", "30", ""],
        ]);
        expect(result.imported).toBe(3);
        expect(evolu.insert.mock.calls.map((call) => call[1].internalNumber)).toEqual([`${prefix}0002`, `${prefix}0001`, `${prefix}0003`]);
    });

    it("skips an explicitly numbered expense when the same file is imported again", async () => {
        const first = await importMinimal([["00001", "2025-01-01", "10", ""]]);
        const saved = first.evolu.insert.mock.calls[0][1] as EvoluExpenseRow;
        const second = await importMinimal([["00001", "2025-01-01", "10", ""]], [saved]);
        expect(second.result.imported).toBe(0);
        expect(second.result.skipped).toBe(1);
        expect(second.evolu.insert).not.toHaveBeenCalled();
    });

    it.each([
        ["2025-02-29", "10", "", "invalid_issue_date"],
        ["2025-01-01", "-1", "", "invalid_total"],
        ["2025-01-01", "1e3", "", "invalid_total"],
        ["2025-01-01", "1000000000000", "", "invalid_total"],
        ["2025-01-01", "10", "31.02.2025", "invalid_paid_at"],
    ])("rejects invalid dates and amounts without creating an expense", async (issueDate, amount, paidAt, error) => {
        const { evolu, result } = await importMinimal([["A1", issueDate, amount, paidAt]]);
        expect(result.errors[0].message).toBe(error);
        expect(result.skipped).toBe(1);
        expect(evolu.insert).not.toHaveBeenCalled();
    });

    it.each([-1, 0.5, NaN, Infinity, 4, true, " ", "1e0", "1.0"])("rejects invalid mapping indices (%s)", async (index) => {
        const evolu = fakeEvolu();
        const mapping = { ...minimalMapping, total: index } as ExpenseImportMapping;
        await expect(importLocalExpensesFromFile(evolu as never, companyId, csvFile([MINIMAL_HEADERS, ["A", "2025-01-01", "1", ""]]), mapping, [])).rejects.toThrow("invalid_mapping:total");
        expect(evolu.insert).not.toHaveBeenCalled();
    });

    it("reports storage failure per row and retries a number only when insertion failed", async () => {
        const evolu = fakeEvolu();
        evolu.insert.mockReturnValueOnce({ ok: false, error: "offline" });
        const file = csvFile([MINIMAL_HEADERS, ["A", "2025-01-01", "10", ""], ["A", "2025-01-01", "10", ""]]);
        const result = await importLocalExpensesFromFile(evolu as never, companyId, file, minimalMapping, []);
        expect(result).toMatchObject({ imported: 1, skipped: 1 });
        expect(result.errors[0].message).toBe("insert_failed");
    });

    it("skips oversized notes rather than silently truncating supplier/item data", async () => {
        const headers = ["Dátum vystavenia", "Spolu", "Položky"];
        const evolu = fakeEvolu();
        const result = await importLocalExpensesFromFile(evolu as never, companyId, csvFile([headers, ["2025-01-01", "10", "x".repeat(4000)]]), suggestExpenseImportMapping(headers), []);
        expect(result.errors[0].message).toBe("internal_note_too_long");
        expect(evolu.insert).not.toHaveBeenCalled();
    });

    it("rejects a file exceeding the row limit before any write", async () => {
        const rows = Array.from({ length: 2001 }, (_, index) => [String(index), "2025-01-01", "10", ""]);
        const evolu = fakeEvolu();
        await expect(importLocalExpensesFromFile(evolu as never, companyId, csvFile([MINIMAL_HEADERS, ...rows]), minimalMapping, [])).rejects.toThrow();
        expect(evolu.insert).not.toHaveBeenCalled();
    });

    it("generates large existing number sequences without floating point collisions", () => {
        const prefix = `EXP${new Date().getFullYear()}`;
        expect(nextLocalExpenseInternalNumber(companyId, [existing(`${prefix}9007199254740992`)])).toBe(`${prefix}9007199254740993`);
    });
});


describe("expense import duplicate preflight", () => {
    const mapping = { title: 0, external_number: 1, issue_date: 2, total: 3, currency: 4 };
    const headers = ["Title", "External number", "Issue date", "Total", "Currency"];
    it("warns about existing and repeated file rows without invoking financial writers", async () => {
        const existing = [{ id: "existing", ownerId: "owner-a", companyId, internalNumber: "EXP001", status: "paid",
            title: "Other", externalNumber: "INV-001", issueDate: "2026-11-01", total: "19.00", currency: "EUR", cancelledAt: null }] as unknown as EvoluExpenseRow[];
        const file = csvFile([headers, ["Fee", "INV-001", "2026-11-05", "19.00", "EUR"],
            ["Hosting", "INV-002", "2026-11-05", "29.00", "EUR"], ["Hosting", "INV-002", "2026-11-05", "29.00", "EUR"]]);
        const before = JSON.stringify(existing);
        expect(await previewLocalExpenseImportDuplicates(companyId, "owner-a", file, mapping, existing)).toMatchObject([
            { row: 2, expense: { id: "existing" }, reason: "external_number", in_file: false },
            { row: 4, expense: { id: "preview-row-3", internal_number: "3" }, reason: "external_number", in_file: true },
        ]);
        expect(JSON.stringify(existing)).toBe(before);
    });
    it("ignores other owner/company records and caps repetitive output at twenty warnings", async () => {
        const existing = [{ id: "foreign", ownerId: "owner-b", companyId, internalNumber: "EXP001", status: "recorded",
            externalNumber: "INV-001", title: "Fee", issueDate: "2026-11-05", total: "19.00", currency: "EUR" },
        { id: "other-company", ownerId: "owner-a", companyId: "company-b", internalNumber: "EXP002", status: "recorded",
            externalNumber: "INV-001", title: "Fee", issueDate: "2026-11-05", total: "19.00", currency: "EUR" }] as unknown as EvoluExpenseRow[];
        const single = csvFile([headers, ["Fee", "INV-001", "2026-11-05", "19.00", "EUR"]]);
        expect(await previewLocalExpenseImportDuplicates(companyId, "owner-a", single, mapping, existing)).toEqual([]);
        const repeated = csvFile([headers, ...Array.from({ length: 40 }, () => ["Fee", "INV-001", "2026-11-05", "19.00", "EUR"])]);
        const warnings = await previewLocalExpenseImportDuplicates(companyId, "owner-a", repeated, mapping, []);
        expect(warnings).toHaveLength(20);
        expect(warnings.every((row) => row.in_file)).toBe(true);
    });
    it("compares the rounded amount that import will persist for existing and repeated rows", async () => {
        const existing = [{ id: "existing-rounded", ownerId: "owner-a", companyId, internalNumber: "EXP001", status: "recorded",
            externalNumber: "INV-001", title: "Fee", issueDate: "2026-11-05", total: "19.00", currency: "EUR" }] as unknown as EvoluExpenseRow[];
        const file = csvFile([headers, ["Fee", "INV-001", "2026-11-05", "19.001", "EUR"],
            ["Hosting", "INV-002", "2026-11-05", "29.004", "EUR"], ["Hosting", "INV-002", "2026-11-05", "29.00", "EUR"]]);
        expect(await previewLocalExpenseImportDuplicates(companyId, "owner-a", file, mapping, existing)).toMatchObject([
            { row: 2, expense: { id: "existing-rounded" }, in_file: false },
            { row: 4, expense: { id: "preview-row-3" }, in_file: true },
        ]);
    });
});
