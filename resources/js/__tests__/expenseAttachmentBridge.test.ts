import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount, type VueWrapper } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";
import { createI18n } from "vue-i18n";
import en from "@/locales/en.json";

const mocks = vi.hoisted(() => ({
    local: true,
    evolu: {} as object | null,
    detect: vi.fn(), extract: vi.fn(), quota: vi.fn(), purchase: vi.fn(), upload: vi.fn(),
    localDetect: vi.fn(), localExtract: vi.fn(), localQuota: vi.fn(),
    read: vi.fn(), insert: vi.fn(),
}));
vi.mock("@/composables/useEffectiveAccess", () => ({ useEffectiveAccess: () => ({ purchases: ref({ isdoc_packs: true }) }) }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => mocks.local }));
vi.mock("@/evolu/client", () => ({ useInvoicingEvolu: () => mocks.evolu }));
vi.mock("@/evolu/expenseAttachmentCrud", () => ({
    LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES: 384 * 1024,
    readFileAsAttachmentBase64: mocks.read,
    insertLocalExpenseAttachmentFromBase64: mocks.insert,
}));
vi.mock("@/services/api", () => ({ invoicingApi: { expenses: {
    detectIsdoc: mocks.detect, extract: mocks.extract, isdocQuota: mocks.quota,
    purchaseIsdocPack: mocks.purchase, uploadAttachment: mocks.upload,
    ephemeral: { detectIsdoc: mocks.localDetect, extract: mocks.localExtract, isdocQuota: mocks.localQuota },
} } }));
import { useExpenseIsdocAttachment } from "@/composables/useExpenseIsdocAttachment";

const localId = "abcdefghijklmnopqrstuv";
const serverId = "00000000-0000-4000-8000-000000000001";
const quota = { unlimited: false, can_extract: true, free_remaining: 19 };
const draft = { title: "Synthetic expense", total: 7.5, currency: "EUR" };
const pdf = (size = 28) => new File([new Uint8Array(size)], "synthetic.pdf", { type: "application/pdf" });
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (error: unknown) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
let wrapper: VueWrapper | undefined;
let resource: ReturnType<typeof useExpenseIsdocAttachment>;
let company = ref(localId);
let expense = ref<string | undefined>("expense-a");
let confirm: ReturnType<typeof vi.spyOn>;
function render(local = true) {
    mocks.local = local;
    company = ref(local ? localId : serverId);
    expense = ref("expense-a");
    wrapper = mount(defineComponent({ setup() {
        resource = useExpenseIsdocAttachment(company, expense);
        return () => h("main");
    } }), { global: { plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })] } });
}
function expectNoCompanyApi() {
    for (const call of [mocks.detect, mocks.extract, mocks.quota, mocks.purchase, mocks.upload]) expect(call).not.toHaveBeenCalled();
}
beforeEach(() => {
    vi.resetAllMocks();
    mocks.evolu = {};
    confirm = vi.spyOn(window, "confirm").mockReturnValue(true);
    vi.spyOn(window, "open").mockImplementation(() => null);
    vi.stubGlobal("URL", class extends URL {
        static createObjectURL = vi.fn(() => "blob:synthetic-pdf");
        static revokeObjectURL = vi.fn();
    });
    for (const call of [mocks.detect, mocks.localDetect]) call.mockResolvedValue({ has_isdoc: true, quota });
    for (const call of [mocks.extract, mocks.localExtract]) call.mockResolvedValue({ data: draft, quota });
    for (const call of [mocks.quota, mocks.localQuota]) call.mockResolvedValue(quota);
    mocks.purchase.mockResolvedValue({ checkoutLink: "https://checkout.invalid/synthetic" });
    mocks.upload.mockResolvedValue(undefined);
    mocks.read.mockResolvedValue("c3ludGhldGlj");
    mocks.insert.mockReturnValue({ ok: true, value: { id: "attachment-a" } });
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe("expense attachment bridge", () => {
    it("uses company-free quota, detection and explicitly confirmed extraction for a local company", async () => {
        render();
        await resource.loadQuota();
        const file = pdf();
        await resource.onDocumentSelected(file);
        expect(confirm).toHaveBeenCalledOnce();
        expect(mocks.localQuota).toHaveBeenCalledWith();
        expect(mocks.localDetect).toHaveBeenCalledOnce();
        expect(confirm.mock.invocationCallOrder[0]).toBeLessThan(mocks.localDetect.mock.invocationCallOrder[0]);
        expect(mocks.localDetect.mock.calls[0]).toHaveLength(1);
        expect((mocks.localDetect.mock.calls[0][0] as FormData).get("file")).toBe(file);
        expect(resource.showExtractModal.value).toBe(true);
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(await resource.confirmExtract()).toEqual(draft);
        expect(mocks.localExtract.mock.calls[0]).toHaveLength(1);
        expect(resource.quota.value).toEqual(quota);
        expectNoCompanyApi();
    });

    it("keeps a declined file available for encrypted local persistence without transmitting it", async () => {
        confirm.mockReturnValue(false);
        render();
        await resource.onDocumentSelected(pdf());
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(resource.showExtractModal.value).toBe(false);
        expect(await resource.confirmExtract()).toBeNull();
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expect(mocks.localExtract).not.toHaveBeenCalled();
        await resource.uploadAllPendingAttachments("expense-a");
        expect(mocks.insert).toHaveBeenCalledWith(mocks.evolu, "expense-a", {
            filename: "synthetic.pdf", mimeType: "application/pdf", contentBase64: "c3ludGhldGlj",
        });
        expect(resource.pendingFiles.value).toHaveLength(0);
        expectNoCompanyApi();
    });

    it("skips extraction when no embedded ISDOC is detected", async () => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: false, quota });
        render();
        await resource.onDocumentSelected(pdf());
        expect(resource.showExtractModal.value).toBe(false);
        expect(await resource.confirmExtract()).toBeNull();
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(resource.pendingFiles.value).toHaveLength(1);
    });

    it("preserves owned server-company routing and purchases without the local processing prompt", async () => {
        render(false);
        await resource.loadQuota();
        await resource.onDocumentSelected(pdf());
        await resource.confirmExtract();
        await resource.purchasePack(25);
        await resource.uploadAllPendingAttachments("server-expense");
        expect(confirm).not.toHaveBeenCalled();
        for (const call of [mocks.quota, mocks.detect, mocks.extract, mocks.purchase, mocks.upload]) expect(call.mock.calls[0][0]).toBe(serverId);
        expect(mocks.upload.mock.calls[0][1]).toBe("server-expense");
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(mocks.insert).not.toHaveBeenCalled();
    });

    it("does not send a local company ID through unsupported pack purchases", async () => {
        render();
        await resource.loadQuota();
        await resource.purchasePack(25);
        expect(resource.quota.value).toEqual(quota);
        expect(mocks.purchase).not.toHaveBeenCalled();
    });

    it("rejects an oversized local attachment before consent or upload and accepts the existing cap", async () => {
        render();
        await resource.onDocumentSelected(pdf(384 * 1024 + 1));
        expect(resource.detectError.value).toBe(en.invoicing.expense_attachment_too_large);
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(confirm).not.toHaveBeenCalled();
        expect(mocks.localDetect).not.toHaveBeenCalled();
        await resource.onDocumentSelected(pdf(384 * 1024));
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(mocks.localDetect).toHaveBeenCalledOnce();
    });

    it("fails closed when the local context is unavailable", async () => {
        mocks.evolu = null;
        render();
        await resource.onDocumentSelected(pdf());
        expect(resource.detectError.value).toBe(en.errors.generic);
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(confirm).not.toHaveBeenCalled();
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expectNoCompanyApi();
    });

    it.each(["company", "expense", "removed", "unmounted"])("ignores detection after its %s context changes", async (change) => {
        const pending = deferred<{ has_isdoc: boolean; quota: typeof quota }>();
        mocks.localDetect.mockReturnValue(pending.promise);
        render();
        const loading = resource.onDocumentSelected(pdf());
        if (change === "company") company.value = "different-local-company";
        if (change === "expense") expense.value = "expense-b";
        if (change === "removed") resource.removePendingFile(resource.pendingFiles.value[0].id);
        if (change === "unmounted") { wrapper!.unmount(); wrapper = undefined; }
        pending.resolve({ has_isdoc: true, quota });
        await loading;
        expect(resource.showExtractModal.value).toBe(false);
        expect(resource.quota.value).toBeNull();
        expect(mocks.localExtract).not.toHaveBeenCalled();
    });

    it.each([false, true])("discards a stale extraction completion (rejected: %s)", async (reject) => {
        const pending = deferred<{ data: typeof draft; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(pending.promise);
        render();
        await resource.onDocumentSelected(pdf());
        const extraction = resource.confirmExtract();
        expense.value = "expense-b";
        if (reject) pending.reject(new Error("synthetic old request failed"));
        else pending.resolve({ data: draft, quota });
        expect(await extraction).toBeNull();
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(resource.showExtractModal.value).toBe(false);
        expect(mocks.insert).not.toHaveBeenCalled();
    });

    it("ignores a detection from before the company changed away and back", async () => {
        const pending = deferred<{ has_isdoc: boolean; quota: typeof quota }>();
        mocks.localDetect.mockReturnValue(pending.promise);
        render();
        const loading = resource.onDocumentSelected(pdf());
        company.value = "different-local-company";
        company.value = localId;
        pending.resolve({ has_isdoc: true, quota });
        await loading;
        expect(resource.showExtractModal.value).toBe(false);
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(resource.quota.value).toBeNull();
    });

    it.each(["removed", "unmounted"])("discards an extraction after its file is %s", async (change) => {
        const pending = deferred<{ data: typeof draft; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(pending.promise);
        render();
        await resource.onDocumentSelected(pdf());
        const extraction = resource.confirmExtract();
        if (change === "removed") resource.removePendingFile(resource.pendingFiles.value[0].id);
        else { wrapper!.unmount(); wrapper = undefined; }
        pending.resolve({ data: draft, quota });
        expect(await extraction).toBeNull();
        expect(mocks.insert).not.toHaveBeenCalled();
    });

    it("does not submit a second quota-consuming extraction while the first is pending", async () => {
        const pending = deferred<{ data: typeof draft; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(pending.promise);
        render();
        await resource.onDocumentSelected(pdf());
        const first = resource.confirmExtract();
        expect(await resource.confirmExtract()).toBeNull();
        expect(mocks.localExtract).toHaveBeenCalledOnce();
        pending.resolve({ data: draft, quota });
        expect(await first).toEqual(draft);
    });

    it("keeps the file and never falls back to a company route on an older backend", async () => {
        mocks.localDetect.mockRejectedValue({ response: { status: 404, data: { message: "Not found" } } });
        render();
        await resource.onDocumentSelected(pdf());
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(resource.detectError.value).toBe("Not found");
        expect(resource.showExtractModal.value).toBe(false);
        expectNoCompanyApi();
    });

    it("retains a failed local attachment for retry without logging its filename", async () => {
        confirm.mockReturnValue(false);
        const errorLog = vi.spyOn(console, "error").mockImplementation(() => undefined);
        mocks.insert.mockReturnValueOnce({ ok: false, error: "synthetic validation rejection" });
        render();
        await resource.onDocumentSelected(pdf());
        await expect(resource.uploadAllPendingAttachments("expense-a")).rejects.toThrow(en.invoicing.expense_attachment_detect_failed);
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(errorLog).not.toHaveBeenCalled();
        await resource.uploadAllPendingAttachments("expense-a");
        expect(resource.pendingFiles.value).toHaveLength(0);
        expectNoCompanyApi();
    });

    it("does not insert an attachment if its context changes during encoding", async () => {
        confirm.mockReturnValue(false);
        const pending = deferred<string>();
        mocks.read.mockReturnValue(pending.promise);
        render();
        await resource.onDocumentSelected(pdf());
        const upload = resource.uploadAllPendingAttachments("expense-a");
        company.value = "different-local-company";
        pending.resolve("c3ludGhldGlj");
        await expect(upload).rejects.toThrow(en.errors.generic);
        expect(mocks.insert).not.toHaveBeenCalled();
        expectNoCompanyApi();
    });
});
