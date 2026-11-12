import { createIdFromString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import { invoicingApi } from "@/services/api";
import { sha256Hex } from "@/utils/sha256";
import { allCompaniesDetailQuery, allExpensesQuery, allExpenseAttachmentsQuery } from "./client";
import { insertLocalExpense, type ExpenseSavePayload } from "./expenseCrud";
import {
    insertLocalExpenseAttachmentFromBase64,
    LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES,
    type EvoluExpenseAttachmentRow,
} from "./expenseAttachmentCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { ExpenseAttachmentId, ExpenseId, type CompanyId, type InvoicingLocalSchema } from "./schema";
import { toAppRows } from "./queryLoad";
import { withExpenseReconciliation, currentExpenseTarget, checkpointExpenseMutations, assertExpenseCompanyOwner, expenseReconciliationScope, type ExpenseReconciliationScope } from "./expenseReconciliation";

/**
 * Received e-invoices (Peppol via the CPDS) for a LOCAL-FIRST company. The
 * server parks each document on the bridge company as an inbox item
 * (EfakturaInboundInboxService); this module turns it into an Evolu expense
 * with the UBL attached and clears the server copy. Mirrors
 * integrationInboxImport.ts for WooCommerce orders.
 */

export type EfakturaInboxEntry = {
    inbox_id: string;
    evolu_expense_id: string | null;
    external_document_id: string;
    inbox_status: string;
    created_at: string | null;
    original_sha256?: string | null;
    draft: Record<string, unknown> | null;
    summary: {
        supplier_name: string | null;
        external_number: string | null;
        total: string | null;
        currency: string | null;
    };
};

export type EfakturaInboxEntryDetail = EfakturaInboxEntry & { ubl: string | null; original_sha256: string | null };

export type EfakturaInboxImportResult =
    | { ok: true; expenseId: ExpenseId; attachmentSkipped: false; alreadyImported: boolean }
    | { ok: false; error: "invalid_entry" | "expense_insert_failed" | "original_unavailable" | "original_too_large" | "original_storage_failed" };

/** Same inbox item -> same Evolu expense id on every device. */
export function stableExpenseIdFromInboxUuid(inboxEvoluUuid: string | null | undefined): ExpenseId | null {
    const normalized = String(inboxEvoluUuid ?? "").trim().toLowerCase();
    if (!normalized) {
        return null;
    }
    const parsed = ExpenseId.from(createIdFromString(`satflux.efaktura-expense.v1.${normalized}`));
    return parsed.ok ? parsed.value : null;
}

/** The UBL attachment of that expense - one row per inbox item on every device. */
export function stableAttachmentIdFromInboxUuid(inboxEvoluUuid: string | null | undefined): ExpenseAttachmentId | null {
    const normalized = String(inboxEvoluUuid ?? "").trim().toLowerCase();
    if (!normalized) {
        return null;
    }
    const parsed = ExpenseAttachmentId.from(createIdFromString(`satflux.efaktura-expense-attachment.v1.${normalized}`));
    return parsed.ok ? parsed.value : null;
}

/** Server draft (UblExpenseDraftParser output) -> local expense payload. */
export function draftToExpensePayload(draft: Record<string, unknown> | null, entry: EfakturaInboxEntry): ExpenseSavePayload | null {
    const text = (value: unknown): string | null => {
        const s = String(value ?? "").trim();
        return s === "" ? null : s;
    };
    const issueDate = text(draft?.issue_date) ?? (entry.created_at ? entry.created_at.slice(0, 10) : null);
    if (!issueDate) {
        return null;
    }
    const total = text(draft?.total) ?? entry.summary.total;
    const currency = (text(draft?.currency) ?? entry.summary.currency ?? "EUR").toUpperCase().slice(0, 3);

    return {
        title: text(draft?.title) ?? entry.summary.supplier_name ?? "Prijatá e-faktúra",
        external_number: text(draft?.external_number) ?? entry.summary.external_number,
        variable_symbol: text(draft?.variable_symbol),
        issue_date: issueDate,
        delivery_date: text(draft?.delivery_date) ?? issueDate,
        due_date: text(draft?.due_date),
        total: total ?? "0",
        currency,
        internal_note: text(draft?.internal_note) ?? "Importované z Peppol (SAPI-SK).",
    };
}

export async function fetchEfakturaInbox(bridgeCompanyId: string): Promise<EfakturaInboxEntry[]> {
    return invoicingApi.efaktura.inboxList<EfakturaInboxEntry>(bridgeCompanyId);
}

export async function fetchEfakturaInboxDetail(bridgeCompanyId: string, inboxId: string): Promise<EfakturaInboxEntryDetail> {
    return invoicingApi.efaktura.inboxDetail<EfakturaInboxEntryDetail>(bridgeCompanyId, inboxId);
}

export async function markEfakturaInboxImported(bridgeCompanyId: string, inboxId: string, originalSha256: string): Promise<void> {
    await invoicingApi.efaktura.inboxImported(bridgeCompanyId, inboxId, originalSha256);
}

export async function dismissEfakturaInboxItem(bridgeCompanyId: string, inboxId: string): Promise<void> {
    await invoicingApi.efaktura.inboxDismiss(bridgeCompanyId, inboxId);
}

/** An expense alone is a partial import. Resolve only after reading back its exact original attachment. */
export async function reconcileEfakturaInboxWithLocalExpenses(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: string,
    bridgeCompanyId: string,
    entries: EfakturaInboxEntry[],
): Promise<EfakturaInboxEntry[]> {
    const admitted = await withExpenseReconciliation(evolu, async (scope) => ({
        context: await companyImportContext(scope, companyId),
        candidates: entries.filter((entry) => findLocalExpenseForInboxEntry(scope.context.expenses, companyId, entry)),
    }));
    const remaining: EfakturaInboxEntry[] = [];
    for (const entry of entries) {
        if (!admitted.candidates.includes(entry)) { remaining.push(entry); continue; }
        try {
            // No financial lock is retained while waiting for the bridge.
            const detail = entry.original_sha256 ? entry : await fetchEfakturaInboxDetail(bridgeCompanyId, entry.inbox_id);
            const digest = normalizeOriginalDigest(detail.original_sha256);
            const verify = () => withExpenseReconciliation(evolu, async (scope) => {
                await assertCompanyImportContext(scope, companyId, admitted.context);
                return digest !== null && await hasVerifiedOriginal(scope.evolu, companyId, entry, digest);
            }, undefined, ["allExpenseAttachmentsQuery"]);
            if (!await verify()) { remaining.push(entry); continue; }
            await markEfakturaInboxImported(bridgeCompanyId, entry.inbox_id, digest!);
            // A concurrent owner/proof change must keep this item visible.
            if (!await verify()) remaining.push(entry);
        } catch {
            // Failure is not evidence of successful import. Keep the item visible for retry.
            remaining.push(entry);
        }
    }
    return remaining;
}

export function findLocalExpenseForInboxEntry(
    rows: readonly EvoluExpenseRow[],
    companyId: string,
    entry: Pick<EfakturaInboxEntry, "evolu_expense_id">,
): EvoluExpenseRow | null {
    const stableId = stableExpenseIdFromInboxUuid(entry.evolu_expense_id);
    if (!stableId) {
        return null;
    }
    return rows.find((row) => row.id === stableId && String(row.companyId) === companyId) ?? null;
}

function normalizeOriginalDigest(value: unknown): string | null {
    return typeof value === "string" && /^[a-f0-9]{64}$/.test(value) ? value : null;
}

type CompanyImportContext = { ownerId: string; fingerprint: string };

/** Read identity and connection evidence only while holding a fresh company lease. */
async function companyImportContext(scope: ExpenseReconciliationScope, companyId: string): Promise<CompanyImportContext> {
    assertExpenseCompanyOwner(scope, companyId);
    const rows = await scope.evolu.loadQuery(allCompaniesDetailQuery);
    const matches = rows.filter((row) => row.id === companyId);
    const row = matches[0];
    const ownerId = row && Reflect.get(row, "ownerId");
    if (matches.length !== 1 || typeof ownerId !== "string" || ownerId === "") throw new Error("expense_reconciliation_owner_unavailable");
    const settings = typeof row.appSettingsJson === "string" ? JSON.parse(row.appSettingsJson) as Record<string, unknown> : {};
    const connection = ["efaktura_enabled", "efaktura_inbound_enabled", "efaktura_sapi_base_url",
        "efaktura_sapi_client_id", "efaktura_sapi_client_secret", "efaktura_peppol_participant_id"]
        .map((key) => settings[key] ?? null);
    const fingerprint = await sha256Hex(JSON.stringify([
        row.legalName, row.registrationNumber, row.taxId, row.jurisdiction, row.country, connection,
    ]));
    if (fingerprint === null) throw new Error("expense_reconciliation_state_changed");
    return { ownerId, fingerprint };
}

async function assertCompanyImportContext(scope: ExpenseReconciliationScope, companyId: string, expected: CompanyImportContext): Promise<void> {
    assertExpenseCompanyOwner(scope, companyId, expected.ownerId);
    if ((await companyImportContext(scope, companyId)).fingerprint !== expected.fingerprint) throw new Error("expense_reconciliation_state_changed");
}

/** A fresh worker snapshot must contain a live same-company expense and the exact original bytes. */
async function hasVerifiedOriginal(
    evolu: Evolu<InvoicingLocalSchema>, companyId: string, entry: EfakturaInboxEntry, digest: string,
): Promise<boolean> {
    const rows = toAppRows<EvoluExpenseRow>(await evolu.loadQuery(allExpensesQuery));
    const expense = findLocalExpenseForInboxEntry(rows, companyId, entry);
    if (!expense) return false;
    const target = currentExpenseTarget(expenseReconciliationScope(evolu), expense.id);
    const ownerId = target && Reflect.get(target, "ownerId");
    if (typeof ownerId !== "string" || ownerId === "") return false;
    const id = stableAttachmentIdFromInboxUuid(entry.evolu_expense_id);
    const attachments = toAppRows<EvoluExpenseAttachmentRow>(await evolu.loadQuery(allExpenseAttachmentsQuery));
    const matches = attachments.filter((row) => row.id === id && row.expenseId === expense.id
        && Reflect.get(row, "ownerId") === ownerId);
    if (matches.length !== 1 || !matches[0].contentBase64) return false;
    try {
        const binary = atob(matches[0].contentBase64);
        if (binary.length > LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES) return false;
        const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
        const original = new TextDecoder("utf-8", { fatal: true, ignoreBOM: true }).decode(bytes);
        return await sha256Hex(original) === digest;
    } catch {
        return false;
    }
}

/** Complete or repair a stable expense, then prove its original survived the worker before resolving custody. */
export async function importEfakturaInboxEntry(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: string,
    bridgeCompanyId: string,
    entry: EfakturaInboxEntry,
): Promise<EfakturaInboxImportResult> {
    const stableId = stableExpenseIdFromInboxUuid(entry.evolu_expense_id);
    const attachmentId = stableAttachmentIdFromInboxUuid(entry.evolu_expense_id);
    if (!stableId || !attachmentId) {
        return { ok: false, error: "invalid_entry" };
    }
    const admitted = await withExpenseReconciliation(evolu, async (scope) => {
        const context = await companyImportContext(scope, companyId);
        const target = currentExpenseTarget(scope, stableId);
        return { context, invalid: Boolean(target && (target.isDeleted === 1 || target.companyId !== companyId)) };
    });
    if (admitted.invalid) return { ok: false, error: "expense_insert_failed" };
    const detail = await fetchEfakturaInboxDetail(bridgeCompanyId, entry.inbox_id);
    const digest = normalizeOriginalDigest(detail.original_sha256);
    if (!digest) return { ok: false, error: "original_unavailable" };
    const result = await withExpenseReconciliation<EfakturaInboxImportResult>(evolu, async (scope) => {
        await assertCompanyImportContext(scope, companyId, admitted.context);
        const client = scope.evolu;
        const target = currentExpenseTarget(scope, stableId);
        if (target && (target.isDeleted === 1 || target.companyId !== companyId)) return { ok: false as const, error: "expense_insert_failed" };
        const existing = findLocalExpenseForInboxEntry(scope.context.expenses, companyId, entry);
        if (!await hasVerifiedOriginal(client, companyId, entry, digest)) {
            const ubl = detail.ubl ?? "";
            if (!ubl || await sha256Hex(ubl) !== digest) return { ok: false, error: "original_unavailable" };
            if (new TextEncoder().encode(ubl).length > LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES) {
                return { ok: false, error: "original_too_large" };
            }
            if (!existing) {
                const payload = draftToExpensePayload(detail.draft ?? entry.draft, entry);
                if (!payload) return { ok: false, error: "invalid_entry" };
                const inserted = await insertLocalExpense(client, companyId as CompanyId, payload, scope.context.expenses, { id: stableId });
                if (!inserted.ok) return { ok: false, error: "expense_insert_failed" };
            }
            const expenseTarget = currentExpenseTarget(scope, stableId);
            const ownerId = expenseTarget && Reflect.get(expenseTarget, "ownerId");
            const attachments = toAppRows<EvoluExpenseAttachmentRow>(await client.loadQuery(allExpenseAttachmentsQuery));
            if (typeof ownerId !== "string" || attachments.some((row) => row.id === attachmentId
                && (row.expenseId !== stableId || Reflect.get(row, "ownerId") !== ownerId))) {
                return { ok: false, error: "original_storage_failed" };
            }
            const attachment = insertLocalExpenseAttachmentFromBase64(client, stableId, {
                filename: `efaktura-${sanitizeFilenamePart(entry.external_document_id)}.xml`,
                mimeType: "application/xml", contentBase64: utf8ToBase64(ubl),
            }, { id: attachmentId });
            if (!attachment.ok) return { ok: false, error: "original_storage_failed" };
            await checkpointExpenseMutations(client);
            if (!await hasVerifiedOriginal(client, companyId, entry, digest)) return { ok: false, error: "original_storage_failed" };
        }
        return { ok: true, expenseId: stableId, attachmentSkipped: false, alreadyImported: Boolean(existing) };
    }, undefined, ["allExpenseAttachmentsQuery"]);
    if (!result.ok) return result;
    // The bridge wait never holds the origin's financial lock or a scoped facade.
    await markEfakturaInboxImported(bridgeCompanyId, entry.inbox_id, digest);
    const stillVerified = await withExpenseReconciliation(evolu, async (scope) => {
        await assertCompanyImportContext(scope, companyId, admitted.context);
        return hasVerifiedOriginal(scope.evolu, companyId, entry, digest);
    }, undefined, ["allExpenseAttachmentsQuery"]);
    return stillVerified ? result : { ok: false, error: "original_storage_failed" };
}

export function utf8ToBase64(text: string): string {
    const bytes = new TextEncoder().encode(text);
    let binary = "";
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}

function sanitizeFilenamePart(value: string): string {
    return value.replace(/[^A-Za-z0-9._-]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80) || "document";
}
