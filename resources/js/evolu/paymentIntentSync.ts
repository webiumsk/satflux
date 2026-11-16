import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import {
    companyShareInfo,
    isCompanyShareRegistryReady,
} from "./companyShareRegistry";
import {
    fetchEphemeralPaymentLinks,
    updateEphemeralPaymentLinkState,
    type EphemeralPaymentLink,
} from "./ephemeralBridge";
import { getStoredAccountMnemonic } from "@/services/accountSeed";
import { isTargetEvoluOwner } from "@/services/evoluOwner";
import { toAppRows } from "./queryLoad";

export type PaymentIntentDocumentState = {
    id: string;
    ownerId: string;
    companyId: string;
    isDeleted: 0 | 1 | null;
    documentType: string;
    status: string;
    paymentBtcEnabled: 0 | 1 | null;
    storeId: string | null;
    number: string | null;
    total: string | null;
    currency: string | null;
    amountPaid: string | null;
};

export type PaymentIntentCompanyState = { id: string; ownerId: string; isDeleted: 0 | 1 | null };

export function canReconcilePaymentIntentOwner(document: { companyId: string; ownerId?: unknown }, appOwnerId: string): boolean {
    if (!isCompanyShareRegistryReady()) return false;
    const share = companyShareInfo(document.companyId);
    if (share) return share.status === "active" && share.role === "owner" && share.ownerId === document.ownerId;
    return document.ownerId === appOwnerId;
}
type LocalState = {
    documents: readonly PaymentIntentDocumentState[];
    companies: readonly PaymentIntentCompanyState[];
};

// Exact decimal comparison also works for BTC-denominated documents. Do not
// round floating point amounts when deciding whether an old link is payable.
function decimalUnits(value: string | null): bigint | null {
    if (typeof value !== "string" || value.length > 64) return null;
    const match = /^(\d+)(?:\.(\d+))?$/.exec(value.trim());
    if (!match) return null;
    const fraction = match[2] ?? "";
    if (fraction.slice(8).replace(/0/g, "")) return null;
    return BigInt(match[1] + fraction.slice(0, 8).padEnd(8, "0"));
}

export function paymentIntentMatchesOutstanding(
    intent: EphemeralPaymentLink,
    document: Pick<PaymentIntentDocumentState, "storeId" | "number" | "currency" | "total" | "amountPaid">,
): boolean {
    const total = decimalUnits(document.total);
    const paid = decimalUnits(document.amountPaid ?? "0");
    const amount = decimalUnits(intent.amount);
    return total !== null && paid !== null && amount !== null && total > paid
        && total - paid === amount && document.storeId === intent.store_id
        && document.number === intent.number
        && document.currency?.toUpperCase() === intent.currency.toUpperCase();
}

/** No registration/reactivation here: only invalidate an already public intent. */
export function paymentIntentTransition(
    intent: EphemeralPaymentLink,
    document: PaymentIntentDocumentState,
    company: PaymentIntentCompanyState,
): "paid" | "revoked" | null {
    if (intent.state === "paid" || intent.state === "revoked") return null;
    if (document.isDeleted === 1 || company.isDeleted === 1) return "revoked";
    if (document.status === "cancelled" || document.status === "draft") return "revoked";
    if (document.status === "paid") return "paid";
    if (document.status !== "issued") return null;
    if (document.paymentBtcEnabled === 0 || document.storeId === null) return "revoked";
    // Missing CRDT fields are not a revocation instruction. A later query or
    // retry will reconcile the complete row without guessing from absence.
    if (document.paymentBtcEnabled !== 1 || !document.storeId || !document.currency) return null;
    if (!document.number) return null;
    const total = decimalUnits(document.total);
    const paid = decimalUnits(document.amountPaid ?? "0");
    const amount = decimalUnits(intent.amount);
    if (total === null || paid === null || amount === null) return null;
    const due = total > paid ? total - paid : 0n;
    if (due === 0n) return "paid";
    if (document.documentType !== "invoice" && document.documentType !== "proforma") return "revoked";
    return document.storeId !== intent.store_id
        || document.number !== intent.number
        || document.currency.toUpperCase() !== intent.currency.toUpperCase()
        || due !== amount ? "revoked" : null;
}

type SynchronizerDependencies = {
    isCurrent: () => boolean;
    verifyCurrent?: () => Promise<boolean>;
    refresh: () => Promise<void>;
    read: () => LocalState;
    canUseOwner: (document: PaymentIntentDocumentState, company: PaymentIntentCompanyState) => boolean;
    list: (signal: AbortSignal) => Promise<EphemeralPaymentLink[]>;
    update: (id: string, version: number, state: "paid" | "revoked", signal: AbortSignal) => Promise<unknown>;
};

/** Serialized CAS reconciliation; durable rows + server list rebuild work after reload. */
export function createPaymentIntentSynchronizer(deps: SynchronizerDependencies) {
    const abort = new AbortController();
    let running: Promise<void> | null = null;
    let pending = false;
    let stopped = false;
    let retry: ReturnType<typeof setTimeout> | undefined;
    let retryDelay = 1_000;
    const current = () => !stopped && !abort.signal.aborted && deps.isCurrent();
    const verified = async () => current() && (!deps.verifyCurrent || await deps.verifyCurrent()) && current();

    function transition(intent: EphemeralPaymentLink) {
        const state = deps.read();
        const documents = state.documents.filter(row => row.id === intent.evolu_document_id);
        const eligible = documents.flatMap(document => {
            const companies = state.companies.filter(company => company.id === document.companyId
                && company.ownerId === document.ownerId && deps.canUseOwner(document, company));
            return companies.length === 1 ? [{ document, company: companies[0] }] : [];
        });
        // An absent, ambiguous or not-yet-synchronized row is not deletion.
        if (eligible.length !== 1) return null;
        return paymentIntentTransition(intent, eligible[0].document, eligible[0].company);
    }

    async function drain(): Promise<void> {
        while (pending && current()) {
            pending = false;
            await deps.refresh();
            if (!await verified()) return;
            const intents = await deps.list(abort.signal);
            if (!await verified()) return;
            for (const intent of intents) {
                if (!current()) return;
                const next = transition(intent);
                if (!next) continue;
                // Read again immediately before the mutation. Requests carry
                // the server version and cannot undo a newer terminal state.
                if (!await verified() || transition(intent) !== next) continue;
                await deps.update(intent.id, intent.version, next, abort.signal);
                if (!await verified()) return;
            }
        }
    }

    function request(): Promise<void> {
        if (!current()) return Promise.resolve();
        pending = true;
        if (running) return running;
        if (retry) { clearTimeout(retry); retry = undefined; }
        running = drain().then(() => { retryDelay = 1_000; }).catch(() => {
            if (!current()) return;
            pending = true;
            retry = setTimeout(() => { retry = undefined; void request(); }, retryDelay);
            retryDelay = Math.min(retryDelay * 2, 30_000);
        }).finally(() => {
            running = null;
            if (pending && !retry && current()) void request();
        });
        return running;
    }

    function stop(): void {
        stopped = true;
        pending = false;
        abort.abort();
        if (retry) clearTimeout(retry);
    }
    return { request, stop };
}

type Lease = { isCurrent: () => boolean };
let active: {
    evolu: Evolu<InvoicingLocalSchema>;
    accountId: number;
    leases: Set<Lease>;
    stop: () => void;
} | null = null;

/** One authenticated loop shared by the layout and dashboard shell. */
export function acquirePaymentIntentSync(
    evolu: Evolu<InvoicingLocalSchema>,
    accountId: number,
    isCurrent: () => boolean,
): () => void {
    const lease = { isCurrent };
    if (!active || active.evolu !== evolu || active.accountId !== accountId) {
        active?.stop();
        const leases = new Set<Lease>();
        let stopped = false;
        let stopLoop = () => {};
        const session = { evolu, accountId, leases, stop: () => { stopped = true; stopLoop(); } };
        active = session;
        void (async () => {
            const owner = await evolu.appOwner;
            const phrase = getStoredAccountMnemonic();
            const valid = () => !stopped && active === session && [...leases].some(item => item.isCurrent())
                && getStoredAccountMnemonic() === phrase;
            if (!valid() || !phrase || !isTargetEvoluOwner(owner.mnemonic, phrase)) return;
            const { paymentIntentDocumentsQuery, paymentIntentCompaniesQuery } = await import("./client");
            if (!valid()) return;
            const sync = createPaymentIntentSynchronizer({
                isCurrent: valid,
                verifyCurrent: async () => (await evolu.appOwner).id === owner.id,
                refresh: async () => {
                    const currentOwner = await evolu.appOwner;
                    const currentPhrase = getStoredAccountMnemonic();
                    if (!valid() || currentOwner.id !== owner.id || !currentPhrase
                        || !isTargetEvoluOwner(currentOwner.mnemonic, currentPhrase)) throw new Error("payment_intent_owner_changed");
                    await Promise.all([
                        evolu.loadQuery(paymentIntentDocumentsQuery),
                        evolu.loadQuery(paymentIntentCompaniesQuery),
                    ]);
                },
                read: () => ({
                    documents: toAppRows<PaymentIntentDocumentState>(evolu.getQueryRows(paymentIntentDocumentsQuery)),
                    companies: toAppRows<PaymentIntentCompanyState>(evolu.getQueryRows(paymentIntentCompaniesQuery)),
                }),
                canUseOwner: (document) => {
                    return canReconcilePaymentIntentOwner(document, owner.id);
                },
                list: fetchEphemeralPaymentLinks,
                update: updateEphemeralPaymentLinkState,
            });
            const request = () => { if (valid()) void sync.request(); };
            const unsubscribeDocuments = evolu.subscribeQuery(paymentIntentDocumentsQuery)(request);
            const unsubscribeCompanies = evolu.subscribeQuery(paymentIntentCompaniesQuery)(request);
            const timer = setInterval(request, 30_000);
            window.addEventListener("online", request);
            window.addEventListener("focus", request);
            stopLoop = () => {
                sync.stop();
                unsubscribeDocuments();
                unsubscribeCompanies();
                clearInterval(timer);
                window.removeEventListener("online", request);
                window.removeEventListener("focus", request);
            };
            if (!valid()) { stopLoop(); return; }
            request();
        })().catch(() => { /* Bootstrap or storage unavailable; no payment mutation. */ });
    }
    const session = active;
    session.leases.add(lease);
    return () => {
        session.leases.delete(lease);
        if (session.leases.size === 0) {
            session.stop();
            if (active === session) active = null;
        }
    };
}
