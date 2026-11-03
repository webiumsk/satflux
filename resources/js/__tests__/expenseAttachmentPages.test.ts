import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { computed, reactive, ref } from "vue";
import { createI18n } from "vue-i18n";
import en from "@/locales/en.json";
import { evoluExpenseToApi, type EvoluExpenseRow } from "@/evolu/expenseMap";
import { toAppRows } from "@/evolu/queryLoad";
import type { EvoluExpenseAttachmentRow } from "@/evolu/expenseAttachmentCrud";

const mocks = vi.hoisted(() => ({
    local: true, evolu: {} as object | null, displayForeign: false,
    push: vi.fn(), remember: vi.fn(), refresh: vi.fn(),
    insertExpense: vi.fn(), updateExpense: vi.fn(), insertAttachment: vi.fn(), read: vi.fn(),
    detect: vi.fn(), extract: vi.fn(), quota: vi.fn(), purchase: vi.fn(), upload: vi.fn(),
    localDetect: vi.fn(), localExtract: vi.fn(), localQuota: vi.fn(),
    get: vi.fn(), update: vi.fn(), create: vi.fn(), history: vi.fn(), list: vi.fn(), company: vi.fn(), blob: vi.fn(),
}));
const localCompany = "abcdefghijklmnopqrstuv";
const serverCompany = "00000000-0000-4000-8000-000000000001";
const newExpenseId = "synthetic-created-expense";
const company = ref(localCompany);
const route = reactive({ name: "invoicing-expense-new", params: {} as { expenseId?: string } });
type Attachment = { id: string; original_filename: string; mime: string | null };
function fixture(id = "expense-a", owner = localCompany) {
    return {
        id, companyId: owner, internal_number: "E-2026-001", external_number: null as string | null,
        title: "Synthetic existing expense", status: "recorded", issue_date: "2026-01-01",
        delivery_date: "2026-01-01", due_date: null, paid_at: null,
        total: "1.00", currency: "EUR", variable_symbol: null, constant_symbol: null,
        specific_symbol: null, internal_note: null, attachment_path: null, original_filename: null,
        attachments: [] as Attachment[],
    };
}
const models = ref<ReturnType<typeof fixture>[]>([]);
const expenseRows = computed(() => models.value.map((expense) => ({
    id: expense.id, companyId: expense.companyId, internalNumber: expense.internal_number,
    externalNumber: expense.external_number, title: expense.title, status: expense.status,
    issueDate: expense.issue_date, deliveryDate: expense.delivery_date, dueDate: expense.due_date,
    paidAt: expense.paid_at, cancelledAt: null, total: expense.total, currency: expense.currency,
    variableSymbol: expense.variable_symbol, constantSymbol: expense.constant_symbol,
    specificSymbol: expense.specific_symbol, internalNote: expense.internal_note,
})));
const attachmentRows = computed(() => models.value.flatMap((expense) => expense.attachments.map((attachment) => ({
    id: attachment.id, expenseId: expense.id, originalFilename: attachment.original_filename,
    mimeType: attachment.mime, sizeBytes: "18", contentBase64: "c3ludGhldGlj",
}))));
const currentExpense = computed(() => {
    const row = expenseRows.value.find((expense) => expense.id === route.params.expenseId
        && (mocks.displayForeign || expense.companyId === company.value));
    return row ? evoluExpenseToApi(
        toAppRows<EvoluExpenseRow>([row])[0],
        toAppRows<EvoluExpenseAttachmentRow>(attachmentRows.value.filter((attachment) => attachment.expenseId === row.id)),
    ) : null;
});
vi.mock("vue-router", () => ({ useRoute: () => route, useRouter: () => ({ push: mocks.push }) }));
vi.mock("@/composables/useEffectiveAccess", () => ({ useEffectiveAccess: () => ({ purchases: ref({ isdoc_packs: true }) }) }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => mocks.local }));
vi.mock("@/evolu/client", () => ({ useInvoicingEvolu: () => mocks.evolu }));
vi.mock("@/composables/useInvoicingLayout", () => ({ useInvoicingLayout: () => ({ companyId: company, rememberCompany: mocks.remember }) }));
vi.mock("@/composables/useInvoicingCompanySummary", () => ({ useInvoicingCompanySummary: () => ({ defaultCurrency: ref("EUR") }) }));
vi.mock("@/composables/useInvoicingSaveFeedback", () => ({ useInvoicingSaveFeedback: () => ({ notifySaved: vi.fn() }) }));
vi.mock("@/composables/useInvoicingExpenses", () => ({ useInvoicingExpense: () => ({
    evolu: mocks.evolu, expense: currentExpense, expenseRows, attachmentRows, refresh: mocks.refresh,
}) }));
vi.mock("@/evolu/expenseCrud", () => ({
    insertLocalExpense: mocks.insertExpense, updateLocalExpense: mocks.updateExpense,
    cancelLocalExpense: vi.fn(), duplicateLocalExpense: vi.fn(), markLocalExpensePaid: vi.fn(), unmarkLocalExpensePaid: vi.fn(),
}));
vi.mock("@/evolu/expenseAttachmentCrud", async (importOriginal) => ({
    ...await importOriginal<typeof import("@/evolu/expenseAttachmentCrud")>(),
    LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES: 384 * 1024,
    readFileAsAttachmentBase64: mocks.read, insertLocalExpenseAttachmentFromBase64: mocks.insertAttachment,
    attachmentBlobUrl: vi.fn(), attachmentContentBlob: vi.fn(() => null), deleteLocalExpenseAttachment: vi.fn(),
}));
vi.mock("@/services/api", () => ({ getWebBlob: mocks.blob, invoicingApi: {
    companies: { get: mocks.company },
    expenses: {
        get: mocks.get, update: mocks.update, create: mocks.create, history: mocks.history, list: mocks.list,
        detectIsdoc: mocks.detect, extract: mocks.extract, isdocQuota: mocks.quota,
        purchaseIsdocPack: mocks.purchase, uploadAttachment: mocks.upload,
        ephemeral: { detectIsdoc: mocks.localDetect, extract: mocks.localExtract, isdocQuota: mocks.localQuota },
    },
} }));
vi.mock("@/components/invoicing/InvoicingPageShell.vue", () => ({ default: { template: '<main><slot name="header" /><slot name="toolbar" /><slot /></main>' } }));
vi.mock("@/components/invoicing/InvoicingAppHeader.vue", () => ({ default: { template: "<header />" } }));
vi.mock("@/components/invoicing/ExpenseShowSidebar.vue", () => ({ default: { template: "<aside />" } }));
vi.mock("@/components/invoicing/ExpenseAttachmentPanel.vue", () => ({ default: {
    name: "ExpenseAttachmentPanel",
    props: ["fileName", "hasPending", "detectError", "detecting"],
    emits: ["file-selected", "files-selected", "clear", "select-pending", "remove-pending"],
    template: '<section data-test="attachment-panel"><span>{{ fileName }}</span><p>{{ detectError }}</p></section>',
} }));
import ExpenseForm from "@/pages/invoicing/ExpenseForm.vue";
import ExpenseShow from "@/pages/invoicing/ExpenseShow.vue";
import ExpenseIsdocExtractModal from "@/components/invoicing/ExpenseIsdocExtractModal.vue";

type AttachmentInput = { filename: string; mimeType: string | null; contentBase64: string };
function appendAttachment(_evolu: object, id: string, input: AttachmentInput) {
    const expense = models.value.find((row) => row.id === id);
    if (!expense) return { ok: false, error: "synthetic missing expense" };
    const attachmentId = `attachment-${expense.attachments.length + 1}`;
    expense.attachments.push({ id: attachmentId, original_filename: input.filename, mime: input.mimeType });
    return { ok: true, value: { id: attachmentId } };
}
const quota = { unlimited: false, can_extract: true, free_remaining: 19, free_limit: 20, packs: [{ credits: 25, price_eur: 1 }] };
const draft = { title: "Synthetic extracted title", total: 7.5, issue_date: "2026-02-03", currency: "EUR" };
const pdf = (name = "synthetic.pdf") => new File(["%PDF synthetic only"], name, { type: "application/pdf" });
function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((done) => { resolve = done; });
    return { promise, resolve };
}
let wrapper: VueWrapper | undefined;
function render(kind: "new" | "show" = "new", local = true) {
    mocks.local = local;
    company.value = local ? localCompany : serverCompany;
    route.name = kind === "new" ? "invoicing-expense-new" : "invoicing-expense-show";
    route.params = kind === "new" ? {} : { expenseId: "expense-a" };
    wrapper = mount(kind === "new" ? ExpenseForm : ExpenseShow, { global: {
        plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })],
        stubs: { RouterLink: { template: '<a href="#"><slot /></a>' } },
    } });
    return wrapper;
}
async function select(file = pdf()) {
    wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("file-selected", file);
    await flushPromises();
}
function expectNoLegacyExpenseWrites() {
    for (const call of [mocks.get, mocks.update, mocks.create, mocks.upload, mocks.detect, mocks.extract, mocks.purchase]) expect(call).not.toHaveBeenCalled();
}
beforeEach(() => {
    vi.resetAllMocks();
    mocks.local = true;
    mocks.displayForeign = false;
    mocks.evolu = {};
    route.params = {};
    route.name = "invoicing-expense-new";
    company.value = localCompany;
    models.value = [fixture()];
    vi.spyOn(window, "confirm").mockReturnValue(false);
    vi.spyOn(window, "alert").mockImplementation(() => undefined);
    vi.stubGlobal("URL", class extends URL {
        static createObjectURL = vi.fn(() => "blob:synthetic-pdf");
        static revokeObjectURL = vi.fn();
    });
    mocks.push.mockResolvedValue(undefined);
    mocks.refresh.mockResolvedValue(undefined);
    mocks.read.mockResolvedValue("c3ludGhldGlj");
    mocks.insertExpense.mockImplementation((_evolu, owner: string, payload: Record<string, unknown>) => {
        models.value.push({ ...fixture(newExpenseId, owner), ...payload, total: String(payload.total ?? "0.00") });
        return { ok: true, value: { id: newExpenseId } };
    });
    mocks.updateExpense.mockImplementation((_evolu, id: string, payload: Record<string, unknown>) => {
        const expense = models.value.find((row) => row.id === id);
        if (!expense) return { ok: false, error: "synthetic missing expense" };
        Object.assign(expense, payload, { total: String(payload.total ?? expense.total) });
        return { ok: true, value: { id } };
    });
    mocks.insertAttachment.mockImplementation(appendAttachment);
    for (const call of [mocks.detect, mocks.localDetect]) call.mockResolvedValue({ has_isdoc: false, quota });
    for (const call of [mocks.extract, mocks.localExtract]) call.mockResolvedValue({ data: draft, quota });
    for (const call of [mocks.quota, mocks.localQuota]) call.mockResolvedValue(quota);
    mocks.get.mockImplementation(async () => models.value[0]);
    mocks.list.mockResolvedValue([{ id: "expense-a" }]);
    mocks.history.mockResolvedValue([]);
    mocks.company.mockResolvedValue({ default_currency: "EUR" });
    mocks.upload.mockResolvedValue(undefined);
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe("expense attachment page integration", () => {
    it("persists a new local expense's pending attachment before navigating", async () => {
        const encoding = deferred<string>();
        mocks.read.mockReturnValue(encoding.promise);
        render();
        await flushPromises();
        await select();
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.insertExpense).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.push).not.toHaveBeenCalled();
        encoding.resolve("c3ludGhldGlj");
        await flushPromises();
        expect(mocks.insertAttachment).toHaveBeenCalledWith(mocks.evolu, newExpenseId, expect.objectContaining({ filename: "synthetic.pdf" }));
        expect(mocks.push).toHaveBeenCalledWith({ name: "invoicing-expense-show", params: { companyId: localCompany, expenseId: newExpenseId } });
        expect(mocks.insertAttachment.mock.invocationCallOrder[0]).toBeLessThan(mocks.push.mock.invocationCallOrder[0]);
        expectNoLegacyExpenseWrites();
    });

    it("retries a partially failed local attachment save without duplicating the expense or completed file", async () => {
        mocks.insertAttachment.mockImplementationOnce(appendAttachment).mockReturnValueOnce({ ok: false, error: "synthetic insert rejection" });
        render();
        await flushPromises();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("files-selected", [pdf("first.pdf"), pdf("second.pdf")]);
        await flushPromises();
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.push).not.toHaveBeenCalled();
        expect(mocks.insertExpense).toHaveBeenCalledOnce();
        expect(models.value.find((row) => row.id === newExpenseId)?.attachments).toHaveLength(1);
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("fileName")).toBe("second.pdf");
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.insertExpense).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).toHaveBeenCalledTimes(3);
        expect(mocks.insertAttachment.mock.calls.map((call) => (call[2] as AttachmentInput).filename)).toEqual(["first.pdf", "second.pdf", "second.pdf"]);
        expect(models.value.find((row) => row.id === newExpenseId)?.attachments).toHaveLength(2);
        expect(mocks.push).toHaveBeenCalledOnce();
        expectNoLegacyExpenseWrites();
    });

    it("does not accept new selections while the current local attachment save is pending", async () => {
        const encoding = deferred<string>();
        mocks.read.mockReturnValue(encoding.promise);
        render();
        await flushPromises();
        await select(pdf("original.pdf"));
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        const panel = wrapper!.findComponent({ name: "ExpenseAttachmentPanel" });
        expect(panel.props("detecting")).toBe(true);
        expect(mocks.read).toHaveBeenCalledOnce();

        panel.vm.$emit("file-selected", pdf("late-single.pdf"));
        panel.vm.$emit("files-selected", [pdf("late-multiple.pdf")]);
        await flushPromises();
        expect(window.confirm).toHaveBeenCalledOnce();
        expect(panel.props("fileName")).toBe("original.pdf");
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expect(mocks.read).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.push).not.toHaveBeenCalled();

        encoding.resolve("c3ludGhldGlj");
        await flushPromises();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment.mock.calls[0][2]).toMatchObject({ filename: "original.pdf" });
        expect(mocks.push).toHaveBeenCalledOnce();
        expect(panel.props("detecting")).toBe(false);
        expectNoLegacyExpenseWrites();
    });

    it("fails a new local save closed when Evolu is missing", async () => {
        mocks.evolu = null;
        render();
        await flushPromises();
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.insertExpense).not.toHaveBeenCalled();
        expect(mocks.push).not.toHaveBeenCalled();
        expect(wrapper!.text()).toContain(en.errors.generic);
        expectNoLegacyExpenseWrites();
    });

    it("stores a declined PDF using the real local adapter without a company_id property", async () => {
        render("show");
        await flushPromises();
        expect(currentExpense.value).not.toHaveProperty("company_id");
        await select();
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).toHaveBeenCalledWith(mocks.evolu, "expense-a", expect.objectContaining({ filename: "synthetic.pdf" }));
        expect(mocks.refresh).toHaveBeenCalledTimes(2);
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("fileName")).toBe("synthetic.pdf");
        expectNoLegacyExpenseWrites();
    });

    it("does not attach a file to a displayed expense whose raw row belongs to another company", async () => {
        mocks.displayForeign = true;
        models.value = [fixture("expense-a", "different-local-company")];
        render("show");
        await flushPromises();
        expect(currentExpense.value).not.toHaveProperty("company_id");
        await select();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expectNoLegacyExpenseWrites();
    });

    it("applies confirmed extracted fields and the attachment only through Evolu", async () => {
        vi.mocked(window.confirm).mockReturnValue(true);
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        render("show");
        await flushPromises();
        await select();
        const modal = wrapper!.findComponent(ExpenseIsdocExtractModal);
        expect(modal.props("open")).toBe(true);
        expect(modal.props("allowPurchases")).toBe(false);
        expect(modal.text()).not.toContain(en.invoicing.expense_isdoc_packs_title);
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        modal.vm.$emit("confirm");
        await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledWith(mocks.evolu, "expense-a", expect.objectContaining({ title: draft.title, total: draft.total }), expect.objectContaining({ id: "expense-a", companyId: localCompany }));
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(wrapper!.text()).toContain(draft.title);
        expectNoLegacyExpenseWrites();
    });

    it("keeps the PDF as a local attachment when field extraction is skipped", async () => {
        vi.mocked(window.confirm).mockReturnValue(true);
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        render("show");
        await flushPromises();
        await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("skip");
        await flushPromises();
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expectNoLegacyExpenseWrites();
    });

    it("does not apply a late extraction to another expense", async () => {
        vi.mocked(window.confirm).mockReturnValue(true);
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        const extraction = deferred<{ data: typeof draft; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(extraction.promise);
        render("show");
        await flushPromises();
        await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm");
        await flushPromises();
        models.value.push(fixture("expense-b"));
        route.params.expenseId = "expense-b";
        await flushPromises();
        extraction.resolve({ data: draft, quota });
        await flushPromises();
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(window.alert).not.toHaveBeenCalled();
        expectNoLegacyExpenseWrites();
    });

    it("does not insert a late encoded attachment after switching company", async () => {
        const encoding = deferred<string>();
        mocks.read.mockReturnValue(encoding.promise);
        render("show");
        await flushPromises();
        await select();
        company.value = "different-local-company";
        await flushPromises();
        encoding.resolve("c3ludGhldGlj");
        await flushPromises();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expectNoLegacyExpenseWrites();
    });

    it("preserves the legacy server attachment upload and refresh path", async () => {
        models.value = [fixture("expense-a", serverCompany)];
        render("show", false);
        await flushPromises();
        await select();
        expect(window.confirm).not.toHaveBeenCalled();
        expect(mocks.detect.mock.calls[0][0]).toBe(serverCompany);
        expect(mocks.upload).toHaveBeenCalledWith(serverCompany, "expense-a", expect.any(FormData));
        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.localDetect).not.toHaveBeenCalled();
    });

    it("does not upload a new company's pending file after an old server create finishes", async () => {
        const creation = deferred<{ id: string }>();
        mocks.create.mockReturnValue(creation.promise);
        render("new", false);
        await flushPromises();
        await select(pdf("old-company.pdf"));
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.create).toHaveBeenCalledWith(serverCompany, expect.any(Object));
        company.value = "00000000-0000-4000-8000-000000000002";
        await flushPromises();
        await select(pdf("new-company.pdf"));
        creation.resolve({ id: "old-server-expense" });
        await flushPromises();
        expect(mocks.upload).not.toHaveBeenCalled();
        expect(mocks.push).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("fileName")).toBe("new-company.pdf");
    });
});
