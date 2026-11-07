import { beforeEach, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "@/evolu/schema";

const mocks = vi.hoisted(() => ({ maintenance: vi.fn(), commit: vi.fn() }));
vi.mock("@/evolu/client", () => ({}));
vi.mock("@/evolu/expenseReconciliation", () => ({
    withExpenseMaintenance: mocks.maintenance,
    commitExpenseMutation: mocks.commit,
}));
import { EMPTY_INVOICING_SNAPSHOT, restoreInvoicingSnapshotAsync } from "@/evolu/invoicingSnapshot";

beforeEach(() => { vi.resetAllMocks(); });

/** Exercise production restore scheduling with controlled maintenance and row-commit boundaries. */
function fixture() {
    const upsert = vi.fn(() => ({ ok: true }));
    const client = { upsert } as unknown as Evolu<InvoicingLocalSchema>;
    mocks.maintenance.mockImplementation(async (_client, run) => run({ evolu: client }));
    mocks.commit.mockImplementation(async (_client, mutate) => { mutate(vi.fn()); return { ok: true }; });
    const snapshot = { ...EMPTY_INVOICING_SNAPSHOT, company: [{ id: "synthetic-one" }, { id: "synthetic-two" }] };
    return { client, snapshot, upsert };
}

it("does not acquire maintenance or issue rows for an already stale restore context", async () => {
    const x = fixture();
    await expect(restoreInvoicingSnapshotAsync(x.client, x.snapshot, () => false)).rejects.toThrow("recovery_context_changed");
    expect(mocks.maintenance.mock.calls.length).toBe(0);
    expect(x.upsert.mock.calls.length).toBe(0);
});

it("stops before the first upsert when context changes while maintenance is acquired", async () => {
    const x = fixture();
    let current = true;
    mocks.maintenance.mockImplementation(async (_client, run) => { current = false; return run({ evolu: x.client }); });
    await expect(restoreInvoicingSnapshotAsync(x.client, x.snapshot, () => current)).rejects.toThrow("recovery_context_changed");
    expect(x.upsert.mock.calls.length).toBe(0);
});

it("does not schedule another row after context changes during an already submitted commit", async () => {
    const x = fixture();
    let current = true;
    mocks.commit.mockImplementationOnce(async (_client, mutate) => {
        mutate(vi.fn());
        current = false;
        return { ok: true };
    });
    await expect(restoreInvoicingSnapshotAsync(x.client, x.snapshot, () => current)).rejects.toThrow("recovery_context_changed");
    expect(x.upsert.mock.calls.length).toBe(1);
});

it.each([false, true])("preserves committed row counts for ordinary and current guarded callers (guarded=%s)", async guarded => {
    const x = fixture();
    await expect(restoreInvoicingSnapshotAsync(x.client, x.snapshot, guarded ? () => true : undefined)).resolves.toBe(2);
    expect(x.upsert.mock.calls.length).toBe(2);
});
