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
    read: vi.fn(), insert: vi.fn(), lookup: vi.fn(), archiveSave: vi.fn(),
}));
vi.mock("@/composables/useEffectiveAccess", () => ({ useEffectiveAccess: () => ({ purchases: ref({ isdoc_packs: true }) }) }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => mocks.local }));
vi.mock("@/evolu/client", () => ({
    useInvoicingEvolu: () => mocks.evolu,
    expenseByIdIncludingDeletedQuery: (id: string) => ({ expenseId: id }),
}));
vi.mock("@/evolu/expenseAttachmentCrud", () => ({
    LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES: 384 * 1024,
    readFileAsAttachmentBase64: mocks.read,
    insertLocalExpenseAttachmentFromBase64: mocks.insert,
}));
vi.mock("@/evolu/manualExpenseAttachment", () => ({ saveManualExpenseAttachmentArchive: mocks.archiveSave }));
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
let consentChoice = true;
function render(local = true) {
    mocks.local = local;
    company = ref(local ? localId : serverId);
    expense = ref("expense-a");
    wrapper = mount(defineComponent({ setup() {
        resource = useExpenseIsdocAttachment(company, expense);
        return () => h("main");
    } }), { global: { plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })] } });
}
async function choose(file: File) {
    await resource.onDocumentSelected(file);
    if (mocks.local && resource.showProcessingConsent.value) {
        if (consentChoice) await resource.confirmProcessingConsent();
        else resource.skipProcessingConsent();
    }
}
function expectNoCompanyApi() {
    for (const call of [mocks.detect, mocks.extract, mocks.quota, mocks.purchase, mocks.upload]) expect(call).not.toHaveBeenCalled();
}
beforeEach(() => {
    vi.resetAllMocks();
    consentChoice = true;
    mocks.evolu = { loadQuery: mocks.lookup };
    mocks.lookup.mockImplementation(async (query: { expenseId: string }) => [{ id: query.expenseId, companyId: localId, isDeleted: null }]);
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
    mocks.archiveSave.mockResolvedValue(undefined);
});
afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe("expense attachment bridge", () => {
    it.each(["text/html", ""])("uses a PDF-only pending preview for a .pdf with MIME %j", async mime => {
        consentChoice = false;
        render();
        const file = new File(["<script>synthetic</script>"], "synthetic.pdf", { type: mime });
        await choose(file);
        const preview = vi.mocked(URL.createObjectURL).mock.calls[0][0] as Blob;
        expect(preview.type).toBe("application/pdf");
        expect(preview.size).toBe(file.size);
        expect(resource.pendingAttachment.value).toBe(file);
        expect(file.type).toBe(mime);
        expect(mocks.localDetect).not.toHaveBeenCalled();
    });
    it("uses company-free quota, detection and explicitly confirmed extraction for a local company", async () => {
        render();
        await resource.loadQuota();
        const file = pdf();
        await choose(file);
        expect(confirm).not.toHaveBeenCalled();
        expect(mocks.localQuota).toHaveBeenCalledWith();
        expect(mocks.localDetect).toHaveBeenCalledOnce();
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
        consentChoice = false;
        render();
        await choose(pdf());
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

    it.each([true, false])("offers text-PDF extraction without claiming embedded ISDOC (local=%s)", async local => {
        const detect = local ? mocks.localDetect : mocks.detect;
        const extract = local ? mocks.localExtract : mocks.extract;
        detect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        extract.mockResolvedValue({ data: { ...draft, source: "pdf_text", payment_review_required: false }, quota });
        render(local); await choose(pdf());
        expect(resource.lastDetectHasIsdoc.value).toBe(false);
        expect(resource.detectionStatus.value).toBe("text_found");
        expect(resource.extractionSource.value).toBe("pdf_text");
        expect(resource.showExtractModal.value).toBe(true);
        expect(extract).not.toHaveBeenCalled();
        expect(await resource.confirmExtract()).toEqual({ ...draft, source: "pdf_text", payment_review_required: true });
        expect(extract).toHaveBeenCalledOnce();
        expect(mocks.insert).not.toHaveBeenCalled();
        expect(mocks.upload).not.toHaveBeenCalled();
    });

    it.each([
        { has_isdoc: true, extraction_source: "pdf_text" },
        { has_isdoc: false, extraction_source: "isdoc" },
        { has_isdoc: true, extraction_source: null },
        { has_isdoc: false, extraction_source: "unknown" },
        { has_isdoc: false, extraction_source: false },
    ])("rejects inconsistent or unknown detection metadata %j", async detection => {
        mocks.localDetect.mockResolvedValue({ ...detection, quota });
        render(); await choose(pdf());
        expect(resource.detectionStatus.value).toBe("error");
        expect(resource.showExtractModal.value).toBe(false);
        expect(resource.extractionSource.value).toBeNull();
        expect(await resource.confirmExtract()).toBeNull();
        expect(mocks.localExtract).not.toHaveBeenCalled();
    });

    it("rejects a text-PDF source claim for an image attachment", async () => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        render(); await choose(new File(["synthetic image"], "synthetic.png", { type: "image/png" }));
        expect(resource.detectionStatus.value).toBe("error");
        expect(resource.showExtractModal.value).toBe(false);
        expect(mocks.localExtract).not.toHaveBeenCalled();
    });

    it("keeps source metadata attached to the queued file rather than the preview selection", async () => {
        mocks.detect.mockResolvedValueOnce({ has_isdoc: true, extraction_source: "isdoc", quota })
            .mockResolvedValueOnce({ has_isdoc: false, extraction_source: "pdf_text", quota });
        render(false);
        const files = [pdf(), new File(["synthetic"], "text.pdf", { type: "application/pdf" })];
        await resource.onDocumentsSelected(files);
        expect(resource.pendingAttachment.value).toBe(files[1]);
        expect(resource.extractionSource.value).toBe("isdoc");
        expect(resource.lastDetectHasIsdoc.value).toBe(false);
        resource.skipExtract();
        expect(resource.extractionSource.value).toBe("pdf_text");
        expect((await resource.confirmExtract())?.payment_review_required).toBe(true);
        expect((mocks.extract.mock.calls[0][1] as FormData).get("file")).toBe(files[1]);
        expect(resource.showExtractModal.value).toBe(false);
    });

    it("reopens text extraction for the same pending identity without detecting it again", async () => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        render(); await choose(pdf());
        const id = resource.selectedPendingId.value;
        resource.skipExtract();
        await resource.retryDetection();
        expect(resource.showExtractModal.value).toBe(true);
        expect(resource.selectedPendingId.value).toBe(id);
        expect(resource.extractionSource.value).toBe("pdf_text");
        expect(mocks.localDetect).toHaveBeenCalledOnce();
    });

    it("retains the explicit ISDOC source's existing payment-review semantics", async () => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, extraction_source: "isdoc", quota });
        const safeDraft = { ...draft, source: "isdoc", payment_review_required: false };
        mocks.localExtract.mockResolvedValue({ data: safeDraft, quota });
        render(); await choose(pdf());
        expect(resource.lastDetectHasIsdoc.value).toBe(true);
        expect(resource.detectionStatus.value).toBe("found");
        expect(await resource.confirmExtract()).toEqual(safeDraft);
    });

    it.each(["company", "expense"])("drops text drafts after the %s context changes during extraction", async scope => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        const pending = deferred<{ data: typeof draft & { source: "pdf_text" }; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(pending.promise);
        render(); await choose(pdf());
        const extracting = resource.confirmExtract();
        if (scope === "company") company.value = "another-company";
        else expense.value = "another-expense";
        pending.resolve({ data: { ...draft, source: "pdf_text" }, quota });
        expect(await extracting).toBeNull();
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(resource.showExtractModal.value).toBe(false);
        expect(resource.extractionSource.value).toBeNull();
        expect(mocks.insert).not.toHaveBeenCalled();
    });

    it("keeps a mismatched extraction source unresolved without applying its draft", async () => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        mocks.localExtract.mockResolvedValue({ data: { ...draft, source: "isdoc", payment_review_required: false }, quota });
        render(); await choose(pdf());
        await expect(resource.confirmExtract()).rejects.toThrow(en.invoicing.expense_attachment_detect_failed);
        expect(resource.showExtractModal.value).toBe(true);
        expect(resource.hasUnresolvedProcessing.value).toBe(true);
        expect(mocks.insert).not.toHaveBeenCalled();
    });

    it("skips extraction when no embedded ISDOC is detected", async () => {
        mocks.localDetect.mockResolvedValue({ has_isdoc: false, quota });
        render();
        await choose(pdf());
        expect(resource.showExtractModal.value).toBe(false);
        expect(await resource.confirmExtract()).toBeNull();
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(resource.pendingFiles.value).toHaveLength(1);
    });

    it("preserves owned server-company routing and purchases without the local processing prompt", async () => {
        render(false);
        await resource.loadQuota();
        await choose(pdf());
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

    it("keeps both server extraction choices unresolved until each file has a decision", async () => {
        render(false);
        const files = [pdf(), new File(["synthetic"], "second.pdf", { type: "application/pdf" })];
        await resource.onDocumentsSelected(files);
        expect(mocks.detect).toHaveBeenCalledTimes(2);
        expect(resource.showProcessingConsent.value).toBe(false);
        await resource.confirmExtract();
        expect((mocks.extract.mock.calls[0][1] as FormData).get("file")).toBe(files[0]);
        expect(resource.showExtractModal.value).toBe(true);
        await expect(resource.uploadAllPendingAttachments("server-expense")).rejects.toThrow(en.invoicing.expense_attachment_processing_pending);
        resource.skipExtract();
        expect(resource.hasUnresolvedProcessing.value).toBe(false);
        await resource.uploadAllPendingAttachments("server-expense");
        expect(mocks.upload).toHaveBeenCalledTimes(2);
    });

    it("rejects above 10 MiB before consent or upload and accepts the exact manual archive cap", async () => {
        render();
        await choose(pdf(10 * 1024 * 1024 + 1));
        expect(resource.detectError.value).toBe(en.invoicing.expense_attachment_too_large);
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(confirm).not.toHaveBeenCalled();
        expect(mocks.localDetect).not.toHaveBeenCalled();
        await choose(pdf(10 * 1024 * 1024));
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(mocks.localDetect).toHaveBeenCalledOnce();
    });

    it("saves a 450 KiB manual file locally with a stable identity across a failed archive retry", async () => {
        consentChoice = false;
        render();
        const file = pdf(450 * 1024);
        await choose(file);
        mocks.archiveSave.mockRejectedValueOnce(new Error("original_unverified"));
        await expect(resource.uploadAllPendingAttachments("expense-a")).rejects.toThrow("original_unverified");
        expect(resource.pendingFiles.value).toHaveLength(1);
        await resource.uploadAllPendingAttachments("expense-a");
        const first = mocks.archiveSave.mock.calls[0][1];
        const second = mocks.archiveSave.mock.calls[1][1];
        expect(first).toMatchObject({ companyId: localId, expenseId: "expense-a", file });
        expect(first.attachmentId).toBe(second.attachmentId);
        expect(resource.pendingFiles.value).toHaveLength(0);
        expect(mocks.insert).not.toHaveBeenCalled();
        expect(mocks.read).not.toHaveBeenCalled();
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expectNoCompanyApi();
    });

    it("invalidates a large-file archive operation after changing company", async () => {
        consentChoice = false;
        render();
        await choose(pdf(450 * 1024));
        const pending = deferred<void>();
        mocks.archiveSave.mockReturnValue(pending.promise);
        const upload = resource.uploadAllPendingAttachments("expense-a");
        const options = mocks.archiveSave.mock.calls[0][1];
        company.value = "different-company";
        expect(() => options.assertCurrent()).toThrow(en.errors.generic);
        pending.resolve();
        await expect(upload).rejects.toThrow(en.errors.generic);
        expectNoCompanyApi();
    });

    it("fails closed when the local context is unavailable", async () => {
        mocks.evolu = null;
        render();
        await choose(pdf());
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
        await resource.onDocumentSelected(pdf());
        const loading = resource.confirmProcessingConsent();
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
        await choose(pdf());
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
        await resource.onDocumentSelected(pdf());
        const loading = resource.confirmProcessingConsent();
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
        await choose(pdf());
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
        await choose(pdf());
        const first = resource.confirmExtract();
        expect(await resource.confirmExtract()).toBeNull();
        expect(mocks.localExtract).toHaveBeenCalledOnce();
        pending.resolve({ data: draft, quota });
        expect(await first).toEqual(draft);
    });

    it("does not skip or close a modal while confirmed extraction is pending", async () => {
        const pending = deferred<{ data: typeof draft; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(pending.promise);
        render();
        await choose(pdf());
        const extraction = resource.confirmExtract();
        resource.skipExtract();
        expect(resource.showExtractModal.value).toBe(true);
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(mocks.insert).not.toHaveBeenCalled();
        pending.resolve({ data: draft, quota });
        expect(await extraction).toEqual(draft);
        expect(resource.showExtractModal.value).toBe(false);
    });

    it.each(["missing", "deleted", "foreign"])("rejects a %s attachment target after encoding", async (state) => {
        consentChoice = false;
        render();
        await choose(pdf());
        mocks.lookup.mockResolvedValue(state === "missing" ? [] : [{
            id: "expense-a", companyId: state === "foreign" ? "other-company" : localId,
            isDeleted: state === "deleted" ? 1 : null,
        }]);
        await expect(resource.uploadAllPendingAttachments("expense-a")).rejects.toThrow("expense_unavailable");
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(mocks.insert).not.toHaveBeenCalled();
        expectNoCompanyApi();
    });

    it("keeps the file and never falls back to a company route on an older backend", async () => {
        mocks.localDetect.mockRejectedValue({ response: { status: 404, data: { message: "Not found" } } });
        render();
        await choose(pdf());
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(resource.detectError.value).toBe("Not found");
        expect(resource.showExtractModal.value).toBe(false);
        expectNoCompanyApi();
    });

    it("retains a failed local attachment for retry without logging its filename", async () => {
        consentChoice = false;
        const errorLog = vi.spyOn(console, "error").mockImplementation(() => undefined);
        mocks.insert.mockReturnValueOnce({ ok: false, error: "synthetic validation rejection" });
        render();
        await choose(pdf());
        await expect(resource.uploadAllPendingAttachments("expense-a")).rejects.toThrow(en.invoicing.expense_attachment_detect_failed);
        expect(resource.pendingFiles.value).toHaveLength(1);
        expect(errorLog).not.toHaveBeenCalled();
        await resource.uploadAllPendingAttachments("expense-a");
        expect(resource.pendingFiles.value).toHaveLength(0);
        expectNoCompanyApi();
    });

    it("does not insert an attachment if its context changes during encoding", async () => {
        consentChoice = false;
        const pending = deferred<string>();
        mocks.read.mockReturnValue(pending.promise);
        render();
        await choose(pdf());
        const upload = resource.uploadAllPendingAttachments("expense-a");
        company.value = "different-local-company";
        pending.resolve("c3ludGhldGlj");
        await expect(upload).rejects.toThrow(en.errors.generic);
        expect(mocks.insert).not.toHaveBeenCalled();
        expectNoCompanyApi();
    });
});
