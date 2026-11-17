import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { computed, reactive, ref } from "vue";
import { createI18n } from "vue-i18n";
import en from "@/locales/en.json";
import { evoluExpenseToApi, type EvoluExpenseRow } from "@/evolu/expenseMap";
import { toAppRows } from "@/evolu/queryLoad";
import { createExpenseOriginalReference, createManualExpenseOriginalReference, encodeExpenseOriginalReference } from "@/evolu/expenseOriginalReference";
import type { EvoluExpenseAttachmentRow } from "@/evolu/expenseAttachmentCrud";
import { expensePaymentQrDetails } from "@/utils/expensePaymentQr";

const mocks = vi.hoisted(() => ({
    local: true, evolu: {} as object | null, displayForeign: false,
    release: vi.fn(), push: vi.fn(), remember: vi.fn(), refresh: vi.fn(), duplicate: vi.fn(),
    insertExpense: vi.fn(), updateExpense: vi.fn(), updateNote: vi.fn(), insertAttachment: vi.fn(), read: vi.fn(), lookup: vi.fn(),
    detect: vi.fn(), extract: vi.fn(), quota: vi.fn(), purchase: vi.fn(), upload: vi.fn(),
    localDetect: vi.fn(), localExtract: vi.fn(), localQuota: vi.fn(), attachmentBlob: vi.fn(),
    archiveRead: vi.fn(), archiveDelete: vi.fn(),
    get: vi.fn(), update: vi.fn(), create: vi.fn(), history: vi.fn(), list: vi.fn(), company: vi.fn(), blob: vi.fn(), action: vi.fn(),
}));
const localCompany = "abcdefghijklmnopqrstuv";
const serverCompany = "00000000-0000-4000-8000-000000000001";
const newExpenseId = "synthetic-created-expense";
const company = ref(localCompany);
const ownerRouting = ref('private');
// A lagging query is observable state, including when another consumer warms the computed projection.
const filteredLagId = ref<string | null>(null);
const route = reactive({ name: "invoicing-expense-new", params: {} as { expenseId?: string }, query: {} });
type Attachment = { id: string; original_filename: string; mime: string | null; contentBase64?: string; sizeBytes?: string };
function fixture(id = "expense-a", owner = localCompany) {
    return {
        id, companyId: owner, internal_number: "E-2026-001", external_number: null as string | null,
        title: "Synthetic existing expense", status: "recorded", issue_date: "2026-01-01",
        delivery_date: "2026-01-01", due_date: null, paid_at: null,
        total: "1.00", currency: "EUR", variable_symbol: null as string | null, constant_symbol: null,
        specific_symbol: null, internal_note: null as string | null, attachment_path: null, original_filename: null,
        company_contact_id: null as string | null, supplier_name: null as string | null,
        supplier_iban: null as string | null, payment_review_required: false,
        attachments: [] as Attachment[], isDeleted: null as number | null,
    };
}
const models = ref<ReturnType<typeof fixture>[]>([]);
function rawRow(expense: ReturnType<typeof fixture>) { return {
    id: expense.id, bankMatchConflict: Reflect.get(expense, "bank_match_conflict"), bankPaymentClaimJson: Reflect.get(expense, "bank_match_claim"), companyId: expense.companyId, internalNumber: expense.internal_number,
    externalNumber: expense.external_number, title: expense.title, status: expense.status,
    issueDate: expense.issue_date, deliveryDate: expense.delivery_date, dueDate: expense.due_date,
    paidAt: expense.paid_at, cancelledAt: null, total: expense.total, currency: expense.currency,
    variableSymbol: expense.variable_symbol, constantSymbol: expense.constant_symbol,
    specificSymbol: expense.specific_symbol, internalNote: expense.internal_note, isDeleted: expense.isDeleted,
    companyContactId: expense.company_contact_id, supplierName: expense.supplier_name,
    supplierIban: expense.supplier_iban, paymentReviewRequired: expense.payment_review_required ? 1 : 0,
}; }
const expenseRows = computed(() => models.value
    .filter((expense) => expense.isDeleted !== 1 && expense.id !== filteredLagId.value).map(rawRow));
const attachmentRows = computed(() => models.value.flatMap((expense) => expense.attachments.map((attachment) => ({
    id: attachment.id, expenseId: expense.id, originalFilename: attachment.original_filename,
    mimeType: attachment.mime, sizeBytes: attachment.sizeBytes ?? "18", contentBase64: attachment.contentBase64 ?? "c3ludGhldGlj",
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
vi.mock("@/composables/useExpenseSupplierContacts", () => ({ useExpenseSupplierContacts: () => ({
    contacts: ref([]), loading: ref(false), failed: ref(false), refresh: vi.fn(),
}) }));
vi.mock("@/evolu/companyShareRegistry", async importOriginal => ({
    ...await importOriginal<typeof import("@/evolu/companyShareRegistry")>(),
    companyShareRoutingKey: () => ownerRouting.value,
}));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => mocks.local }));
vi.mock("@/evolu/client", () => ({
    useInvoicingEvolu: () => mocks.evolu,
    expenseByIdIncludingDeletedQuery: (id: string) => ({ expenseId: id }),
}));
vi.mock("@/composables/useInvoicingLayout", () => ({ useInvoicingLayout: () => ({ companyId: company, rememberCompany: mocks.remember }) }));
vi.mock("@/composables/useInvoicingCompanySummary", () => ({ useInvoicingCompanySummary: () => ({ defaultCurrency: ref("EUR") }) }));
vi.mock("@/composables/useInvoicingSaveFeedback", () => ({ useInvoicingSaveFeedback: () => ({ notifySaved: vi.fn() }) }));
vi.mock("@/composables/useInvoicingExpenses", () => ({ useInvoicingExpense: () => ({
    evolu: mocks.evolu, expense: currentExpense, expenseRows, attachmentRows, refresh: mocks.refresh,
}) }));
vi.mock("@/evolu/expenseCrud", () => ({
    insertLocalExpense: mocks.insertExpense, updateLocalExpense: mocks.updateExpense, updateLocalExpenseNote: mocks.updateNote,
    cancelLocalExpense: vi.fn(), duplicateLocalExpense: mocks.duplicate, markLocalExpensePaid: vi.fn(), unmarkLocalExpensePaid: vi.fn(),
}));
vi.mock("@/evolu/expenseAttachmentCrud", async (importOriginal) => ({
    ...await importOriginal<typeof import("@/evolu/expenseAttachmentCrud")>(),
    LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES: 384 * 1024,
    readFileAsAttachmentBase64: mocks.read, insertLocalExpenseAttachmentFromBase64: mocks.insertAttachment,
    attachmentBlobUrl: vi.fn(), attachmentContentBlob: mocks.attachmentBlob, deleteLocalExpenseAttachment: vi.fn(),
}));
vi.mock("@/evolu/expenseOriginalArchive", () => ({ readVerifiedExpenseAttachment: mocks.archiveRead, deleteVerifiedExpenseAttachment: mocks.archiveDelete }));
vi.mock("@/services/api", () => ({ getWebBlob: mocks.blob, invoicingApi: {
    companies: { get: mocks.company },
    expenses: {
        get: mocks.get, update: mocks.update, create: mocks.create, history: mocks.history, list: mocks.list, action: mocks.action,
        detectIsdoc: mocks.detect, extract: mocks.extract, isdocQuota: mocks.quota,
        purchaseIsdocPack: mocks.purchase, uploadAttachment: mocks.upload,
        ephemeral: { detectIsdoc: mocks.localDetect, extract: mocks.localExtract, isdocQuota: mocks.localQuota },
    },
} }));
vi.mock("@/components/invoicing/InvoicingPageShell.vue", () => ({ default: { template: '<main><slot name="header" /><slot name="toolbar" /><slot /></main>' } }));
vi.mock("@/components/invoicing/InvoicingAppHeader.vue", () => ({ default: { template: "<header />" } }));
vi.mock("@/components/invoicing/ExpenseShowSidebar.vue", () => ({ default: {
    name: "ExpenseShowSidebar", emits: ["mark-paid", "duplicate"], template: "<aside />",
} }));
vi.mock("@/components/invoicing/ExpenseAttachmentPanel.vue", () => ({ default: {
    name: "ExpenseAttachmentPanel",
    props: ["fileName", "hasPending", "detectError", "detecting", "previewUrl", "previewKind", "pendingFiles", "selectedPendingId"],
    emits: ["file-selected", "files-selected", "clear", "select-pending", "remove-pending", "select-attachment", "download", "remove-saved", "check-isdoc"],
    template: '<section data-test="attachment-panel"><span>{{ fileName }}</span><p>{{ detectError }}</p></section>',
} }));
vi.mock("@/evolu/bankCrud", () => ({ releaseLocalExpenseBankClaim: mocks.release }));
import ExpenseForm from "@/pages/invoicing/ExpenseForm.vue";
import ExpenseShow from "@/pages/invoicing/ExpenseShow.vue";
import ExpenseIsdocExtractModal from "@/components/invoicing/ExpenseIsdocExtractModal.vue";
import ExpenseAttachmentConsentModal from "@/components/invoicing/ExpenseAttachmentConsentModal.vue";
import ExpenseTextPdfReviewModal from "@/components/invoicing/ExpenseTextPdfReviewModal.vue";

type AttachmentInput = { filename: string; mimeType: string | null; contentBase64: string };
function appendAttachment(_evolu: object, id: string, input: AttachmentInput) {
    const expense = models.value.find((row) => row.id === id);
    if (!expense) return { ok: false, error: "synthetic missing expense" };
    const attachmentId = `attachment-${expense.attachments.length + 1}`;
    expense.attachments.push({ id: attachmentId, original_filename: input.filename, mime: input.mimeType });
    return { ok: true, value: { id: attachmentId } };
}
const quota = { unlimited: false, can_extract: true, free_remaining: 19, free_limit: 20, packs: [{ credits: 25, price_eur: 1 }] };
const draft = { payment_review_required: false, title: "Synthetic extracted title", total: 7.5, issue_date: "2026-02-03", currency: "EUR" };
const pdf = (name = "synthetic.pdf") => new File(["%PDF synthetic only"], name, { type: "application/pdf" });
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (error: unknown) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
let wrapper: VueWrapper | undefined;
let consentChoice = false;
function render(kind: "new" | "show" | "edit" = "new", local = true) {
    mocks.local = local;
    company.value = local ? localCompany : serverCompany;
    route.name = kind === "new" ? "invoicing-expense-new" : kind === "edit" ? "invoicing-expense-edit" : "invoicing-expense-show";
    route.params = kind === "new" ? {} : { expenseId: "expense-a" };
    wrapper = mount(kind === "show" ? ExpenseShow : ExpenseForm, { global: {
        plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })],
        stubs: { UblEmbeddedPdfViewer: true, RouterLink: { template: '<a href="#"><slot /></a>' } },
    } });
    return wrapper;
}
async function select(file = pdf()) {
    wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("file-selected", file);
    await flushPromises();
    await settleConsentChoices();
}
async function settleConsentChoices() {
    for (let step = 0; step < 20; step++) {
        const modal = wrapper!.findComponent(ExpenseAttachmentConsentModal);
        if (!modal.exists() || !modal.props("open")) break;
        modal.vm.$emit(consentChoice ? "confirm" : "skip");
        await flushPromises();
    }
}
function expectNoLegacyExpenseWrites() {
    for (const call of [mocks.get, mocks.update, mocks.create, mocks.upload, mocks.detect, mocks.extract, mocks.purchase]) expect(call).not.toHaveBeenCalled();
}
beforeEach(() => {
    vi.resetAllMocks();
    consentChoice = false;
    ownerRouting.value = 'private';
    mocks.release.mockResolvedValue(undefined);
    mocks.local = true;
    mocks.displayForeign = false;
    filteredLagId.value = null;
    mocks.evolu = { loadQuery: mocks.lookup };
    route.params = {};
    route.name = "invoicing-expense-new";
    company.value = localCompany;
    models.value = [fixture()];
    mocks.lookup.mockImplementation(async (query: { expenseId: string }) => models.value.filter((expense) => expense.id === query.expenseId).map(rawRow));
    vi.spyOn(window, "confirm").mockReturnValue(false);
    vi.spyOn(window, "alert").mockImplementation(() => undefined);
    vi.stubGlobal("URL", class extends URL {
        static createObjectURL = vi.fn(() => "blob:synthetic-pdf");
        static revokeObjectURL = vi.fn();
    });
    mocks.push.mockResolvedValue(undefined);
    mocks.refresh.mockResolvedValue(undefined);
    mocks.updateNote.mockResolvedValue({ ok: true });
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
    async function openTextReview(local: boolean, beforeExtraction?: () => void) {
        consentChoice = true;
        if (!local) models.value = [fixture("expense-a", serverCompany)];
        Object.assign(models.value[0]!, { supplier_name: "Retained supplier", supplier_iban: "CZ6508000000192000145399", variable_symbol: "0007" });
        mocks.get.mockImplementation(async () => ({ ...models.value[0]! }));
        mocks.update.mockImplementation(async (_company, id: string, payload: Record<string, unknown>) => {
            const expense = models.value.find(row => row.id === id)!;
            Object.assign(expense, payload, { total: String(payload.total ?? expense.total) }); return expense;
        });
        (local ? mocks.localDetect : mocks.detect).mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        (local ? mocks.localExtract : mocks.extract).mockResolvedValue({ data: { ...draft, source: "pdf_text", payment_review_required: false,
            supplier_name: "Different extracted supplier", supplier_iban: "SK6807200002891987426353", variable_symbol: "00019" }, quota });
        render("show", local); await flushPromises(); await select();
        beforeExtraction?.();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        const review = wrapper!.findComponent(ExpenseTextPdfReviewModal);
        expect(review.props("open")).toBe(true);
        expect(mocks.updateExpense).not.toHaveBeenCalled(); expect(mocks.update).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).not.toHaveBeenCalled(); expect(mocks.upload).not.toHaveBeenCalled();
        return review;
    }

    it.each([true, false].flatMap(local => [true, false].map(accept => ({ local, accept }))))(
        "requires an explicit detail text-PDF review decision (local=$local, accept=$accept)", async ({ local, accept }) => {
            const review = await openTextReview(local);
            const approvedValues = review.props("values");
            expect(approvedValues).toMatchObject({ supplier_name: "Retained supplier", supplier_iban: "CZ6508000000192000145399",
                title: draft.title, total: draft.total, variable_symbol: "00019", payment_review_required: true });
            review.vm.$emit(accept ? "confirm" : "cancel");
            review.vm.$emit("confirm"); await flushPromises();
            const update = local ? mocks.updateExpense : mocks.update;
            expect(update).toHaveBeenCalledTimes(accept ? 1 : 0);
            if (accept) {
                const written = { ...approvedValues };
                for (const field of ["company_contact_id", "supplier_name", "supplier_iban"]) Reflect.deleteProperty(written, field);
                expect(update.mock.lastCall![2]).toEqual(written);
            }
            else expect(models.value[0]!.title).toBe("Synthetic existing expense");
            expect(local ? mocks.insertAttachment : mocks.upload).toHaveBeenCalledOnce();
            expect(wrapper!.findComponent(ExpenseTextPdfReviewModal).props("open")).toBe(false);
            expect(route.query).toEqual({});
        });

    it.each([true, false])("reviews the current supplier record after the initially loaded copy becomes stale (local=%s)", async local => {
        const review = await openTextReview(local, () => {
            Object.assign(models.value[0]!, { company_contact_id: "new-supplier-contact", supplier_name: "New saved supplier",
                supplier_iban: "SK6807200002891987426353" });
        });
        expect(review.props("values")).toMatchObject({ company_contact_id: "new-supplier-contact", supplier_name: "New saved supplier",
            supplier_iban: "SK6807200002891987426353" });
        review.vm.$emit("confirm"); await flushPromises();
        const payload = (local ? mocks.updateExpense : mocks.update).mock.lastCall![2];
        for (const key of ["company_contact_id", "supplier_name", "supplier_iban"]) expect(payload).not.toHaveProperty(key);
        expect(models.value[0]).toMatchObject({ company_contact_id: "new-supplier-contact", supplier_name: "New saved supplier",
            supplier_iban: "SK6807200002891987426353" });
    });

    it.each([true, false])("does not overwrite supplier changes arriving at the text-PDF write boundary (local=%s)", async local => {
        const review = await openTextReview(local);
        const update = local ? mocks.updateExpense : mocks.update;
        update.mockImplementationOnce((_scope: unknown, id: string, payload: Record<string, unknown>) => {
            const current = models.value.find(row => row.id === id)!;
            Object.assign(current, { company_contact_id: null, supplier_name: "Concurrent supplier", supplier_iban: null });
            Object.assign(current, payload);
            return local ? { ok: true, value: { id } } : Promise.resolve({ ...current });
        });
        review.vm.$emit("confirm"); await flushPromises();
        expect(models.value[0]).toMatchObject({ company_contact_id: null, supplier_name: "Concurrent supplier", supplier_iban: null });
        expect(update).toHaveBeenCalledOnce();
    });

    it.each([true, false].flatMap(local => ["company", "expense", "unmount", "record"].map(change => ({ local, change }))))(
        "invalidates a pending text review after $change changes (local=$local)", async ({ local, change }) => {
            const review = await openTextReview(local);
            if (change === "company") company.value = local ? "different-local-company" : "00000000-0000-4000-8000-000000000002";
            if (change === "expense") {
                models.value.push(fixture("expense-b", local ? localCompany : serverCompany));
                route.params.expenseId = "expense-b";
            }
            if (change === "record") models.value[0]!.internal_note = "Payment amount: 2.00";
            if (change === "unmount") wrapper!.unmount();
            await flushPromises();
            if (change !== "unmount") {
                expect(review.props("open")).toBe(false);
                review.vm.$emit("confirm"); await flushPromises();
            }
            expect(mocks.updateExpense).not.toHaveBeenCalled(); expect(mocks.update).not.toHaveBeenCalled();
            expect(mocks.insertAttachment).not.toHaveBeenCalled(); expect(mocks.upload).not.toHaveBeenCalled();
        });

    it.each([true, false])("rejects a record change between approval and its continuation (local=%s)", async local => {
        const review = await openTextReview(local);
        review.vm.$emit("confirm");
        models.value[0]!.internal_note = "Payment amount: 2.00";
        await flushPromises();
        expect(mocks.updateExpense).not.toHaveBeenCalled(); expect(mocks.update).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).not.toHaveBeenCalled(); expect(mocks.upload).not.toHaveBeenCalled();
    });

    it("invalidates a text review after the local encrypted owner routing changes", async () => {
        const review = await openTextReview(true);
        ownerRouting.value = "different-owner"; await flushPromises();
        expect(review.props("open")).toBe(false);
        review.vm.$emit("confirm"); await flushPromises();
        expect(mocks.updateExpense).not.toHaveBeenCalled(); expect(mocks.insertAttachment).not.toHaveBeenCalled();
    });

    it.each([true, false])("keeps the next queued decision blocked until text review finishes (accept=%s)", async accept => {
        consentChoice = true;
        mocks.localDetect.mockResolvedValueOnce({ has_isdoc: false, extraction_source: "pdf_text", quota });
        mocks.localExtract.mockResolvedValueOnce({ data: { ...draft, source: "pdf_text" }, quota });
        render("show"); await flushPromises();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("files-selected", [pdf("first.pdf"), pdf("second.pdf")]);
        await flushPromises();
        const consent = wrapper!.findComponent(ExpenseAttachmentConsentModal);
        consent.vm.$emit("confirm"); await flushPromises();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        const review = wrapper!.findComponent(ExpenseTextPdfReviewModal);
        expect(review.props("open")).toBe(true);
        expect(consent.props("open")).toBe(false);
        expect(consent.props("busy")).toBe(true);
        consent.vm.$emit("confirm"); consent.vm.$emit("skip"); await flushPromises();
        expect(mocks.localDetect).toHaveBeenCalledOnce(); expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        review.vm.$emit(accept ? "confirm" : "cancel"); await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledTimes(accept ? 1 : 0);
        expect(consent.props("open")).toBe(true); expect(consent.props("busy")).toBe(false);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        consent.vm.$emit("skip"); await flushPromises();
        expect(mocks.insertAttachment).toHaveBeenCalledTimes(2);
    });

    it.each([true, false])("opens a copied expense with its exact duplicate marker from detail (local=%s)", async (local) => {
        mocks.duplicate.mockResolvedValueOnce({ ok: true, value: { id: "copied-expense" } });
        mocks.action.mockResolvedValueOnce({ id: "copied-expense" });
        render("show", local);
        await flushPromises();
        wrapper!.findComponent({ name: "ExpenseShowSidebar" }).vm.$emit("duplicate");
        await flushPromises();
        expect(mocks.push).toHaveBeenCalledExactlyOnceWith({ name: "invoicing-expense-edit",
            params: { companyId: local ? localCompany : serverCompany, expenseId: "copied-expense" },
            query: { duplicate: "copied-expense" } });
        expect(local ? mocks.duplicate : mocks.action).toHaveBeenCalledOnce();
        expect(local ? mocks.action : mocks.duplicate).not.toHaveBeenCalled();
    });

    it("saves only the intended note and waits for its committed result before refreshing", async () => {
        const write = deferred<{ ok: true }>();
        mocks.updateNote.mockReturnValueOnce(write.promise);
        render("show");
        await flushPromises();
        mocks.refresh.mockClear();
        await wrapper!.get("textarea").setValue("Synthetic note");
        await wrapper!.get("textarea").trigger("blur");
        await flushPromises();
        expect(mocks.updateNote).toHaveBeenCalledExactlyOnceWith(mocks.evolu, "expense-a", "Synthetic note", localCompany);
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.refresh).not.toHaveBeenCalled();
        write.resolve({ ok: true });
        await flushPromises();
        expect(mocks.refresh).toHaveBeenCalledOnce();
        expectNoLegacyExpenseWrites();
    });

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
        await settleConsentChoices();
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
        expect(window.confirm).not.toHaveBeenCalled();
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

    it.each(["missing", "deleted", "foreign"])("does not retry attachments into a %s retained new expense", async (state) => {
        mocks.insertAttachment.mockReturnValueOnce({ ok: false, error: "synthetic insert rejection" });
        render();
        await flushPromises();
        await select();
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        const created = models.value.find((row) => row.id === newExpenseId)!;
        if (state === "missing") models.value = models.value.filter((row) => row.id !== newExpenseId);
        if (state === "deleted") created.isDeleted = 1;
        if (state === "foreign") created.companyId = "other-company";
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.insertExpense).toHaveBeenCalledOnce();
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(mocks.push).not.toHaveBeenCalled();
        expect(wrapper!.text()).toContain(en.invoicing.expense_unavailable);
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("fileName")).toBe("synthetic.pdf");
    });

    it("allows an active retained expense despite a lagging filtered list", async () => {
        mocks.insertAttachment.mockReturnValueOnce({ ok: false, error: "synthetic insert rejection" });
        render();
        await flushPromises();
        await select();
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        filteredLagId.value = newExpenseId;
        expect(expenseRows.value.some((row) => row.id === newExpenseId)).toBe(false);
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        expect(mocks.insertExpense).toHaveBeenCalledOnce();
        expect(mocks.updateExpense).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).toHaveBeenCalledTimes(2);
        expect(mocks.push).toHaveBeenCalledOnce();
    });

    it("rejects deletion of a newly created expense while attachment encoding is pending", async () => {
        const encoding = deferred<string>();
        mocks.read.mockReturnValue(encoding.promise);
        render();
        await flushPromises();
        await select();
        await wrapper!.find("form").trigger("submit");
        await flushPromises();
        models.value.find((row) => row.id === newExpenseId)!.isDeleted = 1;
        encoding.resolve("c3ludGhldGlj");
        await flushPromises();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.push).not.toHaveBeenCalled();
        expect(wrapper!.text()).toContain(en.invoicing.expense_unavailable);
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
        consentChoice = true;
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

    it.each([true, false])("extracts text-PDF fields before form Save with payment review required (local=%s)", async local => {
        consentChoice = true;
        const detect = local ? mocks.localDetect : mocks.detect;
        const extract = local ? mocks.localExtract : mocks.extract;
        detect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        extract.mockResolvedValue({ data: { ...draft, source: "pdf_text", payment_review_required: false,
            supplier_name: "Synthetic supplier", supplier_iban: "SK6807200002891987426353", variable_symbol: "0007" }, quota });
        render("new", local); await flushPromises(); await select();
        const modal = wrapper!.findComponent(ExpenseIsdocExtractModal);
        expect(modal.props("source")).toBe("pdf_text");
        expect(modal.props("open")).toBe(true);
        expect(mocks.insertExpense).not.toHaveBeenCalled(); expect(mocks.create).not.toHaveBeenCalled();
        modal.vm.$emit("confirm"); await flushPromises();
        expect(mocks.insertExpense).not.toHaveBeenCalled(); expect(mocks.create).not.toHaveBeenCalled();
        await wrapper!.get("form").trigger("submit"); await flushPromises();
        const payload = local ? mocks.insertExpense.mock.lastCall![2] : mocks.create.mock.lastCall![1];
        expect(payload).toMatchObject({ title: draft.title, total: draft.total, variable_symbol: "0007", payment_review_required: true });
        expect(expensePaymentQrDetails({ ...payload, status: "recorded" })).toBeNull();
    });

    it.each([true, false])("retains text payment evidence from detail extraction into subsequent edit review (local=%s)", async local => {
        consentChoice = true;
        if (!local) models.value = [fixture("expense-a", serverCompany)];
        Object.assign(models.value[0]!, { supplier_name: "Saved supplier", supplier_iban: "CZ6508000000192000145399",
            variable_symbol: "0007", internal_note: "Saved expense note" });
        mocks.update.mockImplementation(async (_company, id: string, payload: Record<string, unknown>) => {
            const expense = models.value.find(row => row.id === id)!;
            Object.assign(expense, payload, { total: String(payload.total ?? expense.total) }); return expense;
        });
        const detect = local ? mocks.localDetect : mocks.detect;
        const extract = local ? mocks.localExtract : mocks.extract;
        detect.mockResolvedValue({ has_isdoc: false, extraction_source: "pdf_text", quota });
        extract.mockResolvedValue({ data: { ...draft, source: "pdf_text", payment_review_required: false,
            supplier_name: "Saved supplier", supplier_iban: "CZ6508000000192000145399", variable_symbol: "0007",
            internal_note: "Imported from text PDF.\n\nPayment amount: 2.00" }, quota });
        render("show", local); await flushPromises(); await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        expect(mocks.updateExpense).not.toHaveBeenCalled(); expect(mocks.update).not.toHaveBeenCalled();
        wrapper!.findComponent(ExpenseTextPdfReviewModal).vm.$emit("confirm"); await flushPromises();
        const payload = (local ? mocks.updateExpense : mocks.update).mock.lastCall![2];
        expect(payload).toMatchObject({ payment_review_required: true,
            internal_note: "Saved expense note\n\nImported from text PDF.\n\nPayment amount: 2.00" });
        expect(models.value[0]).toMatchObject({ supplier_name: "Saved supplier", supplier_iban: "CZ6508000000192000145399" });
        expect(models.value[0]!.internal_note).toBe(payload.internal_note);
        expect(expensePaymentQrDetails(models.value[0]!)).toBeNull();
        wrapper!.unmount();
        render("edit", local); await flushPromises();
        expect(wrapper!.find("#expense-confirm-payment-details").exists()).toBe(false);
        await wrapper!.get("textarea").setValue("");
        expect(wrapper!.find("#expense-confirm-payment-details").exists()).toBe(false);
    });

    it.each([undefined, null])("keeps detail extraction fail-closed for review flag %s and preserves saved recipient snapshots", async payment_review_required => {
        models.value[0].supplier_name = "Saved supplier";
        models.value[0].supplier_iban = "CZ6508000000192000145399";
        models.value[0].company_contact_id = "saved-contact";
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        mocks.localExtract.mockResolvedValue({ data: { ...draft, supplier_name: "Changed supplier", supplier_iban: "SK6807200002891987426353", payment_review_required }, quota });
        render("show"); await flushPromises(); await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledWith(mocks.evolu, "expense-a", expect.objectContaining({
            supplier_name: "Saved supplier", supplier_iban: "CZ6508000000192000145399", company_contact_id: "saved-contact", payment_review_required: true,
        }), expect.anything());
    });

    it.each([undefined, null, "", "0007"])("preserves a sparse detail reference or honors supplied reference %s", async variable_symbol => {
        models.value[0].variable_symbol = "0042";
        models.value[0].supplier_name = "Saved supplier";
        models.value[0].supplier_iban = "CZ6508000000192000145399";
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        mocks.localExtract.mockResolvedValue({ data: { ...draft, variable_symbol }, quota });
        render("show"); await flushPromises(); await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledWith(mocks.evolu, "expense-a", expect.objectContaining({
            variable_symbol: variable_symbol ?? "0042", payment_review_required: variable_symbol == null,
        }), expect.anything());
        if (variable_symbol == null) expect(expensePaymentQrDetails(models.value[0])).toBeNull();
    });

    it("requires review when a fresh extraction supplier conflicts with retained detail snapshots despite an explicit VS", async () => {
        models.value[0].variable_symbol = "0042";
        models.value[0].supplier_name = "Saved supplier";
        models.value[0].supplier_iban = "CZ6508000000192000145399";
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        mocks.localExtract.mockResolvedValue({ data: { ...draft, supplier_name: "Different XML supplier", variable_symbol: "0007" }, quota });
        render("show"); await flushPromises(); await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledWith(mocks.evolu, "expense-a", expect.objectContaining({
            supplier_name: "Saved supplier", supplier_iban: "CZ6508000000192000145399", variable_symbol: "0007", payment_review_required: true,
        }), expect.anything());
        expect(expensePaymentQrDetails(models.value[0])).toBeNull();
    });

    it("preserves saved null recipient fields during detail re-extraction", async () => {
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        mocks.localExtract.mockResolvedValue({ data: { ...draft, supplier_name: "Imported supplier", supplier_iban: "SK6807200002891987426353" }, quota });
        render("show"); await flushPromises(); await select();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm"); await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledWith(mocks.evolu, "expense-a", expect.objectContaining({
            supplier_name: null, supplier_iban: null, payment_review_required: false,
        }), expect.anything());
    });

    it("keeps the PDF as a local attachment when field extraction is skipped", async () => {
        consentChoice = true;
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

    it("keeps every dismissal path closed while extraction is pending", async () => {
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        const extraction = deferred<{ data: typeof draft; quota: typeof quota }>();
        mocks.localExtract.mockReturnValue(extraction.promise);
        render("show");
        await flushPromises();
        await select();
        const modal = wrapper!.findComponent(ExpenseIsdocExtractModal);
        modal.vm.$emit("confirm");
        await flushPromises();
        await modal.find("button[aria-label]").trigger("click");
        await modal.find('[role="dialog"]').trigger("click");
        await modal.find('[role="dialog"]').trigger("keydown", { key: "Escape" });
        modal.vm.$emit("skip");
        await flushPromises();
        expect(modal.emitted("close")).toBeUndefined();
        expect(modal.props("open")).toBe(true);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.localExtract).toHaveBeenCalledOnce();
        extraction.resolve({ data: draft, quota });
        await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(modal.props("open")).toBe(false);
    });

    it("allows attachment-only saving after a confirmed extraction is rejected for quota", async () => {
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        mocks.localExtract.mockRejectedValue({ response: { data: { errors: { quota: ["Synthetic quota exhausted"] } } } });
        render("show");
        await flushPromises();
        await select();
        const modal = wrapper!.findComponent(ExpenseIsdocExtractModal);
        modal.vm.$emit("confirm");
        await flushPromises();
        expect(window.alert).toHaveBeenCalledWith("Synthetic quota exhausted");
        expect(modal.props("open")).toBe(true);
        expect(modal.props("extracting")).toBe(false);
        const skip = modal.findAll("button").find((button) => button.text() === en.invoicing.expense_isdoc_extract_skip)!;
        await skip.trigger("click");
        await flushPromises();
        expect(mocks.localExtract).toHaveBeenCalledOnce();
        expect(mocks.updateExpense).not.toHaveBeenCalled();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(modal.props("open")).toBe(false);
        expectNoLegacyExpenseWrites();
    });

    it("keeps new file selection blocked through the extracted draft's local refresh", async () => {
        consentChoice = true;
        mocks.localDetect.mockResolvedValue({ has_isdoc: true, quota });
        render("show");
        await flushPromises();
        await select();
        const refreshed = deferred<void>();
        mocks.refresh.mockReturnValueOnce(refreshed.promise);
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm");
        await flushPromises();
        const panel = wrapper!.findComponent({ name: "ExpenseAttachmentPanel" });
        expect(panel.props("detecting")).toBe(true);
        panel.vm.$emit("file-selected", pdf("late-during-apply.pdf"));
        panel.vm.$emit("files-selected", [pdf("late-multiple-during-apply.pdf")]);
        await flushPromises();
        expect(window.confirm).not.toHaveBeenCalled();
        expect(mocks.localDetect).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        refreshed.resolve();
        await flushPromises();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment.mock.calls[0][2]).toMatchObject({ filename: "synthetic.pdf" });
    });

    it.each([true, false])("retains a failed detail check for retry in local mode %s", async local => {
        consentChoice = true;
        if (!local) models.value = [fixture("expense-a", serverCompany)];
        const detect = local ? mocks.localDetect : mocks.detect;
        detect.mockRejectedValueOnce(new Error("synthetic failure"));
        render("show", local); await flushPromises(); await select();
        const panel = wrapper!.findComponent({ name: "ExpenseAttachmentPanel" });
        expect(panel.props("fileName")).toBe("synthetic.pdf");
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.upload).not.toHaveBeenCalled();
        detect.mockResolvedValueOnce({ has_isdoc: true, quota });
        panel.vm.$emit("check-isdoc"); await flushPromises(); await settleConsentChoices();
        expect(wrapper!.findComponent(ExpenseIsdocExtractModal).props("open")).toBe(true);
        expect(detect).toHaveBeenCalledTimes(2);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.upload).not.toHaveBeenCalled();
    });

    it.each([true, false])("retries the selected earlier failed detail file in local mode %s", async local => {
        consentChoice = true;
        if (!local) models.value = [fixture("expense-a", serverCompany)];
        const detect = local ? mocks.localDetect : mocks.detect;
        detect.mockRejectedValue(new Error("synthetic failure"));
        render("show", local); await flushPromises();
        const panel = wrapper!.findComponent({ name: "ExpenseAttachmentPanel" });
        panel.vm.$emit("files-selected", [pdf("first.pdf"), pdf("second.pdf")]);
        await flushPromises(); await settleConsentChoices();
        const pending = panel.props("pendingFiles") as { id: string; name: string }[];
        expect(pending.map(file => file.name)).toEqual(["first.pdf", "second.pdf"]);
        expect(panel.props("fileName")).toBe("second.pdf");
        panel.vm.$emit("select-pending", pending[0]!.id); await flushPromises();
        expect(panel.props("selectedPendingId")).toBe(pending[0]!.id);
        expect(panel.props("fileName")).toBe("first.pdf");
        detect.mockResolvedValueOnce({ has_isdoc: true, quota });
        panel.vm.$emit("check-isdoc"); await flushPromises(); await settleConsentChoices();
        const args = detect.mock.lastCall!;
        const file = (args[local ? 0 : 1] as FormData).get("file") as File;
        expect(file.name).toBe("first.pdf");
        expect(wrapper!.findComponent(ExpenseIsdocExtractModal).props("open")).toBe(true);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.upload).not.toHaveBeenCalled();
    });

    it.each([true, false])("saves remaining resolved detail files after removing a failed file in local mode %s", async local => {
        consentChoice = true;
        if (!local) models.value = [fixture("expense-a", serverCompany)];
        const detect = local ? mocks.localDetect : mocks.detect;
        detect.mockRejectedValueOnce(new Error("synthetic failure"));
        render("show", local); await flushPromises();
        const panel = wrapper!.findComponent({ name: "ExpenseAttachmentPanel" });
        panel.vm.$emit("files-selected", [pdf("failed.pdf"), pdf("remaining.pdf")]);
        await flushPromises(); await settleConsentChoices();
        const pending = panel.props("pendingFiles") as { id: string; name: string }[];
        expect(pending.map(file => file.name)).toEqual(["failed.pdf", "remaining.pdf"]);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.upload).not.toHaveBeenCalled();
        panel.vm.$emit("remove-pending", pending[0]!.id); await flushPromises();
        expect(panel.props("pendingFiles")).toEqual([]);
        if (local) {
            expect(mocks.insertAttachment).toHaveBeenCalledOnce();
            expect(mocks.insertAttachment.mock.calls[0]![2]).toMatchObject({ filename: "remaining.pdf" });
            expect(mocks.upload).not.toHaveBeenCalled();
        } else {
            expect(mocks.upload).toHaveBeenCalledOnce();
            expect((mocks.upload.mock.calls[0]![2] as FormData).get("file")).toHaveProperty("name", "remaining.pdf");
            expect(mocks.insertAttachment).not.toHaveBeenCalled();
        }
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(mocks.extract).not.toHaveBeenCalled();
    });

    it("blocks the next consent decision until the previous extracted draft is saved", async () => {
        mocks.localDetect.mockResolvedValueOnce({ has_isdoc: true, quota });
        const write = deferred<{ ok: true; value: { id: string } }>();
        mocks.updateExpense.mockReturnValueOnce(write.promise);
        render("show"); await flushPromises();
        const panel = wrapper!.findComponent({ name: "ExpenseAttachmentPanel" });
        panel.vm.$emit("files-selected", [pdf("first.pdf"), pdf("second.pdf")]);
        await flushPromises();
        const consent = wrapper!.findComponent(ExpenseAttachmentConsentModal);
        consent.vm.$emit("confirm"); await flushPromises();
        wrapper!.findComponent(ExpenseIsdocExtractModal).vm.$emit("confirm");
        await flushPromises();
        expect(mocks.updateExpense).toHaveBeenCalledOnce();
        expect(consent.props("open")).toBe(true);
        expect(consent.props("fileName")).toBe("second.pdf");
        expect(consent.props("busy")).toBe(true);
        expect(consent.findAll("button").every(button => button.attributes("disabled") !== undefined)).toBe(true);
        // Guard emitted actions too, independently of the modal's disabled buttons.
        consent.vm.$emit("confirm");
        consent.vm.$emit("skip");
        const pending = panel.props("pendingFiles") as { id: string }[];
        panel.vm.$emit("remove-pending", pending[0]!.id); await flushPromises();
        expect(panel.props("pendingFiles")).toHaveLength(2);
        expect(mocks.localDetect).toHaveBeenCalledOnce();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(consent.props("open")).toBe(true);
        write.resolve({ ok: true, value: { id: "expense-a" } }); await flushPromises();
        expect(consent.props("busy")).toBe(false);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        consent.vm.$emit("skip"); await flushPromises();
        expect(mocks.insertAttachment).toHaveBeenCalledTimes(2);
        expect(mocks.localDetect).toHaveBeenCalledOnce();
    });

    it("keeps a failed detail attachment only after explicit user choice", async () => {
        consentChoice = true;
        mocks.localDetect.mockRejectedValueOnce(new Error("synthetic failure"));
        render("show"); await flushPromises(); await select();
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        const keep = wrapper!.findAll("button").find(button => button.text() === en.invoicing.expense_attachment_processing_skip)!;
        await keep.trigger("click"); await flushPromises();
        expect(mocks.insertAttachment).toHaveBeenCalledOnce();
        expect(mocks.localDetect).toHaveBeenCalledOnce();
        expect(mocks.localExtract).not.toHaveBeenCalled();
    });

    it("shows a local upload failure and retries only remaining files without extracting again", async () => {
        mocks.insertAttachment.mockImplementationOnce(appendAttachment).mockReturnValueOnce({ ok: false, error: "synthetic insert rejection" });
        render("show");
        await flushPromises();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("files-selected", [pdf("first.pdf"), pdf("second.pdf")]);
        await flushPromises();
        await settleConsentChoices();
        expect(wrapper!.text()).toContain(en.invoicing.expense_attachment_upload_failed);
        expect(models.value[0].attachments).toHaveLength(1);
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("fileName")).toBe("second.pdf");
        const retry = wrapper!.findAll("button").find((button) => button.text() === en.invoicing.expense_attachment_retry)!;
        await retry.trigger("click");
        await flushPromises();
        expect(models.value[0].attachments).toHaveLength(2);
        expect(mocks.insertAttachment).toHaveBeenCalledTimes(3);
        expect(mocks.localDetect).not.toHaveBeenCalled();
        expect(mocks.localExtract).not.toHaveBeenCalled();
        expect(wrapper!.text()).not.toContain(en.invoicing.expense_attachment_upload_failed);
        expectNoLegacyExpenseWrites();
    });

    it("shows server upload failure without raw diagnostics and retries the attachment without re-extraction", async () => {
        models.value = [fixture("expense-a", serverCompany)];
        mocks.upload.mockRejectedValueOnce({ response: { data: { message: "synthetic private server diagnostic" } } });
        render("show", false);
        await flushPromises();
        await select();
        expect(wrapper!.text()).toContain(en.invoicing.expense_attachment_upload_failed);
        expect(wrapper!.text()).not.toContain("synthetic private server diagnostic");
        const retry = wrapper!.findAll("button").find((button) => button.text() === en.invoicing.expense_attachment_retry)!;
        await retry.trigger("click");
        await flushPromises();
        expect(mocks.upload).toHaveBeenCalledTimes(2);
        expect(mocks.detect).toHaveBeenCalledOnce();
        expect(mocks.extract).not.toHaveBeenCalled();
        expect(wrapper!.text()).not.toContain(en.invoicing.expense_attachment_upload_failed);
    });

    it("does not apply a late extraction to another expense", async () => {
        consentChoice = true;
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

    it.each([false, true])("ignores an obsolete load success/error and finally while the next expense is loading (error: %s)", async (rejectOld) => {
        const oldRequest = deferred<ReturnType<typeof fixture>>();
        const newRequest = deferred<ReturnType<typeof fixture>>();
        const newer = { ...fixture("expense-b", serverCompany), title: "New current expense" };
        mocks.get.mockImplementation(async (_company: string, id: string) => id === "expense-a" ? oldRequest.promise : newRequest.promise);
        render("show", false);
        await flushPromises();
        route.params.expenseId = "expense-b";
        await flushPromises();
        if (rejectOld) oldRequest.reject(new Error("synthetic obsolete load error"));
        else oldRequest.resolve(fixture("expense-a", serverCompany));
        await flushPromises();
        expect(wrapper!.text()).toContain(en.common.loading);
        expect(wrapper!.text()).not.toContain("Synthetic existing expense");
        expect(wrapper!.findAll('[role="alert"]')).toHaveLength(0);
        newRequest.resolve(newer);
        await flushPromises();
        expect(wrapper!.text()).toContain("New current expense");
        expect(wrapper!.text()).not.toContain("Synthetic existing expense");
    });

    it("clears the displayed expense and note immediately when a new route starts loading", async () => {
        const newRequest = deferred<ReturnType<typeof fixture>>();
        const old = fixture("expense-a", serverCompany);
        old.attachments = [{ id: "old-attachment", original_filename: "old.pdf", mime: "application/pdf" }];
        mocks.blob.mockResolvedValue(new Blob(["synthetic PDF"], { type: "application/pdf" }));
        mocks.get.mockImplementation(async (_company: string, id: string) => id === "expense-a" ? old : newRequest.promise);
        render("show", false);
        await flushPromises();
        expect(wrapper!.text()).toContain("Synthetic existing expense");
        route.params.expenseId = "expense-b";
        await flushPromises();
        expect(wrapper!.text()).not.toContain("Synthetic existing expense");
        expect(wrapper!.find("textarea").exists()).toBe(false);
        expect(wrapper!.text()).toContain(en.common.loading);
        expect(URL.revokeObjectURL).toHaveBeenCalledWith("blob:synthetic-pdf");
        newRequest.resolve({ ...fixture("expense-b", serverCompany), title: "New current expense" });
        await flushPromises();
        expect(wrapper!.text()).toContain("New current expense");
    });

    it.each([false, true])("does not replace a new preview when an old PDF download settles (error: %s)", async (rejectOld) => {
        const oldBlob = deferred<Blob>();
        const newerBlob = new Blob(["synthetic newer PDF"], { type: "application/pdf" });
        const old = fixture("expense-a", serverCompany);
        old.attachments = [{ id: "old-attachment", original_filename: "old.pdf", mime: "application/pdf" }];
        const newer = { ...fixture("expense-b", serverCompany), title: "New current expense" };
        newer.attachments = [{ id: "new-attachment", original_filename: "new.pdf", mime: "application/pdf" }];
        mocks.get.mockImplementation(async (_company: string, id: string) => id === "expense-a" ? old : newer);
        mocks.blob.mockImplementation(async (url: string) => url.includes("/expense-a/") ? oldBlob.promise : newerBlob);
        vi.mocked(URL.createObjectURL).mockImplementation((blob) => blob === newerBlob ? "blob:newer" : "blob:obsolete");
        render("show", false);
        await flushPromises();
        route.params.expenseId = "expense-b";
        await flushPromises();
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("previewUrl")).toBe("blob:newer");
        if (rejectOld) oldBlob.reject(new Error("synthetic obsolete preview error"));
        else oldBlob.resolve(new Blob(["synthetic obsolete PDF"], { type: "application/pdf" }));
        await flushPromises();
        expect(wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).props("previewUrl")).toBe("blob:newer");
        expect(URL.createObjectURL).toHaveBeenCalledOnce();
    });

    it.each([false, true])("does not replace current neighbor IDs when an old request settles (error: %s)", async (rejectOld) => {
        const oldNeighbors = deferred<{ id: string }[]>();
        mocks.get.mockImplementation(async (_company: string, id: string) => fixture(id, serverCompany));
        mocks.list.mockReturnValueOnce(oldNeighbors.promise).mockResolvedValueOnce([{ id: "expense-b" }, { id: "expense-c" }]);
        render("show", false);
        await flushPromises();
        route.params.expenseId = "expense-b";
        await flushPromises();
        expect(wrapper!.find(".expense-show-toolbar__pager-count").text()).toBe("1 / 2");
        if (rejectOld) oldNeighbors.reject(new Error("synthetic obsolete neighbors error"));
        else oldNeighbors.resolve([{ id: "expense-a" }, { id: "obsolete-b" }, { id: "obsolete-c" }]);
        await flushPromises();
        expect(wrapper!.find(".expense-show-toolbar__pager-count").text()).toBe("1 / 2");
    });

    it("does not restore an old expense when its note-save response arrives after navigation", async () => {
        const noteSave = deferred<ReturnType<typeof fixture>>();
        mocks.update.mockReturnValue(noteSave.promise);
        mocks.get.mockImplementation(async (_company: string, id: string) => ({ ...fixture(id, serverCompany), title: id === "expense-a" ? "Old current expense" : "New current expense" }));
        render("show", false);
        await flushPromises();
        await wrapper!.find("textarea").setValue("Synthetic old note");
        await wrapper!.find("textarea").trigger("blur");
        await flushPromises();
        route.params.expenseId = "expense-b";
        await flushPromises();
        noteSave.resolve({ ...fixture("expense-a", serverCompany), title: "Obsolete saved expense" });
        await flushPromises();
        expect(wrapper!.text()).toContain("New current expense");
        expect(wrapper!.text()).not.toContain("Obsolete saved expense");
        expect((wrapper!.find("textarea").element as HTMLTextAreaElement).value).toBe("");
    });

    it("does not restore an old expense when a mark-paid response arrives after navigation", async () => {
        const markPaid = deferred<ReturnType<typeof fixture>>();
        mocks.action.mockReturnValue(markPaid.promise);
        mocks.get.mockImplementation(async (_company: string, id: string) => ({ ...fixture(id, serverCompany), title: id === "expense-a" ? "Old current expense" : "New current expense" }));
        render("show", false);
        await flushPromises();
        wrapper!.findComponent({ name: "ExpenseShowSidebar" }).vm.$emit("mark-paid");
        await flushPromises();
        route.params.expenseId = "expense-b";
        await flushPromises();
        markPaid.resolve({ ...fixture("expense-a", serverCompany), title: "Obsolete paid expense", status: "paid" });
        await flushPromises();
        expect(wrapper!.text()).toContain("New current expense");
        expect(wrapper!.text()).not.toContain("Obsolete paid expense");
        expect(mocks.history).toHaveBeenCalledTimes(2);
    });
});


/** Seed the opaque revision returned by the actual expense mapper. */
function markBankConflict(){Object.assign(models.value[0]!,{bank_match_conflict:true,bank_match_claim:'synthetic-opaque-A'});}
/** Locate recovery by its translated label, as a user would find it. */
function releaseButton(){return wrapper!.findAll('button').find(button=>button.text()===en.invoicing.bank_unmatch);}
describe('expense-detail conflict recovery and reactive payment view',()=>{
 it('shows a conflict action from actual raw-to-API mapping and requires explicit confirmation',async()=>{
  markBankConflict();render('show');await flushPromises();expect(wrapper!.text()).toContain(en.invoicing.expense_bank_match_conflict);expect(releaseButton()).toBeDefined();
  await releaseButton()!.trigger('click');expect(window.confirm).toHaveBeenCalledExactlyOnceWith(en.invoicing.bank_unlink_expense_conflict_confirm);expect(mocks.release).not.toHaveBeenCalled();
 });
 it('passes the exact opaque revision and waits for one completion while disabled',async()=>{
  const pending=deferred<void>();mocks.release.mockReturnValueOnce(pending.promise);vi.mocked(window.confirm).mockReturnValue(true);
  markBankConflict();render('show');await flushPromises();await releaseButton()!.trigger('click');await flushPromises();
  expect(mocks.release).toHaveBeenCalledExactlyOnceWith(mocks.evolu,localCompany,'expense-a','synthetic-opaque-A');expect(releaseButton()!.attributes('disabled')).toBeDefined();
  await releaseButton()!.trigger('click');expect(mocks.release).toHaveBeenCalledOnce();
  Object.assign(models.value[0]!,{bank_match_conflict:false,bank_match_claim:null});pending.resolve();await flushPromises();expect(releaseButton()).toBeUndefined();
 });
 it('keeps a stale-claim failure recoverable and never retries or navigates',async()=>{
  mocks.release.mockRejectedValueOnce(new Error('bank_expense_claim_changed'));vi.mocked(window.confirm).mockReturnValue(true);
  markBankConflict();render('show');await flushPromises();const reads=mocks.refresh.mock.calls.length;await releaseButton()!.trigger('click');await flushPromises();
  expect(window.alert).toHaveBeenCalledExactlyOnceWith(en.invoicing.bank_expense_match_failed);expect(mocks.release).toHaveBeenCalledOnce();expect(mocks.refresh).toHaveBeenCalledTimes(reads);expect(mocks.push).not.toHaveBeenCalled();
 });
 it('does not offer local claim recovery for legacy server expense details',async()=>{
  markBankConflict();render('show',false);await flushPromises();expect(releaseButton()).toBeUndefined();expect(mocks.release).not.toHaveBeenCalled();
 });
 it('updates payment-only fields from incoming projection while preserving an unsaved note draft',async()=>{
  markBankConflict();Object.assign(models.value[0]!,{internal_note:'Saved note'});render('show');await flushPromises();
  await wrapper!.get('textarea').setValue('My unsaved note');expect(wrapper!.text()).toContain(en.invoicing.expense_bank_match_conflict);
  Object.assign(models.value[0]!,{status:'paid',paid_at:'2026-11-05',bank_match_conflict:false,bank_match_claim:null,internal_note:'Other device note'});await flushPromises();
  expect(wrapper!.text()).not.toContain(en.invoicing.expense_bank_match_conflict);expect((wrapper!.get('textarea').element as HTMLTextAreaElement).value).toBe('My unsaved note');
 });
 it('does not surface an old recovery failure after a real company change',async()=>{
  const pending=deferred<void>();mocks.release.mockReturnValueOnce(pending.promise);vi.mocked(window.confirm).mockReturnValue(true);
  markBankConflict();render('show');await flushPromises();await releaseButton()!.trigger('click');company.value='bcdefghijklmnopqrstuvw';await flushPromises();
  pending.reject(new Error('bank_expense_claim_changed'));await flushPromises();expect(window.alert).not.toHaveBeenCalled();expect(mocks.push).not.toHaveBeenCalled();
 });
});


const embeddedXml = (filename = "received.pdf") => `<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cac:AdditionalDocumentReference><cac:Attachment><cbc:EmbeddedDocumentBinaryObject filename="${filename}" mimeCode="application/pdf">${btoa('%PDF-1.4 synthetic received PDF')}</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference></Invoice>`;
const xmlBlob = (xml: string) => Object.assign(new Blob([xml], { type: 'application/xml' }), { text: async () => xml });

describe('embedded PDFs from preserved expense XML', () => {
    it.each([true, false])('reloads saved XML after clearing a pending attachment that interrupted its read (local: %s)', async local => {
        const oldText = deferred<string>();
        const expense = fixture('expense-a', local ? localCompany : serverCompany);
        expense.attachments = [{ id: 'original', original_filename: 'received.xml', mime: 'application/xml' }];
        models.value = [expense];
        mocks.get.mockResolvedValue(expense);
        const firstBlob = Object.assign(new Blob(['synthetic XML'], { type: 'application/xml' }), { text: () => oldText.promise });
        mocks.attachmentBlob.mockReturnValueOnce(xmlBlob(embeddedXml('initial.pdf')))
            .mockReturnValueOnce(firstBlob).mockReturnValue(xmlBlob(embeddedXml('restored.pdf')));
        mocks.blob.mockResolvedValueOnce(xmlBlob(embeddedXml('initial.pdf')))
            .mockResolvedValueOnce(firstBlob).mockResolvedValue(xmlBlob(embeddedXml('restored.pdf')));
        consentChoice = true;
        for (const detect of [mocks.detect, mocks.localDetect]) detect.mockResolvedValue({ has_isdoc: true, quota });
        render('show', local);
        await flushPromises();
        wrapper!.findComponent({ name: 'ExpenseAttachmentPanel' }).vm.$emit('select-attachment', 'original');
        await flushPromises();
        await select(pdf('pending.pdf'));
        const panel = wrapper!.findComponent({ name: 'ExpenseAttachmentPanel' });
        expect(panel.props('hasPending')).toBe(true);
        oldText.resolve(embeddedXml('obsolete.pdf'));
        await flushPromises();
        expect(wrapper!.find('[data-testid="ubl-embedded-pdfs"]').exists()).toBe(false);

        panel.vm.$emit('clear');
        await flushPromises();
        expect(panel.props('hasPending')).toBe(false);
        expect(wrapper!.get('[data-testid="ubl-embedded-pdfs"]').text()).toContain('restored.pdf');
        expect(wrapper!.text()).not.toContain('obsolete.pdf');
        expect(local ? mocks.attachmentBlob : mocks.blob).toHaveBeenCalledTimes(3);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.upload).not.toHaveBeenCalled();
    });

    it.each([true, false])('revokes the saved preview without reloading when no attachment is pending (local: %s)', async local => {
        const expense = fixture('expense-a', local ? localCompany : serverCompany);
        expense.attachments = [{ id: 'original', original_filename: 'received.pdf', mime: 'application/pdf' }];
        models.value = [expense];
        mocks.get.mockResolvedValue(expense);
        mocks.attachmentBlob.mockReturnValue(pdf('received.pdf'));
        mocks.blob.mockResolvedValue(pdf('received.pdf'));
        render('show', local);
        await flushPromises();
        const panel = wrapper!.findComponent({ name: 'ExpenseAttachmentPanel' });
        expect(panel.props('previewUrl')).toBe('blob:synthetic-pdf');
        panel.vm.$emit('clear');
        await flushPromises();
        expect(panel.props('previewUrl')).toBeNull();
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:synthetic-pdf');
        expect(local ? mocks.attachmentBlob : mocks.blob).toHaveBeenCalledTimes(1);
    });

    it.each([true, false])('offers PDFs in an already imported expense without changing original custody (local: %s)', async local => {
        const expense = fixture('expense-a', local ? localCompany : serverCompany);
        expense.attachments = [{ id: 'original', original_filename: 'received.xml', mime: 'application/xml' }];
        models.value = [expense];
        mocks.get.mockResolvedValue(expense);
        mocks.attachmentBlob.mockReturnValue(xmlBlob(embeddedXml()));
        mocks.blob.mockResolvedValue(xmlBlob(embeddedXml()));
        render('show', local);
        await flushPromises();
        expect(wrapper!.get('[data-testid="ubl-embedded-pdfs"]').text()).toContain('received.pdf');
        await wrapper!.get('button[aria-label="View received.pdf"]').trigger('click');
        expect(wrapper!.findComponent({ name: 'UblEmbeddedPdfViewer' }).exists()).toBe(true);
        expect(mocks.insertAttachment).not.toHaveBeenCalled();
        expect(mocks.update).not.toHaveBeenCalled();
        if (local) expect(mocks.blob).not.toHaveBeenCalled();
        else expect(mocks.blob).toHaveBeenCalledExactlyOnceWith(`/api/invoicing/companies/${serverCompany}/expenses/expense-a/attachments/original`);
    });

    it.each(['document', 'company', 'attachment'])('discards a late XML text read after changing %s', async kind => {
        const oldText = deferred<string>();
        const old = fixture('expense-a', serverCompany);
        old.attachments = [{ id: 'old', original_filename: 'old.xml', mime: 'application/xml' }, { id: 'new', original_filename: 'new.xml', mime: 'application/xml' }];
        const newer = fixture('expense-b', 'another-company');
        newer.attachments = [{ id: 'new', original_filename: 'new.xml', mime: 'application/xml' }];
        mocks.get.mockImplementation(async (_company: string, id: string) => id === 'expense-a' && _company === serverCompany ? old : { ...newer, id });
        let oldRequests = 0;
        mocks.blob.mockImplementation(async (url: string) => {
            if (!url.endsWith('/old')) return xmlBlob(embeddedXml('current.pdf'));
            if (kind === 'attachment' && ++oldRequests === 1) return xmlBlob(embeddedXml('initial.pdf'));
            return Object.assign(new Blob(['synthetic XML'], { type: 'application/xml' }), { text: () => oldText.promise });
        });
        render('show', false);
        await flushPromises();
        if (kind === 'document') route.params.expenseId = 'expense-b';
        else if (kind === 'company') company.value = 'another-company';
        else {
            wrapper!.findComponent({ name: 'ExpenseAttachmentPanel' }).vm.$emit('select-attachment', 'old');
            await flushPromises();
            wrapper!.findComponent({ name: 'ExpenseAttachmentPanel' }).vm.$emit('select-attachment', 'new');
        }
        await flushPromises();
        oldText.resolve(embeddedXml('obsolete.pdf'));
        await flushPromises();
        expect(wrapper!.text()).not.toContain('obsolete.pdf');
        expect(wrapper!.get('[data-testid="ubl-embedded-pdfs"]').text()).toContain('current.pdf');
    });

    it('keeps ordinary XML attachments free of an unrelated PDF warning', async () => {
        const expense = fixture('expense-a', serverCompany);
        expense.attachments = [{ id: 'isdoc', original_filename: 'expense.xml', mime: 'application/xml' }];
        mocks.get.mockResolvedValue(expense);
        mocks.blob.mockResolvedValue(xmlBlob('<Invoice xmlns="http://isdoc.cz/namespace/2013"/>'));
        render('show', false);
        await flushPromises();
        expect(wrapper!.find('[data-testid="ubl-embedded-pdfs"]').exists()).toBe(false);
        expect(wrapper!.text()).not.toContain(en.invoicing.ubl_pdf_rejected);
    });

    it('does not allocate text for an oversized XML attachment', async () => {
        const expense = fixture('expense-a', serverCompany);
        expense.attachments = [{ id: 'large', original_filename: 'large.xml', mime: 'application/xml' }];
        const text = vi.fn();
        mocks.get.mockResolvedValue(expense);
        mocks.blob.mockResolvedValue(Object.assign(new Blob([new Uint8Array(2 * 1024 * 1024 + 1)], { type: 'application/xml' }), { text }));
        render('show', false);
        await flushPromises();
        expect(text).not.toHaveBeenCalled();
        expect(wrapper!.text()).toContain(en.invoicing.ubl_pdf_rejected);
    });
});


describe("archived received originals on expense detail", () => {
    function archivedExpense(sizeBytes = 450 * 1024) {
        const expense = fixture("expense-a", localCompany);
        const ref = createExpenseOriginalReference({ companyId: localCompany, receiptId: "receipt", expenseId: expense.id,
            attachmentId: "original", sizeBytes, sha256: "a".repeat(64) });
        expense.attachments = [{ id: "original", original_filename: "received.xml", mime: "application/xml",
            contentBase64: encodeExpenseOriginalReference(ref), sizeBytes: String(sizeBytes) }];
        models.value = [expense];
        return expense;
    }

    it("uses a PDF-only saved preview while downloads preserve the original contradictory MIME", async () => {
        const expense = archivedExpense();
        const blob = new Blob(["<script>synthetic</script>"], { type: "text/html" });
        const ref = createManualExpenseOriginalReference({ companyId: localCompany, expenseId: expense.id,
            attachmentId: "original", sizeBytes: blob.size, sha256: "a".repeat(64), mimeType: "text/html" });
        expense.attachments = [{ id: "original", original_filename: "manual.pdf", mime: "text/html",
            contentBase64: encodeExpenseOriginalReference(ref), sizeBytes: String(blob.size) }];
        mocks.archiveRead.mockResolvedValue({ bytes: new Uint8Array(blob.size), blob });
        vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
        render("show", true);
        await flushPromises();
        const preview = vi.mocked(URL.createObjectURL).mock.calls.at(-1)![0] as Blob;
        expect(preview.type).toBe("application/pdf");
        expect(preview.size).toBe(blob.size);
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("download");
        await flushPromises();
        expect(URL.createObjectURL).toHaveBeenLastCalledWith(blob);
        expect(blob.type).toBe("text/html");
    });

    it("previews embedded PDF from verified bytes and downloads the exact original after a fresh read", async () => {
        archivedExpense();
        const xml = embeddedXml("archived.pdf").padEnd(450 * 1024, " ");
        const blob = xmlBlob(xml);
        mocks.archiveRead.mockResolvedValue({ bytes: new TextEncoder().encode(xml), blob });
        const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
        render("show", true);
        await flushPromises();
        expect(wrapper!.get('[data-testid="ubl-embedded-pdfs"]').text()).toContain("archived.pdf");
        expect(mocks.archiveRead).toHaveBeenCalledWith(mocks.evolu, localCompany,
            expect.objectContaining({ id: "original" }), { maxBytes: 2 * 1024 * 1024 });
        expect(mocks.attachmentBlob).not.toHaveBeenCalled();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("download");
        await flushPromises();
        expect(mocks.archiveRead).toHaveBeenLastCalledWith(mocks.evolu, localCompany, expect.objectContaining({ id: "original" }));
        expect(URL.createObjectURL).toHaveBeenLastCalledWith(blob);
        expect(click).toHaveBeenCalledOnce();
        expect(mocks.blob).not.toHaveBeenCalled();
    });

    it("keeps the 2MiB XML preview bound while allowing a verified raw download", async () => {
        archivedExpense(2 * 1024 * 1024 + 1);
        const text = vi.fn();
        const blob = Object.assign(new Blob([new Uint8Array(2 * 1024 * 1024 + 1)], { type: "application/xml" }), { text });
        mocks.archiveRead.mockImplementation(async (_client, _company, _row, options) => {
            if (options?.maxBytes) throw new Error("original_too_large");
            return { bytes: new Uint8Array(blob.size), blob };
        });
        vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
        render("show", true);
        await flushPromises();
        expect(wrapper!.text()).toContain(en.invoicing.ubl_pdf_rejected);
        expect(text).not.toHaveBeenCalled();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("download");
        await flushPromises();
        expect(URL.createObjectURL).toHaveBeenCalledWith(blob);
        expect(text).not.toHaveBeenCalled();
    });

    it("does not expose an unverified marker as a file when its archive is unavailable", async () => {
        archivedExpense();
        mocks.archiveRead.mockRejectedValue(new Error("original_unverified"));
        const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
        render("show", true);
        await flushPromises();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("download");
        await flushPromises();
        expect(click).not.toHaveBeenCalled();
        expect(mocks.attachmentBlob).not.toHaveBeenCalled();
        expect(window.alert).toHaveBeenCalledWith(en.invoicing.expense_attachment_download_failed);
    });

    it("waits for authorized archive cleanup before refreshing a deleted attachment", async () => {
        archivedExpense();
        mocks.archiveRead.mockResolvedValue({ bytes: new Uint8Array(0), blob: xmlBlob(embeddedXml()) });
        const deletion = deferred<void>();
        mocks.archiveDelete.mockReturnValue(deletion.promise);
        vi.mocked(window.confirm).mockReturnValue(true);
        render("show", true);
        await flushPromises();
        mocks.refresh.mockClear();
        wrapper!.findComponent({ name: "ExpenseAttachmentPanel" }).vm.$emit("remove-saved", "original");
        await flushPromises();
        expect(mocks.archiveDelete).toHaveBeenCalledWith(mocks.evolu, localCompany, expect.objectContaining({ id: "original" }));
        expect(mocks.refresh).not.toHaveBeenCalled();
        deletion.resolve();
        await flushPromises();
        expect(mocks.refresh).toHaveBeenCalledOnce();
    });
});
