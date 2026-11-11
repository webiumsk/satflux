import { afterEach, expect, it, vi } from "vitest";

vi.mock("@/evolu/client", () => ({}));
vi.mock("@/evolu/contactCrud", () => ({
    insertLocalContactFromForm: vi.fn(),
    updateLocalContactFromForm: vi.fn(),
}));

import { assertMigrationSchemaReady } from "@/evolu/migrationSchema";
import { InvoicingLocalSchema } from "@/evolu/schema";
import { awaitWorkerOutcome, serializeBankEmailReconciliation, BANK_RECONCILIATION_TIMEOUT_MS } from "@/evolu/bankReconciliation";
import { ContactImportWriteError, resolveLocalContact } from "@/evolu/documentImportLocal";
import { insertLocalContactFromForm, updateLocalContactFromForm } from "@/evolu/contactCrud";

afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
});

const columns = ["migrationSource", "migrationChunk", "document"].flatMap(tableName =>
    ["ownerId", ...Object.keys(InvoicingLocalSchema[tableName as keyof typeof InvoicingLocalSchema])]
        .map(name => ({ tableName, name })),
);

it("requires both migration tables and the document columns on the current worker", async () => {
    const loadQuery = vi.fn().mockResolvedValue(columns);
    await expect(assertMigrationSchemaReady({ loadQuery } as never)).resolves.toBeUndefined();
    loadQuery.mockResolvedValue(columns.filter(column => column.tableName !== "migrationChunk"));
    await expect(assertMigrationSchemaReady({ loadQuery } as never)).rejects.toThrow("migration_schema_outdated");
    loadQuery.mockResolvedValue(columns.filter(column => column.name !== "migrationCompletedAt"));
    await expect(assertMigrationSchemaReady({ loadQuery } as never)).rejects.toThrow("migration_schema_outdated");
});

it("distinguishes a missing receipt from a worker failure without exposing the worker error", async () => {
    vi.useFakeTimers();
    const unsubscribe = vi.fn();
    const worker = { subscribeError: vi.fn(() => unsubscribe), getError: vi.fn(() => null as unknown) };
    const pending = awaitWorkerOutcome(worker as never, () => undefined);
    const rejected = expect(pending).rejects.toMatchObject({ reason: "receipt_timeout", message: "bank_reconciliation_outcome_unknown" });
    await vi.advanceTimersByTimeAsync(BANK_RECONCILIATION_TIMEOUT_MS);
    await rejected;
    expect(unsubscribe).toHaveBeenCalledOnce();

    worker.getError.mockReturnValue({ type: "SqliteError", error: { message: "private synthetic detail" } });
    const start = vi.fn();
    const failure = awaitWorkerOutcome(worker as never, start).catch(error => error);
    expect(await failure).toMatchObject({ reason: "worker_error" });
    expect(JSON.stringify(await failure)).not.toContain("private synthetic detail");
    expect(start).not.toHaveBeenCalled();
});

it("retains an uncertain start exception as an unknown result", async () => {
    const worker = { subscribeError: () => () => undefined, getError: () => null };
    await expect(awaitWorkerOutcome(worker as never, () => { throw new Error("synthetic failure"); }))
        .rejects.toMatchObject({ reason: "start_exception", message: "bank_reconciliation_outcome_unknown" });
});

it("propagates both rejected existing-contact updates and rejected contact creation", () => {
    const attributes = { name: "Synthetic buyer", registration_number: null, tax_id: null, vat_id: null,
        street: null, city: null, postal_code: null, country: null, phone: null, email: null };
    const existing = { id: "contact", companyId: "company", name: attributes.name };
    vi.mocked(updateLocalContactFromForm).mockReturnValue({ ok: false } as never);
    expect(() => resolveLocalContact({} as never, "company" as never, [existing] as never, attributes, true))
        .toThrow(ContactImportWriteError);

    vi.mocked(insertLocalContactFromForm).mockReturnValue({ ok: false } as never);
    expect(() => resolveLocalContact({} as never, "company" as never, [], attributes, true))
        .toThrow(ContactImportWriteError);
});

function observedWorker(initialError: unknown = null) {
    let error = initialError;
    const listeners = new Set<() => void>();
    return {
        getError: () => error,
        subscribeError: (listener: () => void) => {
            listeners.add(listener);
            return () => { listeners.delete(listener); };
        },
        emit(next: unknown) {
            error = next;
            listeners.forEach(listener => listener());
        },
    };
}

it("accepts an explicit local receipt with a preexisting relay quota refusal", async () => {
    const worker = observedWorker({ type: "ProtocolQuotaError" });
    const start = vi.fn((complete: (value: boolean) => void) => complete(true));
    await expect(awaitWorkerOutcome(worker as never, start)).resolves.toBe(true);
    expect(start).toHaveBeenCalledOnce();
    expect(worker.getError()).toEqual({ type: "ProtocolQuotaError" });
});

it("waits for the local receipt when the relay rejects an upload during the operation", async () => {
    const worker = observedWorker();
    let receipt!: () => void;
    let completed = false;
    const pending = awaitWorkerOutcome(worker as never, complete => {
        receipt = () => complete(true);
        worker.emit({ type: "ProtocolQuotaError" });
    }).then(result => { completed = true; return result; });
    await Promise.resolve();
    expect(completed).toBe(false);
    receipt();
    await expect(pending).resolves.toBe(true);
    expect(worker.getError()).toEqual({ type: "ProtocolQuotaError" });
});

it.each([true, false])("retains quarantine when local and relay errors arrive together (localFirst=%s)", async localFirst => {
    const worker = observedWorker();
    const local = { type: "SqliteError" };
    const relay = { type: "ProtocolQuotaError" };
    const pending = serializeBankEmailReconciliation(worker as never, () =>
        awaitWorkerOutcome(worker as never, complete => {
            worker.emit(localFirst ? local : relay);
            worker.emit(localFirst ? relay : local);
            complete(true);
        }),
    );
    await expect(pending).rejects.toMatchObject({ reason: "worker_error" });
    worker.emit(relay);
    const next = vi.fn();
    await expect(serializeBankEmailReconciliation(worker as never, next)).rejects.toThrow("bank_reconciliation_outcome_unknown");
    expect(next).not.toHaveBeenCalled();
});

it("times out on a missing local receipt even when the only error is the relay quota", async () => {
    vi.useFakeTimers();
    const worker = observedWorker({ type: "ProtocolQuotaError" });
    const pending = awaitWorkerOutcome(worker as never, () => undefined);
    const rejected = expect(pending).rejects.toMatchObject({ reason: "receipt_timeout" });
    await vi.advanceTimersByTimeAsync(BANK_RECONCILIATION_TIMEOUT_MS);
    await rejected;
});

it.each(["TransferableError", "SymmetricCryptoDecryptError", "TimestampError", "ProtocolWriteError", "UnexpectedError"])(
    "keeps %s as a blocking error",
    async type => {
        const worker = observedWorker({ type });
        const start = vi.fn();
        await expect(awaitWorkerOutcome(worker as never, start)).rejects.toMatchObject({ reason: "worker_error" });
        expect(start).not.toHaveBeenCalled();
    },
);
