import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { config, flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { ref } from "vue";
import { createI18n } from "vue-i18n";
import en from "@/locales/en.json";

const mocks = vi.hoisted(() => ({
    preview: vi.fn(),
    run: vi.fn(),
    serverPreview: vi.fn(),
    serverRun: vi.fn(),
    refresh: vi.fn(),
}));
vi.mock("@/evolu/expenseImportLocal", () => ({
    previewLocalExpenseImport: mocks.preview,
    importLocalExpensesFromFile: mocks.run,
    downloadExpenseImportExampleCsv: vi.fn(),
}));
vi.mock("@/evolu/client", () => ({ useInvoicingEvolu: () => ({}) }));
vi.mock("@/composables/useInvoicingExpenses", () => ({
    useInvoicingExpenses: () => ({ expenseRows: ref([]), refresh: mocks.refresh }),
}));
vi.mock("@/services/api", () => ({
    invoicingApi: { expenses: { import: {
        preview: mocks.serverPreview, run: mocks.serverRun, example: vi.fn(),
    } } },
}));
import ExpenseImportModal from "@/components/invoicing/ExpenseImportModal.vue";

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((done) => { resolve = done; });
    return { promise, resolve };
}
const preview = (headers = ["Date", "Total"]) => ({
    headers, row_count: 1, suggested_mapping: { issue_date: 0, total: 1 },
});
let wrapper: VueWrapper | undefined;
const originalPlugins = config.global.plugins;
function render(localFirst = true) {
    config.global.plugins = [createI18n({ legacy: false, locale: "en", messages: { en } })];
    wrapper = mount(ExpenseImportModal, {
        props: { open: true, companyId: "company-a", localFirst },
        attachTo: document.body,
        global: {
            stubs: { RouterLink: { template: '<a href="#"><slot /></a>' } },
        },
    });
    return wrapper;
}
async function choose(file: File) {
    const input = wrapper!.find('input[type="file"]');
    Object.defineProperty(input.element, "files", { configurable: true, value: [file] });
    await input.trigger("change");
    await flushPromises();
}
function submit() {
    return wrapper!.findAll("button").find((button) => button.text() === "Import")!;
}

beforeEach(() => {
    vi.clearAllMocks();
    mocks.preview.mockResolvedValue(preview());
    mocks.run.mockResolvedValue({ imported: 1, skipped: 0, errors: [] });
    mocks.refresh.mockResolvedValue(undefined);
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    config.global.plugins = originalPlugins;
});

describe("expense import modal lifecycle", () => {
    it("ignores a stale preview after a second file is selected", async () => {
        const first = deferred<ReturnType<typeof preview>>();
        mocks.preview.mockReturnValueOnce(first.promise).mockResolvedValueOnce(preview(["New date", "New total"]));
        render();
        await choose(new File(["first"], "first.csv"));
        await wrapper!.find(".border-dashed").trigger("drop", {
            dataTransfer: { files: [new File(["second"], "second.csv")] },
        });
        first.resolve(preview(["Old date", "Old total"]));
        await flushPromises();
        expect(wrapper!.text()).toContain("New date");
        expect(wrapper!.text()).not.toContain("Old date");
        await submit().trigger("click");
        await flushPromises();
        expect(mocks.run.mock.calls[0][2].name).toBe("second.csv");
    });

    it("blocks overlapping submits, file changes and close while preserving the result", async () => {
        const pending = deferred<{ imported: number; skipped: number; errors: [] }>();
        mocks.run.mockReturnValueOnce(pending.promise);
        render();
        await choose(new File(["first"], "first.csv"));
        await submit().trigger("click");
        await flushPromises();
        await wrapper!.find('[role="dialog"]').trigger("keydown", { key: "Escape" });
        await wrapper!.find('button[aria-label="Close"]').trigger("click");
        await choose(new File(["second"], "second.csv"));
        expect(wrapper!.emitted("close")).toBeUndefined();
        expect(mocks.run).toHaveBeenCalledTimes(1);
        expect(mocks.preview).toHaveBeenCalledTimes(1);
        pending.resolve({ imported: 1, skipped: 0, errors: [] });
        await flushPromises();
        expect(wrapper!.emitted("imported")).toHaveLength(1);
        expect(wrapper!.find('[role="status"]').text()).toContain("Imported 1");
        expect(wrapper!.emitted("close")).toBeUndefined();
        expect(mocks.serverRun).not.toHaveBeenCalled();
    });

    it("discards a preview on close and reopen", async () => {
        const first = deferred<ReturnType<typeof preview>>();
        mocks.preview.mockReturnValueOnce(first.promise);
        render();
        await choose(new File(["first"], "first.csv"));
        await wrapper!.setProps({ open: false });
        await wrapper!.setProps({ open: true });
        first.resolve(preview(["Old date", "Old total"]));
        await flushPromises();
        expect(wrapper!.findAll("select")).toHaveLength(0);
        expect(submit().attributes("disabled")).toBeDefined();
    });

    it("fails closed when local mode is enabled without a local context", async () => {
        render(false);
        await wrapper!.setProps({ localFirst: true });
        await choose(new File(["first"], "first.csv"));
        await submit().trigger("click");
        await flushPromises();
        expect(mocks.run).not.toHaveBeenCalled();
        expect(mocks.serverRun).not.toHaveBeenCalled();
        expect(wrapper!.find('[role="alert"]').exists()).toBe(true);
    });

    it("does not write if company changes during the prerequisite refresh", async () => {
        const refresh = deferred<void>();
        mocks.refresh.mockReturnValueOnce(refresh.promise);
        render();
        await choose(new File(["first"], "first.csv"));
        await submit().trigger("click");
        await wrapper!.setProps({ companyId: "company-b" });
        refresh.resolve();
        await flushPromises();
        expect(mocks.run).not.toHaveBeenCalled();
        expect(mocks.serverRun).not.toHaveBeenCalled();
        expect(wrapper!.findAll("select")).toHaveLength(0);
    });
});
