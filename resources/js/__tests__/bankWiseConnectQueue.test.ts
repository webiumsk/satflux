import { afterEach, expect, it, vi } from "vitest";
import { enqueueBankWiseConnect } from "@/evolu/bankWiseConnectQueue";

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (error: Error) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
afterEach(() => { vi.useRealTimers(); });

it("serializes three current intents and keeps later arrivals behind the newest pending gate", async () => {
    const first = deferred<void>(); const second = deferred<void>(); const third = deferred<void>();
    const events: string[] = [];
    let active = 0; let maximum = 0;
    const dispatch = (name: string, pending: Promise<void>) => async () => {
        active++; maximum = Math.max(maximum, active); events.push(`${name}:start`);
        await pending;
        events.push(`${name}:end`); active--;
        return name;
    };
    const a = enqueueBankWiseConnect("synthetic-three", dispatch("a", first.promise));
    const b = enqueueBankWiseConnect("synthetic-three", dispatch("b", second.promise));
    const c = enqueueBankWiseConnect("synthetic-three", dispatch("c", third.promise));
    expect(events).toEqual(["a:start"]);
    first.resolve(); expect(await a).toBe("a");
    await vi.waitFor(() => expect(events).toContain("b:start"));
    const fourth = vi.fn(async () => "d");
    const d = enqueueBankWiseConnect("synthetic-three", fourth);
    expect(fourth).not.toHaveBeenCalled();
    second.resolve(); expect(await b).toBe("b");
    await vi.waitFor(() => expect(events).toContain("c:start"));
    expect(fourth).not.toHaveBeenCalled();
    third.resolve(); expect(await c).toBe("c"); expect(await d).toBe("d");
    expect(maximum).toBe(1);
    expect(events).toEqual(["a:start", "a:end", "b:start", "b:end", "c:start", "c:end"]);
    // A completed tail is pruned: a fresh caller dispatches immediately without awaiting a retained gate.
    const fresh = vi.fn(async () => "fresh");
    const completed = enqueueBankWiseConnect("synthetic-three", fresh);
    expect(fresh).toHaveBeenCalledTimes(1);
    expect(await completed).toBe("fresh");
});

it("shares one gate for equivalent server UUID spellings while different bridges run independently", async () => {
    const pending = deferred<void>();
    const first = enqueueBankWiseConnect(" 0192A3B4-C5D6-7E8F-9A0B-1C2D3E4F5A6B ", () => pending.promise);
    const same = vi.fn(async () => "same");
    const waiting = enqueueBankWiseConnect("0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b", same);
    const other = vi.fn(async () => "independent");
    expect(await enqueueBankWiseConnect("0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6c", other)).toBe("independent");
    expect(other).toHaveBeenCalledTimes(1); expect(same).not.toHaveBeenCalled();
    pending.resolve(); await first; expect(await waiting).toBe("same");
});

it("preserves the exact entered rejection and releases the gate for a separate explicit intent", async () => {
    const pending = deferred<void>(); const failure = new Error("synthetic entered POST failure");
    const first = enqueueBankWiseConnect("synthetic-rejection", () => pending.promise).catch((error) => error);
    const fresh = vi.fn(async () => "new-result");
    const second = enqueueBankWiseConnect("synthetic-rejection", fresh);
    expect(fresh).not.toHaveBeenCalled();
    pending.reject(failure);
    expect(await first).toBe(failure); expect(await second).toBe("new-result");
    expect(fresh).toHaveBeenCalledTimes(1);
});

it("allows the caller to reject a stale queued intent before dispatch without poisoning its successor", async () => {
    const pending = deferred<void>();
    const first = enqueueBankWiseConnect("synthetic-stale", () => pending.promise);
    let current = true;
    const post = vi.fn(async () => {});
    const stale = enqueueBankWiseConnect("synthetic-stale", async () => {
        if (!current) throw new Error("bank_bridge_lookup_failed");
        await post();
    }).catch((error) => error);
    const fresh = vi.fn(async () => "fresh");
    const latest = enqueueBankWiseConnect("synthetic-stale", fresh);
    current = false; pending.resolve(); await first;
    expect(await stale).toMatchObject({ message: "bank_bridge_lookup_failed" });
    expect(await latest).toBe("fresh"); expect(post).not.toHaveBeenCalled();
});

it("does not race an entered POST or release its successor after sixty seconds", async () => {
    vi.useFakeTimers();
    const pending = deferred<void>();
    const first = enqueueBankWiseConnect("synthetic-no-timeout", () => pending.promise);
    const post = vi.fn(async () => "second");
    const second = enqueueBankWiseConnect("synthetic-no-timeout", post);
    await vi.advanceTimersByTimeAsync(61_000);
    expect(post).not.toHaveBeenCalled(); expect(vi.getTimerCount()).toBe(0);
    pending.resolve(); await first; expect(await second).toBe("second");
});
