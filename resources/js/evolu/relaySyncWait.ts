import type { Evolu } from "@evolu/common/local-first";
import { allCompaniesQuery, allDocumentsQuery } from "./client";
import type { InvoicingLocalSchema } from "./schema";
import { getStoredAccountMnemonic } from "@/services/accountSeed";
import { isTargetEvoluOwner } from "@/services/evoluOwner";
import {
    isEvoluRelayConfigured,
    ensureEvoluRelaySubscription,
    refreshEvoluRelaySubscription,
} from "./evoluRelaySubscription";
import {
    loadInvoicingRelayFingerprint,
    refreshAllInvoicingLocalQueries,
} from "./invoicingRelayFingerprint";

const EVOLU_RELAY_PENDING_KEY = "satflux.evolu.relay_pending.v1";

export type InvoicingRelayOwnerStatus = "ok" | "no_phrase" | "owner_mismatch";

export type PullInvoicingFromRelayResult = {
    ownerStatus: InvoicingRelayOwnerStatus;
    changed: boolean;
    documentCount: number;
    timedOut: boolean;
    /** Build has no VITE_EVOLU_RELAY_URL - sync cannot cross browsers. */
    relayDisabled?: boolean;
    /** Documents synced for this account but under another company row (duplicate companies). */
    syncedElsewhere?: boolean;
    /** New rows synced for this company but another document type (e.g. proforma while on invoice list). */
    syncedOtherDocumentType?: boolean;
};

export function markEvoluRelaySyncPending(): void {
    sessionStorage.setItem(EVOLU_RELAY_PENDING_KEY, String(Date.now()));
}

export function clearEvoluRelaySyncPending(): void {
    sessionStorage.removeItem(EVOLU_RELAY_PENDING_KEY);
}

export function isEvoluRelaySyncPending(): boolean {
    const raw = sessionStorage.getItem(EVOLU_RELAY_PENDING_KEY);
    if (raw == null) {
        return false;
    }
    const started = parseInt(raw, 10);
    if (!Number.isFinite(started)) {
        clearEvoluRelaySyncPending();
        return false;
    }
    if (Date.now() - started > 120_000) {
        clearEvoluRelaySyncPending();
        return false;
    }
    return true;
}

/** @deprecated Use refreshAllInvoicingLocalQueries */
export async function refreshInvoicingLocalQueries(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<void> {
    await refreshAllInvoicingLocalQueries(evolu);
}

export { refreshAllInvoicingLocalQueries };

export async function checkInvoicingRelayOwner(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<InvoicingRelayOwnerStatus> {
    const mnemonic = getStoredAccountMnemonic();
    if (!mnemonic) {
        return "no_phrase";
    }
    const owner = await evolu.appOwner;
    return isTargetEvoluOwner(owner.mnemonic, mnemonic) ? "ok" : "owner_mismatch";
}

export type PullInvoicingFromRelayOptions = {
    timeoutMs?: number;
    pollMs?: number;
    companyId?: string;
    /** Document count for the active list tab in the result message. */
    documentType?: string;
    /** Wait after re-subscribing on the relay before polling (ms). */
    subscriptionWarmupMs?: number;
};

type RelayDocumentRow = {
    id: string;
    companyId?: string | null;
    documentType?: string | null;
};

function filterRelayDocuments(
    docs: RelayDocumentRow[],
    options?: Pick<PullInvoicingFromRelayOptions, "companyId" | "documentType">,
): RelayDocumentRow[] {
    return docs.filter((row) => {
        if (options?.companyId && row.companyId !== options.companyId) {
            return false;
        }
        if (options?.documentType && row.documentType !== options.documentType) {
            return false;
        }
        return true;
    });
}

async function countDocumentsForCompany(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: Pick<PullInvoicingFromRelayOptions, "companyId" | "documentType">,
): Promise<number> {
    const docs = (await evolu.loadQuery(allDocumentsQuery)) as RelayDocumentRow[];
    return filterRelayDocuments(docs, options).length;
}

async function loadDocumentListFingerprint(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: Pick<PullInvoicingFromRelayOptions, "companyId" | "documentType">,
): Promise<string> {
    const docs = (await evolu.loadQuery(allDocumentsQuery)) as RelayDocumentRow[];
    return filterRelayDocuments(docs, options)
        .map((row) => String(row.id))
        .sort()
        .join("|");
}

/**
 * Poll local Evolu until relay merges change any invoicing rows (or timeout).
 * Relay itself syncs the full CRDT database; this waits for merges in SQLite.
 */
export async function pullInvoicingFromRelay(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: PullInvoicingFromRelayOptions,
): Promise<PullInvoicingFromRelayResult> {
    if (!isEvoluRelayConfigured()) {
        return {
            ownerStatus: "ok",
            changed: false,
            documentCount: 0,
            timedOut: false,
            relayDisabled: true,
        };
    }

    const ownerStatus = await checkInvoicingRelayOwner(evolu);
    if (ownerStatus !== "ok") {
        return {
            ownerStatus,
            changed: false,
            documentCount: 0,
            timedOut: false,
        };
    }

    const timeoutMs = options?.timeoutMs ?? 25_000;
    const pollMs = options?.pollMs ?? 600;
    const companyId = options?.companyId;
    const listFilter = {
        companyId,
        documentType: options?.documentType,
    };
    const deadline = Date.now() + timeoutMs;

    await refreshEvoluRelaySubscription(evolu);
    await sleep(options?.subscriptionWarmupMs ?? 1_500);

    const baselineCompanyFingerprint = await loadInvoicingRelayFingerprint(evolu, companyId);
    const baselineListFingerprint = await loadDocumentListFingerprint(evolu, listFilter);
    const baselineGlobalFingerprint = await loadInvoicingRelayFingerprint(evolu);
    let lastFingerprint = baselineCompanyFingerprint;
    let stableAfterChange = 0;

    while (Date.now() < deadline) {
        await sleep(pollMs);
        await refreshAllInvoicingLocalQueries(evolu);
        const companyFingerprint = await loadInvoicingRelayFingerprint(evolu, companyId);

        if (companyFingerprint !== baselineCompanyFingerprint) {
            if (companyFingerprint === lastFingerprint) {
                stableAfterChange += 1;
                if (stableAfterChange >= 2) {
                    return buildPullResult(evolu, {
                        listFilter,
                        baselineCompanyFingerprint,
                        baselineListFingerprint,
                    });
                }
            } else {
                lastFingerprint = companyFingerprint;
                stableAfterChange = 0;
            }
            continue;
        }

        lastFingerprint = companyFingerprint;
        stableAfterChange = 0;
    }

    const finalCompanyFingerprint = await loadInvoicingRelayFingerprint(evolu, companyId);
    const companyChanged = finalCompanyFingerprint !== baselineCompanyFingerprint;

    if (companyChanged) {
        return buildPullResult(evolu, {
            listFilter,
            baselineCompanyFingerprint,
            baselineListFingerprint,
        });
    }

    const finalGlobalFingerprint = await loadInvoicingRelayFingerprint(evolu);
    if (companyId && finalGlobalFingerprint !== baselineGlobalFingerprint) {
        return {
            ownerStatus: "ok",
            changed: true,
            documentCount: await countDocumentsForCompany(evolu, listFilter),
            timedOut: false,
            syncedElsewhere: true,
        };
    }

    return {
        ownerStatus: "ok",
        changed: false,
        documentCount: await countDocumentsForCompany(evolu, listFilter),
        timedOut: true,
    };
}

async function buildPullResult(
    evolu: Evolu<InvoicingLocalSchema>,
    args: {
        listFilter: Pick<PullInvoicingFromRelayOptions, "companyId" | "documentType">;
        baselineCompanyFingerprint: string;
        baselineListFingerprint: string;
    },
): Promise<PullInvoicingFromRelayResult> {
    const companyFingerprint = await loadInvoicingRelayFingerprint(evolu, args.listFilter.companyId);
    const listFingerprint = await loadDocumentListFingerprint(evolu, args.listFilter);
    const companyChanged = companyFingerprint !== args.baselineCompanyFingerprint;
    const listChanged = listFingerprint !== args.baselineListFingerprint;

    return {
        ownerStatus: "ok",
        changed: companyChanged,
        documentCount: await countDocumentsForCompany(evolu, args.listFilter),
        timedOut: false,
        syncedOtherDocumentType:
            companyChanged && !listChanged && Boolean(args.listFilter.documentType),
    };
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

export type WaitForInvoicingRelaySyncOptions = {
    timeoutMs?: number;
    pollMs?: number;
    stablePolls?: number;
    minWaitMs?: number;
};

/** After phrase restore: wait until full invoicing fingerprint stabilizes. */
export async function waitForInvoicingRelaySync(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: WaitForInvoicingRelaySyncOptions,
): Promise<boolean> {
    const timeoutMs = options?.timeoutMs ?? 45_000;
    const pollMs = options?.pollMs ?? 750;
    const stablePolls = options?.stablePolls ?? 4;
    const minWaitMs = options?.minWaitMs ?? 5_000;
    const deadline = Date.now() + timeoutMs;
    const startedAt = Date.now();

    await refreshEvoluRelaySubscription(evolu);

    let lastFingerprint = "";
    let stable = 0;

    while (Date.now() < deadline) {
        await refreshAllInvoicingLocalQueries(evolu);
        const fingerprint = await loadInvoicingRelayFingerprint(evolu);

        if (fingerprint === lastFingerprint) {
            stable += 1;
            const elapsed = Date.now() - startedAt;
            if (stable >= stablePolls && elapsed >= minWaitMs) {
                clearEvoluRelaySyncPending();
                const companies = await evolu.loadQuery(allCompaniesQuery);
                return companies.length > 0;
            }
        } else {
            lastFingerprint = fingerprint;
            stable = 0;
        }

        await sleep(pollMs);
    }

    clearEvoluRelaySyncPending();
    const companies = await evolu.loadQuery(allCompaniesQuery);
    return companies.length > 0;
}

/** @deprecated Use waitForInvoicingRelaySync */
export async function waitForInvoicingRelayData(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: WaitForInvoicingRelaySyncOptions,
): Promise<boolean> {
    return waitForInvoicingRelaySync(evolu, options);
}

export type WaitForInvoicingDataSettledOptions = {
    timeoutMs?: number;
    pollMs?: number;
    stablePolls?: number;
    minWaitMs?: number;
};

/** Wait until full invoicing data stops changing (post-import / post-mutation). */
export async function waitForInvoicingDataSettled(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: WaitForInvoicingDataSettledOptions,
): Promise<void> {
    const timeoutMs = options?.timeoutMs ?? 12_000;
    const pollMs = options?.pollMs ?? 500;
    const stablePolls = options?.stablePolls ?? 3;
    const minWaitMs = options?.minWaitMs ?? 1_500;
    const deadline = Date.now() + timeoutMs;
    const startedAt = Date.now();

    let lastFingerprint = "";
    let stable = 0;

    while (Date.now() < deadline) {
        await refreshAllInvoicingLocalQueries(evolu);
        const fingerprint = await loadInvoicingRelayFingerprint(evolu);

        if (fingerprint === lastFingerprint) {
            stable += 1;
            const elapsed = Date.now() - startedAt;
            if (stable >= stablePolls && elapsed >= minWaitMs) {
                return;
            }
        } else {
            lastFingerprint = fingerprint;
            stable = 0;
        }

        await sleep(pollMs);
    }
}

export type PushInvoicingToRelayOptions = {
    timeoutMs?: number;
    companyId?: string;
};

export type PushInvoicingToRelayResult = {
    ownerStatus: InvoicingRelayOwnerStatus;
    relayDisabled?: boolean;
    upserted: number;
    ok: boolean;
};

/**
 * Re-upsert local invoicing rows so Evolu sends CRDT updates to the relay.
 * Use on the device where data was created before relay worked (legacy local-only rows).
 */
export async function pushInvoicingToRelay(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: PushInvoicingToRelayOptions,
): Promise<PushInvoicingToRelayResult> {
    if (!isEvoluRelayConfigured()) {
        return {
            ownerStatus: "ok",
            relayDisabled: true,
            upserted: 0,
            ok: false,
        };
    }

    const ownerStatus = await checkInvoicingRelayOwner(evolu);
    if (ownerStatus !== "ok") {
        return { ownerStatus, upserted: 0, ok: false };
    }

    const { filterInvoicingSnapshotByCompany } = await import("./invoicingRelayFingerprintCore");
    const { restoreInvoicingSnapshotDetailedAsync, snapshotInvoicingData } = await import(
        "./invoicingSnapshot"
    );

    await refreshEvoluRelaySubscription(evolu);
    await sleep(2_000);

    const snapshot = await snapshotInvoicingData(evolu);
    const scoped = options?.companyId
        ? filterInvoicingSnapshotByCompany(snapshot, options.companyId)
        : snapshot;

    const report = await restoreInvoicingSnapshotDetailedAsync(evolu, scoped);
    await waitForInvoicingDataSettled(evolu, {
        timeoutMs: options?.timeoutMs ?? 20_000,
        minWaitMs: 3_000,
    });

    return {
        ownerStatus: "ok",
        upserted: report.upserted,
        ok: report.upserted > 0 || report.failed.length === 0,
    };
}
