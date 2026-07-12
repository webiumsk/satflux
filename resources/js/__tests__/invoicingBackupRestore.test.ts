import { describe, expect, it, vi, beforeEach } from "vitest";

vi.mock("@/evolu/client", () => {
    const tokens = [
        "allBankImportBatchesQuery",
        "allBankTransactionMatchesQuery",
        "allBankTransactionsQuery",
        "allCompaniesDetailQuery",
        "allCompanyStockBalancesQuery",
        "allCompanyStockItemsQuery",
        "allCompanyStockMovementsQuery",
        "allCompanyWarehousesQuery",
        "allContactsQuery",
        "allDocumentEventsQuery",
        "allDocumentLinesQuery",
        "allDocumentSnapshotsQuery",
        "allDocumentsQuery",
        "allExpensesQuery",
        "allExpenseAttachmentsQuery",
        "allNumberSeriesQuery",
        "allRecurringProfileLinesQuery",
        "allRecurringProfilesQuery",
    ];
    return Object.fromEntries(tokens.map((token) => [token, token]));
});

import {
    parseAndValidateBackup,
    restoreInvoicingBackup,
} from "@/evolu/invoicingBackupRestore";
import { buildBackupEnvelopeFromSnapshot } from "@/evolu/invoicingBackup";
import { EMPTY_INVOICING_SNAPSHOT } from "@/evolu/invoicingSnapshot";
import {
    isBackupReminderSnoozed,
    isBackupStale,
    snoozeBackupReminder,
} from "@/services/backupState";
import { sha256Hex } from "@/utils/sha256";

const snapshot = {
    ...EMPTY_INVOICING_SNAPSHOT,
    company: [{ id: "co-1", legalName: "ACME s.r.o." }],
    document: [{ id: "d-1", companyId: "co-1", title: "Invoice" }],
};

async function validBackupText(ownerId = "owner-1"): Promise<string> {
    return JSON.stringify(await buildBackupEnvelopeFromSnapshot(snapshot, ownerId));
}

beforeEach(() => {
    localStorage.clear();
});

describe("parseAndValidateBackup", () => {
    it("accepts a valid backup and reports counts and owner pairing", async () => {
        const ownerHash = await sha256Hex("owner-1");
        const result = await parseAndValidateBackup(await validBackupText(), ownerHash);

        expect(result.ok).toBe(true);
        if (result.ok) {
            expect(result.value.tableCounts.company).toBe(1);
            expect(result.value.tableCounts.document).toBe(1);
            expect(result.value.ownerMatches).toBe(true);
        }
    });

    it("flags an owner mismatch without blocking", async () => {
        const otherHash = await sha256Hex("someone-else");
        const result = await parseAndValidateBackup(await validBackupText(), otherHash);
        expect(result.ok && result.value.ownerMatches).toBe(false);
    });

    it("rejects invalid JSON, foreign formats and unsupported versions", async () => {
        expect(await parseAndValidateBackup("{oops", null)).toEqual({ ok: false, error: "invalid_json" });
        expect(await parseAndValidateBackup(JSON.stringify({ hello: 1 }), null)).toEqual({
            ok: false,
            error: "invalid_format",
        });
        const future = JSON.parse(await validBackupText());
        future.version = 99;
        expect(await parseAndValidateBackup(JSON.stringify(future), null)).toEqual({
            ok: false,
            error: "unsupported_version",
        });
    });

    it("rejects a tampered or hash-less file", async () => {
        const tampered = JSON.parse(await validBackupText());
        tampered.data.document[0].title = "Changed";
        expect(await parseAndValidateBackup(JSON.stringify(tampered), null)).toEqual({
            ok: false,
            error: "hash_mismatch",
        });

        const hashless = JSON.parse(await validBackupText());
        delete hashless.sha256;
        expect(await parseAndValidateBackup(JSON.stringify(hashless), null)).toEqual({
            ok: false,
            error: "hash_missing",
        });
    });
});

describe("restoreInvoicingBackup", () => {
    it("upserts every row through the snapshot restore and defaults missing tables", async () => {
        const validated = await parseAndValidateBackup(await validBackupText(), null);
        if (!validated.ok) throw new Error("expected valid backup");

        // Older backup without newer tables must not crash the restore.
        delete (validated.value.envelope.data as Record<string, unknown>).documentSnapshot;

        const upsert = vi.fn((_table: string, _row: unknown, options?: { onComplete?: () => void }) => {
            options?.onComplete?.();
            return { ok: true, value: { id: "x" } };
        });
        const evolu = { upsert } as never;

        const report = await restoreInvoicingBackup(evolu, validated.value);
        expect(report.upserted).toBe(2);
        expect(report.failed).toEqual([]);
    });
});

describe("backup staleness and snooze", () => {
    const meta = { exportedAt: "2026-07-01T00:00:00.000Z", sha256: "", tableCounts: {} };

    it("treats missing or old exports as stale", () => {
        expect(isBackupStale(null)).toBe(true);
        expect(isBackupStale(meta, new Date("2026-07-12T00:00:00Z"))).toBe(true);
        expect(isBackupStale(meta, new Date("2026-07-05T00:00:00Z"))).toBe(false);
    });

    it("snoozes the reminder for the requested window", () => {
        const now = new Date("2026-07-12T00:00:00Z");
        expect(isBackupReminderSnoozed(now)).toBe(false);
        snoozeBackupReminder(now);
        expect(isBackupReminderSnoozed(new Date("2026-07-18T00:00:00Z"))).toBe(true);
        expect(isBackupReminderSnoozed(new Date("2026-07-20T00:00:00Z"))).toBe(false);
    });
});
