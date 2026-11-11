import { effectScope, ref } from "vue";
import { flushPromises } from "@vue/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import { useCompanyActionCenter } from "@/composables/useCompanyActionCenter";
import { emptyActionCenterCategories, type ActionCenterItem, type CompanyActionCenterPayload } from "@/services/companyActionCenter";
import type { ActionCenterLocalSnapshot } from "@/evolu/actionCenterLocal";

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((finish) => { resolve = finish; });
    return { promise, resolve };
}
function remote(items: ActionCenterItem[] = [], count = items.length): CompanyActionCenterPayload {
    const categories = emptyActionCenterCategories("ready");
    categories[0] = { ...categories[0], count, items, has_more: count > items.length };
    return { checked_at: "2026-11-10T12:00:00Z", categories, restricted_sources: [] };
}
function local(items: ActionCenterItem[] = [], bankHashes: string[] = []): ActionCenterLocalSnapshot {
    const categories = emptyActionCenterCategories("ready");
    categories[0] = { ...categories[0], count: items.length, items };
    return { checkedAt: "2026-11-10T12:00:00Z", categories, bankHashes };
}
const item = (id: string, hash?: string): ActionCenterItem => ({ id, kind: "unmatched_bank", reference: id, document_id: null, occurred_at: null, amount: "10.00", currency: "EUR", ...(hash === undefined ? {} : { bank_dedupe_hash: hash }) });
const scopes: ReturnType<typeof effectScope>[] = [];
afterEach(() => { for (const scope of scopes.splice(0)) scope.stop(); });

describe("company action center source states", () => {
    it("does not show an error or all-clear while initial checks are pending", async () => {
        const pending = deferred<CompanyActionCenterPayload>();
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(ref("a"), { localFirst: false, loadRemote: () => pending.promise }))!;
        expect(center.loading.value).toBe(true);
        expect(center.partialError.value).toBe(false);
        expect(center.allClear.value).toBe(false);
        pending.resolve(remote()); await flushPromises();
        expect(center.allClear.value).toBe(true);
    });

    it("keeps local tasks usable when online checks fail and supports recovery", async () => {
        const loadRemote = vi.fn().mockRejectedValueOnce(new Error("offline")).mockResolvedValueOnce(remote());
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(ref("a"), { localFirst: true, loadLocal: async () => local([item("local")]), loadRemote }))!;
        await flushPromises();
        expect(center.partialError.value).toBe(true);
        expect(center.allClear.value).toBe(false);
        expect(center.categories.value[0].items[0].id).toBe("local");
        await center.refresh();
        expect(center.partialError.value).toBe(false);
        expect(center.categories.value[0].count).toBe(1);
    });

    it("discards stale replies and immediately clears previous-company rows on a switch", async () => {
        const first = deferred<CompanyActionCenterPayload>();
        const second = deferred<CompanyActionCenterPayload>();
        const company = ref("a");
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(company, { localFirst: false, loadRemote: (id) => id === "a" ? first.promise : second.promise }))!;
        company.value = "b";
        expect(center.categories.value.every((category) => category.items.length === 0)).toBe(true);
        second.resolve(remote([item("b")])); await flushPromises();
        first.resolve(remote([item("a")])); await flushPromises();
        expect(center.categories.value[0].items.map((entry) => entry.id)).toEqual(["b"]);
    });

    it("deduplicates server bank copies using all local hashes, including resolved records", async () => {
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(ref("a"), { localFirst: true,
            loadLocal: async () => local([], ["already-handled"]),
            loadRemote: async () => remote([item("server-copy", "already-handled"), item("not-imported", "new")]),
        }))!;
        await flushPromises();
        expect(center.categories.value[0].count).toBe(1);
        expect(center.categories.value[0].items.map((entry) => [entry.id, entry.kind])).toEqual([["not-imported", "remote_bank_review"]]);
        expect(center.allClear.value).toBe(false);
    });

    it("does not claim all-clear or an exact combined count from a truncated server sample", async () => {
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(ref("a"), { localFirst: true,
            loadLocal: async () => local([], ["handled"]), loadRemote: async () => remote([item("sample", "handled")], 50),
        }))!;
        await flushPromises();
        expect(center.categories.value[0].items).toEqual([]);
        expect(center.categories.value[0].count).toBeNull();
        expect(center.categories.value[0].has_more).toBe(true);
        expect(center.allClear.value).toBe(false);
    });

    it("keeps unavailable/restricted categories explicit even when other sources are empty", async () => {
        const data = remote();
        data.categories[1] = { key: "failed_sends", state: "unavailable", count: null, items: [], has_more: false };
        data.restricted_sources = ["failed_sends"];
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(ref("a"), { localFirst: false, loadRemote: async () => data }))!;
        await flushPromises();
        expect(center.partialError.value).toBe(true);
        expect(center.restrictedSources.value).toEqual(["failed_sends"]);
        expect(center.allClear.value).toBe(false);
    });

    it("does not publish late replies after disposal", async () => {
        const pending = deferred<CompanyActionCenterPayload>();
        const scope = effectScope(); scopes.push(scope);
        const center = scope.run(() => useCompanyActionCenter(ref("a"), { localFirst: false, loadRemote: () => pending.promise }))!;
        scope.stop(); pending.resolve(remote([item("late")])); await flushPromises();
        expect(center.categories.value.every((category) => category.items.length === 0)).toBe(true);
    });
});
