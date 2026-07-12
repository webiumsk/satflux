import { describe, expect, it, vi, beforeEach } from "vitest";

// invoicingBackup transitively imports the real Evolu client through
// invoicingSnapshot - replace it with inert query tokens before importing.
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
    INVOICING_BACKUP_FORMAT,
    INVOICING_BACKUP_VERSION,
    backupFilename,
    backupTableCounts,
    buildBackupEnvelopeFromSnapshot,
} from "@/evolu/invoicingBackup";
import { EMPTY_INVOICING_SNAPSHOT } from "@/evolu/invoicingSnapshot";
import { deterministicStringify } from "@/evolu/documentSnapshotCrud";
import { sha256Hex } from "@/utils/sha256";

const snapshot = {
    ...EMPTY_INVOICING_SNAPSHOT,
    company: [{ id: "co-1", legalName: "ACME s.r.o." }],
    document: [
        { id: "d-1", companyId: "co-1", title: "Invoice", total: "120.00" },
        { id: "d-2", companyId: "co-1", title: "Quote", total: "10.00" },
    ],
};

beforeEach(() => {
    localStorage.clear();
});

describe("buildBackupEnvelopeFromSnapshot", () => {
    it("wraps the snapshot with format, counts, owner hash and integrity hash", async () => {
        const now = new Date("2026-07-12T10:00:00.000Z");
        const envelope = await buildBackupEnvelopeFromSnapshot(snapshot, "owner-1", now);

        expect(envelope.format).toBe(INVOICING_BACKUP_FORMAT);
        expect(envelope.version).toBe(INVOICING_BACKUP_VERSION);
        expect(envelope.created_at).toBe("2026-07-12T10:00:00.000Z");
        expect(envelope.table_counts.company).toBe(1);
        expect(envelope.table_counts.document).toBe(2);
        expect(envelope.owner_id_hash).toBe(await sha256Hex("owner-1"));
        expect(envelope.sha256).toBe(await sha256Hex(deterministicStringify(snapshot)));
        expect(envelope.data).toEqual(snapshot);
    });

    it("round-trips through JSON with a verifiable hash", async () => {
        const envelope = await buildBackupEnvelopeFromSnapshot(snapshot, "owner-1");
        const parsed = JSON.parse(JSON.stringify(envelope));

        expect(await sha256Hex(deterministicStringify(parsed.data))).toBe(parsed.sha256);

        // Tampering with the data breaks the hash.
        parsed.data.document[0].total = "999.00";
        expect(await sha256Hex(deterministicStringify(parsed.data))).not.toBe(parsed.sha256);
    });

    it("omits the owner hash without an owner id and never embeds the raw id", async () => {
        const anonymous = await buildBackupEnvelopeFromSnapshot(snapshot, null);
        expect(anonymous.owner_id_hash).toBeNull();

        const named = await buildBackupEnvelopeFromSnapshot(snapshot, "owner-1");
        expect(JSON.stringify(named)).not.toContain("owner-1");
    });
});

describe("backupTableCounts", () => {
    it("counts every table in the snapshot", () => {
        const counts = backupTableCounts(snapshot);
        expect(counts.company).toBe(1);
        expect(counts.document).toBe(2);
        expect(counts.contact).toBe(0);
    });
});

describe("backupFilename", () => {
    it("uses the satflux-backup-YYYY-MM-DD.json pattern", () => {
        expect(backupFilename(new Date(2026, 6, 12))).toBe("satflux-backup-2026-07-12.json");
        expect(backupFilename(new Date(2026, 0, 5))).toBe("satflux-backup-2026-01-05.json");
    });
});
