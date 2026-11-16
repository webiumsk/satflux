import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
    acquirePaymentIntentSync,
    canReconcilePaymentIntentOwner,
    createPaymentIntentSynchronizer,
    paymentIntentMatchesOutstanding,
    paymentIntentTransition,
    type PaymentIntentDocumentState,
    type PaymentIntentCompanyState,
} from "@/evolu/paymentIntentSync";
import type { EphemeralPaymentLink } from "@/evolu/ephemeralBridge";

const mocks = vi.hoisted(() => ({ list: vi.fn(), update: vi.fn(), phrase: "synthetic-session", share: undefined as unknown }));
vi.mock("@/evolu/ephemeralBridge", () => ({
    fetchEphemeralPaymentLinks: mocks.list,
    updateEphemeralPaymentLinkState: mocks.update,
}));
vi.mock("@/services/accountSeed", () => ({ getStoredAccountMnemonic: () => mocks.phrase }));
vi.mock("@/services/evoluOwner", () => ({ isTargetEvoluOwner: () => true }));
vi.mock("@/evolu/companyShareRegistry", () => ({
    isCompanyShareRegistryReady: () => true,
    companyShareInfo: () => mocks.share,
}));
vi.mock("@/evolu/client", () => ({ paymentIntentDocumentsQuery: "documents", paymentIntentCompaniesQuery: "companies" }));

const intent = (): EphemeralPaymentLink => ({
    id: "intent", evolu_document_id: "document", version: 3, state: "active",
    store_id: "store", number: "20260001", amount: "100.00", currency: "EUR", btcpay_invoice_id: null,
    payment_method: null, payment_link: "https://example.test/pay/opaque",
});
const document = (): PaymentIntentDocumentState => ({
    id: "document", ownerId: "owner", companyId: "company", isDeleted: null,
    documentType: "invoice", status: "issued", paymentBtcEnabled: 1,
    storeId: "store", number: "20260001", total: "100.00", currency: "EUR", amountPaid: null,
});
const company = (): PaymentIntentCompanyState => ({ id: "company", ownerId: "owner", isDeleted: null });

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>(done => { resolve = done; });
    return { promise, resolve };
}

function fixture() {
    const state = { documents: [document()], companies: [company()] };
    let authenticated = true;
    let ownerValid = true;
    const list = vi.fn(async () => [intent()]);
    const update = vi.fn<Parameters<typeof createPaymentIntentSynchronizer>[0]["update"]>(async () => ({}));
    const sync = createPaymentIntentSynchronizer({
        isCurrent: () => authenticated,
        verifyCurrent: async () => ownerValid,
        refresh: vi.fn(async () => {}), read: () => state,
        canUseOwner: (doc, co) => doc.ownerId === "owner" && co.ownerId === "owner",
        list, update,
    });
    return { state, list, update, sync, logout: () => { authenticated = false; }, ownerChange: () => { ownerValid = false; } };
}

beforeEach(() => { vi.clearAllMocks(); mocks.phrase = "synthetic-session"; mocks.share = undefined; });
afterEach(() => { vi.useRealTimers(); });

describe("minimal payment intent terminal transitions", () => {
    it("accepts only the current private partition or the validated active company owner's shared partition", () => {
        expect(canReconcilePaymentIntentOwner(document(), "owner")).toBe(true);
        expect(canReconcilePaymentIntentOwner({ ...document(), ownerId: "foreign" }, "owner")).toBe(false);
        mocks.share = { ownerId: "shared-owner", status: "active", role: "member" };
        expect(canReconcilePaymentIntentOwner({ ...document(), ownerId: "shared-owner" }, "owner")).toBe(false);
        mocks.share = { ownerId: "shared-owner", status: "active", role: "owner" };
        expect(canReconcilePaymentIntentOwner({ ...document(), ownerId: "shared-owner" }, "owner")).toBe(true);
        expect(canReconcilePaymentIntentOwner(document(), "owner")).toBe(false);
        mocks.share = { ownerId: "shared-owner", status: "revoked", role: "owner" };
        expect(canReconcilePaymentIntentOwner({ ...document(), ownerId: "shared-owner" }, "owner")).toBe(false);
    });
    it.each([
        [{ status: "paid" }, "paid"], [{ status: "cancelled" }, "revoked"], [{ status: "draft" }, "revoked"],
        [{ isDeleted: 1 }, "revoked"], [{ paymentBtcEnabled: 0 }, "revoked"], [{ storeId: null }, "revoked"],
        [{ storeId: "other-store" }, "revoked"], [{ total: "120" }, "revoked"], [{ currency: "USD" }, "revoked"], [{ number: "20260002" }, "revoked"],
        [{ amountPaid: "20.00" }, "revoked"], [{ amountPaid: "100.00" }, "paid"],
    ] as const)("invalidates a registered link for %j", (patch, expected) => {
        expect(paymentIntentTransition(intent(), { ...document(), ...patch }, company())).toBe(expected);
    });

    it("uses exact outstanding decimals and rejects malformed incomplete amounts", () => {
        expect(paymentIntentMatchesOutstanding({ ...intent(), amount: "0.00000003" },
            { ...document(), total: "0.00000004", amountPaid: "0.00000001" })).toBe(true);
        expect(paymentIntentTransition({ ...intent(), amount: "80" }, { ...document(), amountPaid: "20" }, company())).toBeNull();
        for (const total of [null, "NaN", "1e2", "0.000000001"]) {
            expect(paymentIntentTransition(intent(), { ...document(), total }, company())).toBeNull();
        }
    });

    it("never reopens paid/revoked records and honors company tombstones", () => {
        for (const state of ["paid", "revoked"] as const) {
            expect(paymentIntentTransition({ ...intent(), state }, document(), company())).toBeNull();
        }
        expect(paymentIntentTransition(intent(), document(), { ...company(), isDeleted: 1 })).toBe("revoked");
        expect(paymentIntentTransition(intent(), { ...document(), paymentBtcEnabled: null }, company())).toBeNull();
    });
});

describe("registered-link reconciliation", () => {
    it("rebuilds bank/manual payment work from durable rows after a reload and sends only the CAS transition", async () => {
        const f = fixture();
        Object.assign(f.state.documents[0], { status: "paid", amountPaid: "100", customer: "must-never-upload", lines: ["private"] });
        await f.sync.request();
        expect(f.update).toHaveBeenCalledExactlyOnceWith("intent", 3, "paid", expect.any(AbortSignal));
        expect(JSON.stringify(f.update.mock.calls)).not.toContain("must-never-upload");
        f.sync.stop();
        const reloaded = fixture();
        reloaded.state.documents[0].status = "paid";
        await reloaded.sync.request();
        expect(reloaded.update).toHaveBeenCalledOnce();
        reloaded.sync.stop();
    });

    it("does not turn missing relay rows or ambiguous/foreign owners into deletion", async () => {
        const f = fixture();
        f.state.documents = [];
        await f.sync.request();
        f.state.documents = [{ ...document(), ownerId: "foreign", isDeleted: 1 }];
        await f.sync.request();
        f.state.documents = [{ ...document(), isDeleted: 1 }, { ...document(), isDeleted: 1 }];
        await f.sync.request();
        expect(f.update).not.toHaveBeenCalled();
        f.state.documents = [{ ...document(), isDeleted: 1 }];
        await f.sync.request();
        expect(f.update).toHaveBeenCalledWith("intent", 3, "revoked", expect.any(AbortSignal));
        f.sync.stop();
    });

    it.each(["logout", "ownerChange"] as const)("discards a held list after %s", async action => {
        const f = fixture();
        f.state.documents[0].status = "paid";
        const held = deferred<EphemeralPaymentLink[]>();
        f.list.mockImplementationOnce(() => held.promise);
        const running = f.sync.request();
        await vi.waitFor(() => expect(f.list).toHaveBeenCalledOnce());
        f[action]();
        held.resolve([intent()]);
        await running;
        expect(f.update).not.toHaveBeenCalled();
        f.sync.stop();
    });

    it("reads live durable state after a held request rather than a captured issued snapshot", async () => {
        const f = fixture();
        const held = deferred<EphemeralPaymentLink[]>();
        f.list.mockImplementationOnce(() => held.promise);
        const running = f.sync.request();
        await vi.waitFor(() => expect(f.list).toHaveBeenCalledOnce());
        f.state.documents[0].status = "paid";
        held.resolve([intent()]);
        await running;
        expect(f.update).toHaveBeenCalledWith("intent", 3, "paid", expect.any(AbortSignal));
        f.sync.stop();
    });

    it("serializes requests and refreshes the CAS revision after conflict/network failure", async () => {
        vi.useFakeTimers();
        const f = fixture();
        f.state.documents[0].status = "cancelled";
        f.update.mockRejectedValueOnce({ response: { status: 409 } });
        await f.sync.request();
        expect(f.update).toHaveBeenCalledTimes(1);
        f.list.mockResolvedValueOnce([{ ...intent(), version: 4 }]);
        await vi.advanceTimersByTimeAsync(1_000);
        expect(f.update).toHaveBeenLastCalledWith("intent", 4, "revoked", expect.any(AbortSignal));
        f.sync.stop();
        await vi.advanceTimersByTimeAsync(60_000);
        expect(f.update).toHaveBeenCalledTimes(2);
    });

    it("coalesces concurrent notifications and never repeats a terminal transition from an old list", async () => {
        const f = fixture();
        f.state.documents[0].status = "paid";
        const held = deferred<unknown>();
        f.update.mockImplementationOnce(() => held.promise);
        f.list.mockResolvedValueOnce([intent()]).mockResolvedValue([{ ...intent(), state: "paid", version: 4 }]);
        const running = f.sync.request();
        await vi.waitFor(() => expect(f.update).toHaveBeenCalledOnce());
        const concurrent = f.sync.request();
        void f.sync.request();
        expect(f.list).toHaveBeenCalledOnce();
        held.resolve({});
        await Promise.all([running, concurrent]);
        expect(f.list).toHaveBeenCalledTimes(2);
        expect(f.update).toHaveBeenCalledOnce();
        f.sync.stop();
    });

    it("cancels an in-flight update and never continues to the next registered intent", async () => {
        const f = fixture();
        f.state.documents[0].status = "paid";
        f.list.mockResolvedValueOnce([intent(), { ...intent(), id: "second" }]);
        const held = deferred<unknown>();
        f.update.mockImplementationOnce(() => held.promise);
        const running = f.sync.request();
        await vi.waitFor(() => expect(f.update).toHaveBeenCalledOnce());
        const signal = f.update.mock.calls[0][3] as AbortSignal;
        f.sync.stop();
        expect(signal.aborted).toBe(true);
        held.resolve({});
        await running;
        expect(f.update).toHaveBeenCalledOnce();
    });
});

describe("shared shell lifecycle", () => {
    it("deduplicates leases, retries on focus/online, and aborts after the last shell stops", async () => {
        const state = { documents: [{ ...document(), status: "paid" }], companies: [company()] };
        mocks.list.mockResolvedValue([intent()]);
        mocks.update.mockResolvedValue({});
        const callbacks = new Set<() => void>();
        const evolu = {
            appOwner: Promise.resolve({ id: "owner", mnemonic: "synthetic-session" }),
            loadQuery: vi.fn(async (query: keyof typeof state) => state[query]),
            getQueryRows: (query: keyof typeof state) => state[query],
            subscribeQuery: () => (callback: () => void) => {
                const subscription = () => callback();
                callbacks.add(subscription);
                return () => callbacks.delete(subscription);
            },
        };
        const first = acquirePaymentIntentSync(evolu as never, 1, () => true);
        const second = acquirePaymentIntentSync(evolu as never, 1, () => true);
        await vi.waitFor(() => expect(mocks.update).toHaveBeenCalledTimes(1));
        expect(callbacks.size).toBe(2);
        first();
        window.dispatchEvent(new Event("online"));
        await vi.waitFor(() => expect(mocks.update).toHaveBeenCalledTimes(2));
        window.dispatchEvent(new Event("focus"));
        await vi.waitFor(() => expect(mocks.update).toHaveBeenCalledTimes(3));
        const signal = mocks.update.mock.calls[0][3] as AbortSignal;
        second();
        expect(callbacks.size).toBe(0);
        expect(signal.aborted).toBe(true);
        window.dispatchEvent(new Event("online"));
        await Promise.resolve();
        expect(mocks.update).toHaveBeenCalledTimes(3);
    });
});
