import type { Evolu } from "@evolu/common/local-first";
import { allCompaniesQuery, allDocumentsQuery, allNumberSeriesQuery } from "./client";
import type { InvoicingLocalSchema } from "./schema";
import { getStoredAccountMnemonic } from "@/services/accountSeed";
import { isTargetEvoluOwner } from "@/services/evoluOwner";

const EVOLU_RELAY_PENDING_KEY = "satflux.evolu.relay_pending.v1";

export type InvoicingRelayOwnerStatus = "ok" | "no_phrase" | "owner_mismatch";

export type PullInvoicingFromRelayResult = {
    ownerStatus: InvoicingRelayOwnerStatus;
    changed: boolean;
    documentCount: number;
    timedOut: boolean;
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
    // Stale flag from an interrupted restore - do not block invoicing forever.
    if (Date.now() - started > 120_000) {
        clearEvoluRelaySyncPending();
        return false;
    }
    return true;
}

/** Reload local Evolu queries (picks up relay merges already applied in SQLite). */
export async function refreshInvoicingLocalQueries(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<void> {
    await Promise.all([
        evolu.loadQuery(allCompaniesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allNumberSeriesQuery),
    ]);
}

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

async function countDocumentsForCompany(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId?: string,
): Promise<number> {
    const docs = await evolu.loadQuery(allDocumentsQuery);
    if (!companyId) {
        return docs.length;
    }
    return docs.filter((row) => row.companyId === companyId).length;
}

export type PullInvoicingFromRelayOptions = {
    timeoutMs?: number;
    pollMs?: number;
    companyId?: string;
};

/**
 * Poll local Evolu until relay merges change document rows (or timeout).
 * loadQuery alone does not fetch from the network - this waits for background sync.
 */
export async function pullInvoicingFromRelay(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: PullInvoicingFromRelayOptions,
): Promise<PullInvoicingFromRelayResult> {
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
    const deadline = Date.now() + timeoutMs;

    const baseline = await countDocumentsForCompany(evolu, companyId);
    let lastCount = baseline;
    let stableAfterChange = 0;

    while (Date.now() < deadline) {
        await sleep(pollMs);
        await refreshInvoicingLocalQueries(evolu);
        const count = await countDocumentsForCompany(evolu, companyId);

        if (count !== baseline) {
            if (count === lastCount) {
                stableAfterChange += 1;
                if (stableAfterChange >= 2) {
                    return {
                        ownerStatus: "ok",
                        changed: true,
                        documentCount: count,
                        timedOut: false,
                    };
                }
            } else {
                lastCount = count;
                stableAfterChange = 0;
            }
            continue;
        }

        lastCount = count;
        stableAfterChange = 0;
    }

    const finalCount = await countDocumentsForCompany(evolu, companyId);
    return {
        ownerStatus: "ok",
        changed: finalCount !== baseline,
        documentCount: finalCount,
        timedOut: finalCount === baseline,
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
    /** Consecutive polls with unchanged company count before treating sync as settled. */
    stablePolls?: number;
    /** Minimum wait after restore so relay can push remote rows even when local DB is non-empty. */
    minWaitMs?: number;
};

/**
 * Poll local Evolu DB until company count stabilizes (relay merge finished) or timeout.
 * Used after phrase restore / legacy owner migration so all browsers converge before UI actions.
 */
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

    let lastCount = -1;
    let stable = 0;

    while (Date.now() < deadline) {
        const companies = await evolu.loadQuery(allCompaniesQuery);
        const count = companies.length;

        if (count === lastCount) {
            stable += 1;
            const elapsed = Date.now() - startedAt;
            if (stable >= stablePolls && elapsed >= minWaitMs) {
                clearEvoluRelaySyncPending();
                return count > 0;
            }
        } else {
            lastCount = count;
            stable = 0;
        }

        await sleep(pollMs);
    }

    clearEvoluRelaySyncPending();
    return lastCount > 0;
}

/** @deprecated Use waitForInvoicingRelaySync - kept for imports that expect the old name. */
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

/**
 * Poll document count until stable so relay merges finish before issue/list actions.
 */
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

    let lastCount = -1;
    let stable = 0;

    while (Date.now() < deadline) {
        const documents = await evolu.loadQuery(allDocumentsQuery);
        const count = documents.length;

        if (count === lastCount) {
            stable += 1;
            const elapsed = Date.now() - startedAt;
            if (stable >= stablePolls && elapsed >= minWaitMs) {
                return;
            }
        } else {
            lastCount = count;
            stable = 0;
        }

        await sleep(pollMs);
    }
}
