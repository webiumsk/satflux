import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { defineComponent, ref } from "vue";
import { useLocalInvoicingCompanies } from "../composables/useInvoicingCompaniesLocal";

const mocks = vi.hoisted(() => ({
    loadQuery: vi.fn(),
    pending: false,
    pull: vi.fn(),
    wait: vi.fn(),
    rows: [] as Record<string, unknown>[],
}));
vi.mock("@/evolu/client", () => ({
    allCompaniesQuery: "companies",
    allDocumentsQuery: "documents",
    useInvoicingEvolu: () => ({ loadQuery: mocks.loadQuery }),
}));
vi.mock("@evolu/vue", () => ({ useQuery: (query: string) => ref(query === "companies" ? mocks.rows : []) }));
vi.mock("@/evolu/relaySyncWait", () => ({
    isEvoluRelaySyncPending: () => mocks.pending,
    pullInvoicingFromRelay: mocks.pull,
    waitForInvoicingRelaySync: mocks.wait,
}));
vi.mock("@/evolu/multiTab", () => ({ detectEvoluMultiTabContention: async () => false }));

let wrapper: ReturnType<typeof mount> | undefined;
function setup() {
    let result!: ReturnType<typeof useLocalInvoicingCompanies>;
    wrapper = mount(defineComponent({
        setup() { result = useLocalInvoicingCompanies(); return () => null; },
    }));
    return result;
}
beforeEach(() => {
    mocks.pending = false;
    mocks.rows = [{ id: "company", legalName: "Retained local company", tradeName: null }];
    mocks.loadQuery.mockReset().mockImplementation(async query => query === "companies" ? mocks.rows : []);
    mocks.pull.mockReset().mockImplementation(() => new Promise(() => undefined));
    mocks.wait.mockReset().mockImplementation(() => new Promise(() => undefined));
});
afterEach(() => { wrapper?.unmount(); vi.useRealTimers(); });

it("shows retained local companies without waiting for a relay pull", async () => {
    const result = setup();
    await flushPromises();
    expect(result.loading.value).toBe(false);
    expect(result.loadError.value).toBe(false);
    expect(result.companies.value).toMatchObject([{ legal_name: "Retained local company" }]);
    expect(mocks.pull).not.toHaveBeenCalled();
    await result.refresh();
    expect(result.loading.value).toBe(false);
    expect(mocks.pull).not.toHaveBeenCalled();
});

it("keeps the existing pending-recovery gate before publishing a loaded list", async () => {
    mocks.pending = true;
    const result = setup();
    await flushPromises();
    expect(result.loading.value).toBe(true);
    expect(mocks.wait).toHaveBeenCalledOnce();
    expect(mocks.pull).not.toHaveBeenCalled();
});

it("still attempts relay recovery for an empty local list", async () => {
    mocks.rows = [];
    const result = setup();
    await flushPromises();
    expect(result.loading.value).toBe(true);
    expect(mocks.pull).toHaveBeenCalledOnce();
});

it("finishes with a load error when a local query never answers", async () => {
    vi.useFakeTimers();
    mocks.loadQuery.mockImplementation(() => new Promise(() => undefined));
    const result = setup();
    await vi.advanceTimersByTimeAsync(30_001);
    expect(result.loading.value).toBe(false);
    expect(result.loadError.value).toBe(true);
    expect(mocks.pull).not.toHaveBeenCalled();
});
