import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { config, flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { reactive, ref } from "vue";
import { createIdFromString } from "@evolu/common";
import { CompanyId, ExpenseId } from "@/evolu/schema";
import { createI18n } from "vue-i18n";
import en from "@/locales/en.json";
import type { ExpenseListRow } from "@/composables/useExpenseRowMeta";
import { summarizeExpenses, type ExpenseSummary } from "@/utils/expenseSummary";
import { filterLocalExpenses, type EvoluExpenseRow, type ExpenseListFilterOptions } from "@/evolu/expenseMap";

const mocks = vi.hoisted(() => ({
    localFirst: false,
    route: {} as { params: { companyId: string }; query: { year?: string } },
    listPaged: vi.fn(),
    action: vi.fn(),
    delete: vi.fn(),
    refresh: vi.fn(),
    rememberCompany: vi.fn(),
    push: vi.fn(),
    duplicateLocal: vi.fn(),
    bulk: vi.fn(),
    bulkExport: vi.fn(),
    resolveTargets: vi.fn(),
    markPaidBulk: vi.fn(),
    openFilterDrawer: vi.fn(),
}));
const localExpenses = ref<ExpenseListRow[]>([]);
const localExpenseRows = ref<{ id: string }[]>([]);
let localSource: ExpenseListRow[] = [];

vi.mock("vue-router", () => ({ useRoute: () => mocks.route, useRouter: () => ({ push: mocks.push }) }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => mocks.localFirst }));
vi.mock("@/composables/useInvoicingExpenses", () => ({
    useInvoicingExpenses: () => ({ expenses: localExpenses, refresh: mocks.refresh, expenseRows: localExpenseRows, evolu: {} }),
}));
vi.mock("@/composables/useInvoicingCompanySummary", () => ({
    useInvoicingCompanySummary: () => ({ companyName: ref("Synthetic company") }),
}));
vi.mock("@/composables/useInvoicingLayout", () => ({
    INVOICING_CONTAINER_CLASS: "container",
    useInvoicingLayout: () => ({ rememberCompany: mocks.rememberCompany }),
}));
vi.mock("@/evolu/expenseCrud", () => ({ markLocalExpensePaid: vi.fn(), duplicateLocalExpense: mocks.duplicateLocal, cancelLocalExpense: vi.fn() }));
vi.mock("@/evolu/expenseBulkLocal", () => ({ bulkCancelLocalExpenses: vi.fn(), bulkMarkPaidLocalExpenses: mocks.markPaidBulk, resolveBulkExpenseTargets: mocks.resolveTargets }));
vi.mock("@/services/api", () => ({ invoicingApi: { expenses: { listPaged: mocks.listPaged, action: mocks.action, delete: mocks.delete, bulk: mocks.bulk, bulkExport: mocks.bulkExport } } }));
vi.mock("@/components/invoicing/InvoicingPageShell.vue", () => ({ default: { template: '<main><slot name="header" /><slot name="subheader" /><slot /></main>' } }));
vi.mock("@/components/invoicing/InvoicingAppHeader.vue", () => ({ default: { name: "InvoicingAppHeader", setup: () => ({ openFilterDrawer: mocks.openFilterDrawer }), emits: ["mobile-filter-apply", "mobile-filter-clear"], template: '<header><slot name="mobile-filters" /></header>' } }));
vi.mock("@/components/invoicing/ExpensesTable.vue", () => ({ default: {
    name: "ExpensesTable", props: ["rows", "totalCount", "selectionCount"], emits: ["mark-paid", "select-page", "select-all", "run-bulk", "duplicate"],
    template: '<div data-test="expenses-table"><p v-for="row in rows" :key="row.id">{{ row.title }}</p><span data-test="selection">{{ selectionCount }}</span></div>',
} }));
vi.mock("@/components/invoicing/ExpenseImportModal.vue", () => ({ default: { name: "ExpenseImportModal", emits: ["imported"], template: "<div />" } }));
vi.mock("@/components/invoicing/ExpenseAttachmentImportModal.vue", () => ({ default: { name: "ExpenseAttachmentImportModal", emits: ["imported"], template: "<div />" } }));
vi.mock("@/components/invoicing/EfakturaInboxPanel.vue", () => ({ default: { template: "<div />" } }));
vi.mock("@/components/invoicing/InvoicingMobileBulkBar.vue", () => ({ default: { template: "<div />" } }));
vi.mock("@/components/invoicing/LocalFirstBridgeNotice.vue", () => ({ default: { template: "<div />" } }));
import Expenses from "@/pages/invoicing/Expenses.vue";

type Response = { data: ExpenseListRow[]; total: number; last_page: number; current_page: number; summary?: ExpenseSummary };
function row(id: string, total = "1.00", currency = "EUR", status = "recorded", year = new Date().getFullYear()): ExpenseListRow {
    return { id, title: id, internal_number: id, external_number: null, issue_date: `${year}-01-01`, total, currency, status };
}
function response(rows: ExpenseListRow[], summary = summarizeExpenses(rows), page = 1): Response {
    return { data: rows, total: summary.count, current_page: page, last_page: Math.max(1, Math.ceil(summary.count / 25)), summary };
}
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: Error) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
let wrapper: VueWrapper | undefined;
const originalPlugins = config.global.plugins;
function render(localFirst = false) {
    mocks.localFirst = localFirst;
    config.global.plugins = [createI18n({ legacy: false, locale: "en", messages: { en } })];
    wrapper = mount(Expenses, { global: { stubs: { RouterLink: { template: '<a href="#"><slot /></a>' } } } });
    return wrapper;
}
function summaryText() {
    return wrapper!.find('section[aria-labelledby="expense-list-summary-heading"]').text();
}
async function filter(label: string) {
    const button = wrapper!.findAll("button.invoicing-filter").find((node) => node.text() === label)!;
    await button.trigger("click");
    await flushPromises();
}

beforeEach(() => {
    vi.clearAllMocks();
    mocks.route = reactive({ params: { companyId: "company-a" }, query: { year: "all" } });
    mocks.listPaged.mockResolvedValue(response([row("Initial expense")]));
    mocks.action.mockResolvedValue({});
    mocks.delete.mockResolvedValue(undefined);
    localSource = [];
    localExpenses.value = [];
    localExpenseRows.value = [];
    mocks.bulk.mockResolvedValue({ processed: 1, skipped: 0 });
    mocks.markPaidBulk.mockResolvedValue({ processed: 1, skipped: 0 });
    mocks.resolveTargets.mockReturnValue([]);
    mocks.refresh.mockImplementation(async (filters: ExpenseListFilterOptions = {}) => {
        const raw: EvoluExpenseRow[] = localSource.map((item) => ({
            id: ExpenseId.orThrow(createIdFromString(item.id)), companyId: CompanyId.orThrow(createIdFromString("company-a")),
            internalNumber: item.internal_number, externalNumber: item.external_number ?? null,
            title: item.title ?? null, variableSymbol: item.variable_symbol ?? null, status: item.status,
            issueDate: item.issue_date || null, dueDate: item.due_date ?? null, total: item.total ?? null,
            currency: item.currency ?? null, internalNote: item.internal_note ?? null,
            constantSymbol: null, specificSymbol: null, deliveryDate: null, paidAt: item.paid_at ?? null, cancelledAt: null,
        }));
        const ids = new Set(filterLocalExpenses(raw, filters).map((item) => item.id));
        localExpenses.value = localSource.filter((item) => ids.has(ExpenseId.orThrow(createIdFromString(item.id))));
    });
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    config.global.plugins = originalPlugins;
    vi.unstubAllGlobals();
});

describe("expense page filtered summary", () => {
    it("uses the full server summary without summing its current page", async () => {
        const full = Array.from({ length: 26 }, (_, index) => row(`Expense ${index}`, "2.00"));
        mocks.listPaged.mockResolvedValue(response(full.slice(0, 25), summarizeExpenses(full)));
        render();
        await flushPromises();
        expect(wrapper!.findAll('[data-test="expenses-table"] p')).toHaveLength(25);
        expect(summaryText()).toContain("Expenses: 26");
        expect(summaryText()).toContain("€52.00");
        expect(wrapper!.text()).toContain("1 / 2");
    });

    it("summarizes the full local collection including an off-page currency", async () => {
        localSource = Array.from({ length: 26 }, (_, index) => row(`Local ${index}`, "0.10"));
        localSource.push(row("Off-page CZK", "250.25", "CZK", "paid"));
        render(true);
        await flushPromises();
        expect(wrapper!.findAll('[data-test="expenses-table"] p')).toHaveLength(25);
        expect(summaryText()).toContain("Expenses: 27");
        expect(summaryText()).toContain("CZK");
        expect(summaryText()).toContain("250.25");
        expect(summaryText()).toContain("€2.60");
        expect(mocks.listPaged).not.toHaveBeenCalled();
        const next = wrapper!.findAll("button").find((node) => node.text() === "›")!;
        await next.trigger("click");
        await flushPromises();
        expect(wrapper!.findAll('[data-test="expenses-table"] p')).toHaveLength(2);
        expect(summaryText()).toContain("Expenses: 27");
        expect(wrapper!.text()).toContain("2 / 2");
    });

    it("refreshes local totals for status and issue-period filters", async () => {
        const current = new Date().getFullYear();
        localSource = [row("Paid current", "2.00", "EUR", "paid", current), row("Unpaid old", "3.00", "EUR", "recorded", current - 1)];
        render(true);
        await flushPromises();
        expect(summaryText()).toContain("Expenses: 2");
        await filter(en.invoicing.adv_paid);
        expect(summaryText()).toContain("Expenses: 1");
        expect(wrapper!.findAll("dd").map((node) => node.text())).toEqual(["€2.00", "€0.00"]);
        await filter(en.invoicing.adv_all);
        wrapper!.findAllComponents({ name: "InvoicingRecordFilterBar" })[1].vm.$emit("period-change", {
            preset: "last_year", customFrom: "", customTo: "",
        });
        await flushPromises();
        expect(summaryText()).toContain("Expenses: 1");
        expect(summaryText()).toContain("€3.00");
    });

    it.each([
        { control: "mobile", selectorIndex: 0 },
        { control: "desktop", selectorIndex: 1 },
    ])("resets server pagination and selection when the $control period changes", async ({ selectorIndex }) => {
        const targetYear = new Date().getFullYear() - 1;
        const full = Array.from({ length: 26 }, (_, index) => row(`Expense ${index}`, "2.00"));
        const smallerYear = [row("Smaller year expense", "7.50", "EUR", "recorded", targetYear)];
        mocks.listPaged.mockImplementation(async (_company: string, options: { page: number; issue_from?: string }) => {
            const filtered = options.issue_from === `${targetYear}-01-01` ? smallerYear : full;
            const start = (options.page - 1) * 25;
            return response(filtered.slice(start, start + 25), summarizeExpenses(filtered), options.page);
        });
        render();
        await flushPromises();
        const next = wrapper!.findAll("button").find((node) => node.text() === "›")!;
        await next.trigger("click");
        await flushPromises();
        expect(wrapper!.text()).toContain("2 / 2");
        expect(wrapper!.findAll('[data-test="expenses-table"] p')).toHaveLength(1);
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("select-page");
        await flushPromises();
        expect(wrapper!.find('[data-test="selection"]').text()).toBe("1");

        const bars = wrapper!.findAllComponents({ name: "InvoicingRecordFilterBar" });
        expect(bars).toHaveLength(2);
        bars[selectorIndex].vm.$emit("period-change", { preset: "last_year", customFrom: "", customTo: "" });
        await flushPromises();

        expect(mocks.listPaged).toHaveBeenLastCalledWith("company-a", expect.objectContaining({ issue_from: `${targetYear}-01-01`, issue_to: `${targetYear}-12-31`, page: 1 }));
        expect(wrapper!.find('[data-test="expenses-table"]').text()).toContain("Smaller year expense");
        expect(wrapper!.find('[data-test="selection"]').text()).toBe("0");
        expect(summaryText()).toContain("Expenses: 1");
        expect(summaryText()).toContain("€7.50");
    });

    it("hides totals on an older server response instead of presenting page totals", async () => {
        mocks.listPaged.mockResolvedValue({ data: [row("Legacy")], total: 100, current_page: 1, last_page: 4 });
        render();
        await flushPromises();
        expect(wrapper!.text()).toContain("Legacy");
        expect(wrapper!.find("section").exists()).toBe(false);
    });

    it("clears the previous footer immediately while the next filter loads", async () => {
        render();
        await flushPromises();
        expect(summaryText()).toContain("€1.00");
        const pending = deferred<Response>();
        mocks.listPaged.mockReturnValueOnce(pending.promise);
        await filter(en.invoicing.adv_paid);
        expect(wrapper!.find("section").exists()).toBe(false);
        expect(wrapper!.find('[data-test="expenses-table"]').exists()).toBe(false);
        pending.resolve(response([row("Paid result", "3.00", "EUR", "paid")]));
        await flushPromises();
        expect(summaryText()).toContain("€3.00");
    });

    it.each([false, true])("ignores a stale request after a newer result (old rejection: %s)", async (rejectOld) => {
        const older = deferred<Response>();
        const newer = deferred<Response>();
        mocks.listPaged.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        render();
        await filter(en.invoicing.adv_paid);
        newer.resolve(response([row("Newest expense", "9.00", "EUR", "paid")]));
        await flushPromises();
        if (rejectOld) older.reject(new Error("Old company detail"));
        else older.resolve(response([row("Stale expense", "700.00")]));
        await flushPromises();
        expect(summaryText()).toContain("€9.00");
        expect(wrapper!.text()).not.toContain("Stale expense");
        expect(wrapper!.find('[role="alert"]').exists()).toBe(false);
    });

    it("clears stale content on failure and retries with localized feedback", async () => {
        render();
        await flushPromises();
        mocks.listPaged.mockRejectedValueOnce(new Error("Private transport detail"));
        await filter(en.invoicing.expense_filter_unpaid);
        expect(wrapper!.find("section").exists()).toBe(false);
        expect(wrapper!.find('[data-test="expenses-table"]').exists()).toBe(false);
        expect(wrapper!.find('[role="alert"]').text()).toContain(en.invoicing.expenses_load_error);
        expect(wrapper!.text()).not.toContain("Private transport detail");
        mocks.listPaged.mockResolvedValueOnce(response([row("Recovered", "4.00")]));
        await wrapper!.find('[role="alert"] button').trigger("click");
        await flushPromises();
        expect(summaryText()).toContain("€4.00");
        expect(wrapper!.find('[role="alert"]').exists()).toBe(false);
    });

    it.each([false, true])("never restores a prior company after switching (old rejection: %s)", async (rejectOld) => {
        const older = deferred<Response>();
        const newer = deferred<Response>();
        mocks.listPaged.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        render();
        mocks.route.params.companyId = "company-b";
        await flushPromises();
        expect(mocks.listPaged.mock.calls.map((call) => call[0])).toEqual(["company-a", "company-b"]);
        newer.resolve(response([row("Company B expense", "2.00")]));
        await flushPromises();
        if (rejectOld) older.reject(new Error("Company A private error"));
        else older.resolve(response([row("Company A expense", "100.00")]));
        await flushPromises();
        expect(wrapper!.text()).toContain("Company B expense");
        expect(summaryText()).toContain("€2.00");
        expect(wrapper!.text()).not.toContain("Company A");
    });

    it("resets selected rows and old totals immediately on company changes", async () => {
        render();
        await flushPromises();
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("select-page");
        await flushPromises();
        expect(wrapper!.find('[data-test="selection"]').text()).toBe("1");
        const pending = deferred<Response>();
        mocks.listPaged.mockReturnValueOnce(pending.promise);
        mocks.route.params.companyId = "company-b";
        await flushPromises();
        expect(wrapper!.find("section").exists()).toBe(false);
        pending.resolve(response([row("Company B", "2.00")]));
        await flushPromises();
        expect(wrapper!.find('[data-test="selection"]').text()).toBe("0");
    });

    it("refreshes the summary after a payment action and an import", async () => {
        render();
        await flushPromises();
        mocks.listPaged.mockResolvedValueOnce(response([row("Initial expense", "1.00", "EUR", "paid")]));
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("mark-paid", "Initial expense");
        await flushPromises();
        expect(mocks.action).toHaveBeenCalledWith("company-a", "Initial expense", "mark-paid");
        expect(wrapper!.findAll("dd").map((node) => node.text())).toEqual(["€1.00", "€0.00"]);
        mocks.listPaged.mockResolvedValueOnce(response([row("Initial expense", "1.00", "EUR", "paid"), row("Imported", "4.00")]));
        wrapper!.findComponent({ name: "ExpenseImportModal" }).vm.$emit("imported");
        await flushPromises();
        expect(summaryText()).toContain("Expenses: 2");
        expect(summaryText()).toContain("€5.00");
        expect(summaryText()).toContain("€4.00");
    });

    it("ignores an old company's failed payment action after switching", async () => {
        render();
        await flushPromises();
        const pending = deferred<unknown>();
        mocks.action.mockReturnValueOnce(pending.promise);
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("mark-paid", "Initial expense");
        await flushPromises();
        mocks.listPaged.mockResolvedValueOnce(response([row("Company B expense", "2.00")]));
        mocks.route.params.companyId = "company-b";
        await flushPromises();
        pending.reject(Object.assign(new Error("private"), { response: { data: { message: "Company A private failure" } } }));
        await flushPromises();
        expect(summaryText()).toContain("€2.00");
        expect(wrapper!.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper!.text()).not.toContain("Company A");
    });

    it.each([true, false])("opens the copied expense with its exact duplicate marker from the list (local=%s)", async (local) => {
        localSource = [row("Initial expense")];
        localExpenseRows.value = [{ id: "Initial expense" }];
        if (local) mocks.duplicateLocal.mockResolvedValueOnce({ ok: true, value: { id: "copied-expense" } });
        else mocks.action.mockResolvedValueOnce({ id: "copied-expense" });
        render(local);
        await flushPromises();
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("duplicate", "Initial expense");
        await flushPromises();
        expect(mocks.push).toHaveBeenCalledExactlyOnceWith({ name: "invoicing-expense-edit",
            params: { companyId: "company-a", expenseId: "copied-expense" }, query: { duplicate: "copied-expense" } });
        expect(local ? mocks.duplicateLocal : mocks.action).toHaveBeenCalledOnce();
        expect(local ? mocks.action : mocks.duplicateLocal).not.toHaveBeenCalled();
    });

    it("does not navigate to a duplicated expense from a previous company", async () => {
        render();
        await flushPromises();
        const pending = deferred<{ id: string }>();
        mocks.action.mockReturnValueOnce(pending.promise);
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("duplicate", "Initial expense");
        await flushPromises();
        mocks.route.params.companyId = "company-b";
        await flushPromises();
        pending.resolve({ id: "Company A duplicate" });
        await flushPromises();
        expect(mocks.push).not.toHaveBeenCalled();
    });

    it("does not retain a footer after a filter returns no expenses", async () => {
        render();
        await flushPromises();
        mocks.listPaged.mockResolvedValueOnce(response([]));
        await filter(en.invoicing.adv_paid);
        expect(wrapper!.find("section").exists()).toBe(false);
        expect(wrapper!.text()).toContain(en.invoicing.filter_no_results);
    });

    it("applies mobile search and period together, while Clear removes applied and unsubmitted drafts", async () => {
        render();
        await flushPromises();
        const mobile = wrapper!.findAllComponents({ name: "InvoicingRecordFilterBar" })[0];
        const header = wrapper!.findComponent({ name: "InvoicingAppHeader" });
        await mobile.find('input[type="search"]').setValue("Supplier note 000123");
        await mobile.find('button[aria-controls$="-period"]').trigger("click");
        const lastYear = mobile.findAll("button").find(button => button.text() === en.invoicing.period_last_year)!;
        await lastYear.trigger("click");
        expect(mocks.listPaged).toHaveBeenCalledOnce();
        header.vm.$emit("mobile-filter-apply");
        await flushPromises();
        const previous = new Date().getFullYear() - 1;
        expect(mocks.listPaged).toHaveBeenLastCalledWith("company-a", expect.objectContaining({
            issue_from: `${previous}-01-01`, issue_to: `${previous}-12-31`, search: "Supplier note 000123", page: 1,
        }));
        await mobile.find('input[type="search"]').setValue("unsubmitted draft");
        header.vm.$emit("mobile-filter-clear");
        await flushPromises();
        expect(mobile.find('input[type="search"]').element).toHaveProperty("value", "");
        expect(mocks.listPaged).toHaveBeenLastCalledWith("company-a", expect.objectContaining({
            search: undefined, issue_from: undefined, issue_to: undefined, filter: undefined, page: 1,
        }));
        header.vm.$emit("mobile-filter-apply");
        await flushPromises();
        expect(mocks.listPaged.mock.calls.at(-1)![1].search).toBeUndefined();
        expect(mocks.route.query).toEqual({ year: "all" });
        expect(mocks.push).not.toHaveBeenCalled();
    });

    it("reopens the mobile drawer for an invalid range without changing the applied query", async () => {
        render();
        await flushPromises();
        const mobile = wrapper!.findAllComponents({ name: "InvoicingRecordFilterBar" })[0];
        await mobile.find('input[id$="-amount-min"]').setValue("20");
        await mobile.find('input[id$="-amount-max"]').setValue("10");
        wrapper!.findComponent({ name: "InvoicingAppHeader" }).vm.$emit("mobile-filter-apply");
        await flushPromises();
        expect(mocks.openFilterDrawer).toHaveBeenCalledOnce();
        expect(mobile.find('[role="alert"]').text()).toContain(en.invoicing.filter_invalid_range);
        expect(mocks.listPaged).toHaveBeenCalledOnce();
        expect(summaryText()).toContain("€1.00");
    });

    it("uses applied search and amount in select-all bulk payload, ignoring unsubmitted draft edits", async () => {
        render();
        await flushPromises();
        const desktop = wrapper!.findAllComponents({ name: "InvoicingRecordFilterBar" })[1];
        await desktop.find('button[aria-controls$="-advanced"]').trigger("click");
        await desktop.find('input[type="search"]').setValue("invoice 00042");
        await desktop.find('input[id$="-amount-min"]').setValue("12.50");
        await desktop.find("form").trigger("submit");
        await flushPromises();
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("select-all");
        await flushPromises();
        await desktop.find('button[aria-controls$="-advanced"]').trigger("click");
        await desktop.find('input[type="search"]').setValue("different draft");
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("run-bulk", "mark_paid");
        await flushPromises();
        expect(mocks.bulk).toHaveBeenCalledExactlyOnceWith("company-a", expect.objectContaining({
            action: "mark_paid", select_all: true, search: "invoice 00042", amount_min: "12.50",
        }));
    });

    it("ignores an old search response when a new search keeps the same status and period", async () => {
        render();
        await flushPromises();
        const older = deferred<Response>();
        const newer = deferred<Response>();
        mocks.listPaged.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        const desktop = wrapper!.findAllComponents({ name: "InvoicingRecordFilterBar" })[1];
        for (const term of ["old term", "new term"]) {
            await desktop.find('button[aria-controls$="-advanced"]').trigger("click");
            await desktop.find('input[type="search"]').setValue(term);
            await desktop.find("form").trigger("submit");
            await flushPromises();
        }
        newer.resolve(response([row("Current search", "2.00")]));
        await flushPromises();
        older.resolve(response([row("Old search", "999.00")]));
        await flushPromises();
        expect(wrapper!.text()).toContain("Current search");
        expect(wrapper!.text()).not.toContain("Old search");
        expect(summaryText()).toContain("€2.00");
    });

    it.each(["filters", "company", "unmount"])("does not deliver a pending export after %s changes", async change => {
        const pending = deferred<Blob>();
        const objectUrl = vi.fn(() => "blob:synthetic-export");
        const NativeUrl = URL;
        vi.stubGlobal("URL", class extends NativeUrl { static createObjectURL = objectUrl; static revokeObjectURL = vi.fn(); });
        mocks.bulkExport.mockReturnValueOnce(pending.promise);
        render();
        await flushPromises();
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("select-all");
        await flushPromises();
        wrapper!.findComponent({ name: "ExpensesTable" }).vm.$emit("run-bulk", "export_xlsx");
        await flushPromises();
        if (change === "filters") await filter(en.invoicing.adv_paid);
        else if (change === "company") { mocks.route.params.companyId = "company-b"; await flushPromises(); }
        else { wrapper!.unmount(); wrapper = undefined; }
        pending.resolve(new Blob(["synthetic export"]));
        await flushPromises();
        expect(objectUrl).not.toHaveBeenCalled();
    });
});
