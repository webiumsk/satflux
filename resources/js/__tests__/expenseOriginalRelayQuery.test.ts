// @vitest-environment node
import { describe, expect, it, vi } from "vitest";
import { DatabaseSync } from "node:sqlite";
import { deserializeQuery } from "@evolu/common/local-first";

// Keep the real client, owner-scoping wrapper and SDK query serializer. Only
// replace worker construction, so these tests never touch browser storage.
vi.mock("@/evolu/reloadGuard", () => ({ createEvoluWebDepsWithReloadGuard: () => ({
    console: { ...console, log: vi.fn() }, randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
    time: { now: () => Date.now(), nowIso: () => new Date().toISOString() },
    createDbWorker: () => ({ postMessage: vi.fn(), onMessage: vi.fn() }), reloadApp: vi.fn(),
}) }));

import { allExpenseOriginalChunksQuery } from "@/evolu/client";

function database() {
    const db = new DatabaseSync(":memory:");
    db.exec(`CREATE TABLE migrationSource (
        id TEXT, ownerId TEXT, companyId TEXT, sourceKind TEXT, isDeleted INTEGER, savedAt TEXT
    ); CREATE TABLE migrationChunk (
        id TEXT, ownerId TEXT, companyId TEXT, sourceId TEXT, chunkIndex TEXT,
        contentBase64 TEXT, isDeleted INTEGER, createdAt TEXT
    );`);
    return db;
}

function readChunks(db: DatabaseSync) {
    const { sql, parameters } = deserializeQuery(allExpenseOriginalChunksQuery);
    return db.prepare(sql).all(...parameters);
}

describe("production owner-scoped original chunk query", () => {
    it("executes on an empty database with the real wrapper's added owner column", () => {
        const db = database();
        try {
            expect(readChunks(db)).toEqual([]);
        } finally { db.close(); }
    });

    it("keeps source id, owner, company, kind and live-row bindings while retaining incomplete sync evidence", () => {
        const db = database();
        try {
            const source = db.prepare("INSERT INTO migrationSource VALUES (?, ?, ?, ?, ?, ?)");
            source.run("source", "owner-a", "company-a", "efaktura-original", 0, "2026-11-16T00:00:00Z");
            source.run("source", "owner-b", "company-b", "efaktura-original", 0, "2026-11-16T00:00:00Z");
            source.run("pending", "owner-a", "company-a", "efaktura-original", 0, null);
            source.run("deleted", "owner-a", "company-a", "efaktura-original", 1, null);
            source.run("migration", "owner-a", "company-a", "superfaktura", 0, null);
            const chunk = db.prepare("INSERT INTO migrationChunk VALUES (?, ?, ?, ?, '0', 'c3ludGhldGlj', ?, '2026-11-16T00:00:00Z')");
            chunk.run("complete-a", "owner-a", "company-a", "source", 0);
            chunk.run("complete-b", "owner-b", "company-b", "source", 0);
            chunk.run("pending-a", "owner-a", "company-a", "pending", 0);
            chunk.run("foreign-owner", "owner-c", "company-a", "source", 0);
            chunk.run("foreign-company", "owner-a", "company-b", "source", 0);
            chunk.run("deleted-source", "owner-a", "company-a", "deleted", 0);
            chunk.run("migration-source", "owner-a", "company-a", "migration", 0);
            chunk.run("missing-source", "owner-a", "company-a", "missing", 0);
            chunk.run("deleted-chunk", "owner-a", "company-a", "source", 1);
            expect(readChunks(db).map(row => [row.id, row.ownerId, row.contentBase64])).toEqual([
                ["complete-a", "owner-a", "c3ludGhldGlj"],
                ["complete-b", "owner-b", "c3ludGhldGlj"],
                ["pending-a", "owner-a", "c3ludGhldGlj"],
            ]);
        } finally { db.close(); }
    });
});
