import { describe, expect, it } from "vitest";
import { applyPatches, makePatches } from "@evolu/common/local-first";
import { dedupeRowsById } from "../evolu/duplicateCompanies";

/**
 * Demonstration of the query-layer duplicate mechanism (P2 phase 5,
 * docs/QUERY_LAYER_DUPLICATES_INVESTIGATION.md).
 *
 * Evolu's reactive query results flow through a STATEFUL, index-based patch
 * protocol: the worker diffs the new SQL result against a per-tab cache of
 * the previous result (makePatches) and the client applies the patches to
 * ITS cached rows (applyPatches, Array.toSpliced). Correctness rests on the
 * invariant "client rows === worker's previousRows at patch time". When the
 * two caches desync (a lost response within a page lifetime), a replaceAt
 * patch splices against the wrong baseline and the SAME row id can appear
 * at two indexes - exactly the phantom duplicate observed in production
 * (PR #126). These tests pin the mechanism deterministically; they do NOT
 * claim the transport loses messages in any specific browser.
 *
 * Versions: @evolu/common 7.4.1, @evolu/web 2.4.0, @evolu/vue 1.4.0.
 */

type Row = { id: string; name: string };

const rowA: Row = { id: "A", name: "Webium A" };
const rowB: Row = { id: "B", name: "Webium B" };
const rowC: Row = { id: "C", name: "Webium C" };
const rowAEdited: Row = { id: "A", name: "Webium A (edited)" };

describe("Evolu query patch protocol (documented mechanism)", () => {
    it("stays consistent while the client mirrors the worker's previous rows", () => {
        const workerPrevious = [rowA, rowB, rowC];
        const workerNext = [rowAEdited, rowB, rowC];
        const patches = makePatches(workerPrevious as never, workerNext as never);

        // In-place edit yields an index patch, not a full replace.
        expect(patches).toEqual([{ op: "replaceAt", value: rowAEdited, index: 0 }]);

        const clientRows = applyPatches(patches, workerPrevious as never);
        expect(clientRows).toEqual(workerNext);
    });

    it("materializes a duplicate id when the client baseline desynced (same length)", () => {
        // The worker believes the previous result was [B, C, A] (e.g. the
        // client never received an earlier reorder patch), the client still
        // holds [A, B, C].
        const workerPrevious = [rowB, rowC, rowA];
        const clientRows = [rowA, rowB, rowC];

        // Sync edits row A - the worker emits replaceAt(2) for ITS baseline.
        const workerNext = [rowB, rowC, rowAEdited];
        const patches = makePatches(workerPrevious as never, workerNext as never);
        expect(patches).toEqual([{ op: "replaceAt", value: rowAEdited, index: 2 }]);

        // Applied to the desynced client baseline, id "A" now exists twice
        // and row C is gone - the phantom duplicate.
        const corrupted = applyPatches(patches, clientRows as never) as unknown as Row[];
        expect(corrupted.map((row) => row.id)).toEqual(["A", "B", "A"]);

        // The app-level guard collapses the phantom back to one row per id.
        const deduped = dedupeRowsById(corrupted);
        expect(deduped.map((row) => row.id)).toEqual(["A", "B"]);
    });

    it("appends (duplicating) when the client baseline is shorter than the patch index", () => {
        const patches = [{ op: "replaceAt" as const, value: rowA, index: 5 }];
        const corrupted = applyPatches(patches as never, [rowA, rowB] as never) as unknown as Row[];
        // toSpliced past the end appends instead of replacing.
        expect(corrupted.map((row) => row.id)).toEqual(["A", "B", "A"]);
        expect(dedupeRowsById(corrupted).map((row) => row.id)).toEqual(["A", "B"]);
    });

    it("length changes always fall back to replaceAll (self-healing path)", () => {
        const patches = makePatches([rowA, rowB] as never, [rowA, rowB, rowC] as never);
        expect(patches).toEqual([{ op: "replaceAll", value: [rowA, rowB, rowC] }]);
        // A fresh worker cache (undefined previous) also yields replaceAll.
        expect(makePatches(undefined, [rowA] as never)).toEqual([
            { op: "replaceAll", value: [rowA] },
        ]);
    });
});
