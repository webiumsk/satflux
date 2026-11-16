import { projectExpensePayments } from "@/evolu/bankExpenseClaims";
import type { EvoluBankTransactionRow, EvoluBankTransactionMatchRow } from "@/evolu/bankMap";
import { computed, ref, watch, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import {
    allDocumentsQuery,
    allExpenseAttachmentsQuery,
    allExpenseOriginalSourcesQuery,
    allExpenseOriginalChunksQuery,
    allExpensesQuery,
    allBankTransactionsQuery,
    allBankTransactionMatchesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { toAppRows } from "@/evolu/queryLoad";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluExpenseRow } from "@/evolu/expenseMap";
import type { EvoluExpenseAttachmentRow } from "@/evolu/expenseAttachmentCrud";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import {
    buildExpensePayload,
    resolveAccountantExportAttachments,
    selectExpensesForExport,
    ACCOUNTANT_EXPORT_MAX_ATTACHMENT_BYTES,
    defaultAccountantExportOptions,
    planAccountantExport,
    type AccountantExportBatch,
    type AccountantExportOptions,
    type AccountantExportPlan,
    type ExportDateRange,
} from "@/evolu/accountantExportSelect";
import {
    buildAccountantExportEphemeralRequest,
    downloadResponseBlob,
    fetchEphemeralAccountantExport,
} from "@/evolu/ephemeralBridge";
import { downloadCsvBlob } from "@/evolu/documentBulkLocal";
import { useLocalInvoiceDocumentSupport } from "@/composables/useLocalInvoiceDocument";
import {
    defaultIssuePeriodState,
    resolveIssuePeriodRange,
    type IssuePeriodState,
} from "@/composables/useInvoicingIssuePeriod";
import { readVerifiedExpenseAttachment } from "@/evolu/expenseOriginalArchive";
import { isExpenseOriginalReference } from "@/evolu/expenseOriginalReference";
import { invoicingApi } from "@/services/api";

const MIN_DATE = "0000-01-01";
const MAX_DATE = "9999-12-31";

function slug(value: string): string {
    return value
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") || "company";
}

/**
 * "Balík pre účtovníka" page state for both invoicing modes. Local-first
 * builds the transient payload from Evolu (and splits the period by month
 * when it would exceed the server caps); server mode simply downloads the
 * GET endpoint and lets the server select the rows.
 */
export function useAccountantExport(companyId: Ref<string>, companyName: Ref<string | null | undefined>) {
    const localFirst = isInvoicingLocalFirst();
    const period = ref<IssuePeriodState>({ ...defaultIssuePeriodState(), preset: "last_month" });
    const options = ref<AccountantExportOptions>(defaultAccountantExportOptions());
    const downloading = ref(false);
    const progress = ref<{ done: number; total: number } | null>(null);
    const error = ref<string | null>(null);
    const loading = ref(localFirst);
    const loadError = ref(false);

    const range = computed<ExportDateRange>(() => {
        const resolved = resolveIssuePeriodRange(period.value);
        return { from: resolved.from ?? MIN_DATE, to: resolved.to ?? MAX_DATE };
    });

    // ---- local-first data -------------------------------------------------
    const evolu = localFirst ? useInvoicingEvolu() : null;
    const localDoc = localFirst ? useLocalInvoiceDocumentSupport() : null;
    const documentRows = localFirst ? useQuery(allDocumentsQuery) : ref<readonly unknown[]>([]);
    const expenseRows = localFirst ? useQuery(allExpensesQuery) : ref<readonly unknown[]>([]);
    const transactionRows = localFirst ? useQuery(allBankTransactionsQuery) : ref<readonly unknown[]>([]);
    const matchRows = localFirst ? useQuery(allBankTransactionMatchesQuery) : ref<readonly unknown[]>([]);
    const attachmentRows = localFirst ? useQuery(allExpenseAttachmentsQuery) : ref<readonly unknown[]>([]);
    const originalSourceRows = localFirst ? useQuery(allExpenseOriginalSourcesQuery) : ref<readonly unknown[]>([]);
    const originalChunkRows = localFirst ? useQuery(allExpenseOriginalChunksQuery) : ref<readonly unknown[]>([]);

    /** Load raw expense, bank and attachment queries before preparing the accountant export view. */
    async function load(): Promise<void> {
        if (!evolu) return;
        loading.value = true;
        loadError.value = false;
        try {
            await Promise.all([
                evolu.loadQuery(allDocumentsQuery),
                evolu.loadQuery(allExpensesQuery),
                evolu.loadQuery(allBankTransactionsQuery),
                evolu.loadQuery(allBankTransactionMatchesQuery),
                evolu.loadQuery(allExpenseAttachmentsQuery),
                evolu.loadQuery(allExpenseOriginalSourcesQuery),
                evolu.loadQuery(allExpenseOriginalChunksQuery),
            ]);
        } catch (e) {
            loadError.value = true;
            console.error("Failed to load accountant export data", e);
        } finally {
            loading.value = false;
        }
    }
    void load();

    const documents = computed(() => toAppRows<EvoluDocumentRow>(documentRows.value));
    const expenses = computed(() => projectExpensePayments(toAppRows<EvoluExpenseRow>(expenseRows.value),
        toAppRows<EvoluBankTransactionRow>(transactionRows.value), toAppRows<EvoluBankTransactionMatchRow>(matchRows.value)));
    const attachments = computed(() => toAppRows<EvoluExpenseAttachmentRow>(attachmentRows.value));

    // Synchronous invalidation closes races during attachment reads and bridge
    // preparation, including in-place option-array and row-field changes.
    let selectionRevision = 0;
    if (localFirst) watch(() => [companyId.value, companyName.value, range.value, options.value,
        documents.value, expenses.value, attachments.value], () => { selectionRevision += 1; }, { deep: true, flush: "sync" });

    function captureExportSelection() {
        return {
            revision: selectionRevision,
            company: companyId.value,
            companyName: companyName.value ?? "company",
            range: { ...range.value },
            options: { ...options.value, formats: [...options.value.formats] },
            documents: documents.value.map(row => ({ ...row })),
            expenses: expenses.value.map(row => ({ ...row })),
            attachments: attachments.value.map(row => ({ ...row })),
        };
    }
    type ExportSelection = ReturnType<typeof captureExportSelection>;

    function assertExportSelection(selection: ExportSelection, signal?: AbortSignal): void {
        if (selection.revision !== selectionRevision || selection.company !== companyId.value || signal?.aborted) {
            throw new DOMException("Export context changed", "AbortError");
        }
    }

    async function resolveExportAttachments(selection: ExportSelection, signal: AbortSignal) {
        const selected = selection.options.includeExpenseAttachments
            ? new Set(selectExpensesForExport(selection.expenses, selection.company, selection.range).map(row => String(row.id))) : new Set<string>();
        const rows = await resolveAccountantExportAttachments(selection.attachments, selected, async row => {
            assertExportSelection(selection, signal);
            if (!evolu) throw new Error("local_context_unavailable");
            const verified = await readVerifiedExpenseAttachment(evolu, selection.company, row, { maxBytes: ACCOUNTANT_EXPORT_MAX_ATTACHMENT_BYTES });
            assertExportSelection(selection, signal);
            return verified.bytes;
        });
        // The resolver treats unreadable files as skipped, so repeat the context
        // guard outside it before planning or preparing any request.
        assertExportSelection(selection, signal);
        return rows;
    }

    const resolvedAttachments = ref<EvoluExpenseAttachmentRow[]>([]);
    const resolvingAttachments = ref(false);
    let resolutionGeneration = 0;
    async function refreshResolvedAttachments(): Promise<EvoluExpenseAttachmentRow[]> {
        const generation = ++resolutionGeneration;
        const company = companyId.value;
        resolvingAttachments.value = true;
        const selected = options.value.includeExpenseAttachments
            ? new Set(selectExpensesForExport(expenses.value, company, range.value).map(row => String(row.id))) : new Set<string>();
        const rows = await resolveAccountantExportAttachments(attachments.value, selected, async row => {
            if (!evolu) throw new Error("local_context_unavailable");
            return (await readVerifiedExpenseAttachment(evolu, company, row, { maxBytes: ACCOUNTANT_EXPORT_MAX_ATTACHMENT_BYTES })).bytes;
        });
        if (generation !== resolutionGeneration || company !== companyId.value) return rows;
        resolvedAttachments.value = rows;
        resolvingAttachments.value = false;
        return rows;
    }
    if (localFirst) watch(() => [attachments.value, expenses.value, companyId.value, range.value, options.value.includeExpenseAttachments,
        originalSourceRows.value, originalChunkRows.value],
        () => { void refreshResolvedAttachments(); }, { immediate: true });
    const exportAttachments = computed(() => resolvingAttachments.value
        ? attachments.value.map(row => isExpenseOriginalReference(row.contentBase64) ? { ...row, contentBase64: null } : row)
        : resolvedAttachments.value);

    /** Local-first only - server mode has no cheap preview (rows live on the server). */
    const plan = computed<AccountantExportPlan | null>(() => {
        if (!localFirst) return null;
        return planAccountantExport({
            range: range.value,
            documents: documents.value,
            expenses: expenses.value,
            attachments: exportAttachments.value,
            companyId: companyId.value,
            includeAttachments: options.value.includeExpenseAttachments,
        });
    });

    const nothingToExport = computed(
        () => plan.value !== null && plan.value.documents === 0 && plan.value.expenses === 0,
    );

    const wantsAnything = computed(
        () =>
            options.value.formats.length > 0
            || options.value.includePdf
            || options.value.includeIsdoc
            || options.value.includeUbl
            || options.value.includeExpenseAttachments,
    );

    function filename(chunk: ExportDateRange, name = companyName.value ?? "company"): string {
        return `accountant-${slug(name)}-${chunk.from}_${chunk.to}.zip`;
    }

    async function prepareLocalBatch(batch: AccountantExportBatch, selection: ExportSelection, attachmentSnapshot: readonly EvoluExpenseAttachmentRow[], signal?: AbortSignal): Promise<{ blob: Blob; filename: string } | null> {
        if (!localDoc) return null;
        assertExportSelection(selection, signal);
        const expenseIds = new Set(batch.expenseIds);
        const expensePayloads = selection.expenses
            .filter((row) => expenseIds.has(String(row.id)))
            .map((row) => buildExpensePayload(row, attachmentSnapshot, selection.options.includeExpenseAttachments).payload);
        if (batch.documentIds.length === 0 && expensePayloads.length === 0) return null;

        const request = await buildAccountantExportEphemeralRequest(localDoc, selection.company, {
            documentIds: batch.documentIds,
            expenses: expensePayloads,
            range: batch.range,
            options: selection.options,
        });
        assertExportSelection(selection, signal);
        if (!request) {
            throw new Error("accountant_export_build_failed");
        }
        const blob = await fetchEphemeralAccountantExport(request.body, request.bridgeCompanyId, { signal });
        assertExportSelection(selection, signal);
        return { blob, filename: filename(batch.range, selection.companyName) };
    }

    async function downloadServer(signal?: AbortSignal): Promise<void> {
        const blob = await invoicingApi.companies.accountantExport(
            companyId.value,
            {
                from: range.value.from,
                to: range.value.to,
                "formats[]": options.value.formats,
                include_pdf: options.value.includePdf ? 1 : 0,
                include_isdoc: options.value.includeIsdoc ? 1 : 0,
                include_ubl: options.value.includeUbl ? 1 : 0,
                include_expense_attachments: options.value.includeExpenseAttachments ? 1 : 0,
            },
            { signal },
        );
        downloadCsvBlob(blob, filename(range.value));
    }

    let abort: AbortController | null = null;

    async function download(): Promise<void> {
        if (downloading.value) return;
        error.value = null;
        downloading.value = true;
        const controller = new AbortController();
        abort = controller;
        try {
            if (!localFirst) {
                progress.value = { done: 0, total: 1 };
                await downloadServer(controller.signal);
                progress.value = { done: 1, total: 1 };
                return;
            }
            const selection = captureExportSelection();
            const attachmentSnapshot = await resolveExportAttachments(selection, controller.signal);
            const batches = planAccountantExport({ range: selection.range, documents: selection.documents,
                expenses: selection.expenses, attachments: attachmentSnapshot, companyId: selection.company,
                includeAttachments: selection.options.includeExpenseAttachments }).batches;
            const prepared: { blob: Blob; filename: string }[] = [];
            progress.value = { done: 0, total: batches.length };
            for (const batch of batches) {
                const file = await prepareLocalBatch(batch, selection, attachmentSnapshot, controller.signal);
                if (file) {
                    prepared.push(file);
                }
                progress.value = { done: progress.value.done + 1, total: batches.length };
            }
            for (const file of prepared) {
                assertExportSelection(selection, controller.signal);
                downloadResponseBlob(file.blob, file.filename);
            }
        } catch (e) {
            if ((e as { name?: string })?.name === "CanceledError" || (e as { name?: string })?.name === "AbortError") {
                progress.value = null;
                return;
            }
            error.value = e instanceof Error ? e.message : String(e);
        } finally {
            downloading.value = false;
            abort = null;
        }
    }

    function cancel(): void {
        abort?.abort();
    }

    return {
        localFirst,
        period,
        options,
        range,
        plan,
        nothingToExport,
        wantsAnything,
        loading: computed(() => loading.value || resolvingAttachments.value),
        loadError,
        downloading,
        progress,
        error,
        download,
        cancel,
        retry: load,
    };
}
