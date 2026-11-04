import type { BridgeCompanyRequestOptions } from "./numberAllocatorBridge";

/** Bound a bank read stage; nested stages also stop at their parent's original deadline. */
export async function withBankReadBudget<T>(
    read: (requestOptions: BridgeCompanyRequestOptions, signal: AbortSignal) => Promise<T>,
    options: {
        parentOptions?: BridgeCompanyRequestOptions;
        isCurrent?: () => boolean;
        registerCancel?: (cancel: () => void) => () => void;
    } = {},
): Promise<T> {
    const controller = new AbortController();
    const deadline = performance.now() + 20_000;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let parentSignal: AbortSignal | undefined;
    let unregister: (() => void) | undefined;
    let rejectAbort: (() => void) | undefined;
    const cancel = () => controller.abort();
    /** Recheck parent, context and elapsed time before every read continuation or HTTP dispatch. */
    const requestOptions: BridgeCompanyRequestOptions = () => {
        const parent = options.parentOptions?.();
        const remaining = Math.floor(deadline - performance.now());
        if (controller.signal.aborted || parent?.signal?.aborted || remaining <= 0 || options.isCurrent?.() === false) {
            throw new Error("bank_read_expired");
        }
        const parentTimeout = parent?.timeout;
        const limit = parentTimeout && Number.isFinite(parentTimeout) && parentTimeout > 0 ? parentTimeout : 10_000;
        return { signal: controller.signal, timeout: Math.max(1, Math.floor(Math.min(10_000, remaining, limit))), skipErrorFlash: true };
    };
    try {
        parentSignal = options.parentOptions?.().signal;
        parentSignal?.addEventListener("abort", cancel, { once: true });
        unregister = options.registerCancel?.(cancel);
        requestOptions();
        const aborted = new Promise<never>((_resolve, reject) => {
            rejectAbort = () => reject(new Error("bank_read_expired"));
            controller.signal.addEventListener("abort", rejectAbort, { once: true });
        });
        timer = setTimeout(cancel, Math.max(0, deadline - performance.now()));
        const result = await Promise.race([
            Promise.resolve().then(() => { requestOptions(); return read(requestOptions, controller.signal); }),
            aborted,
        ]);
        requestOptions();
        return result;
    } finally {
        clearTimeout(timer);
        if (rejectAbort) controller.signal.removeEventListener("abort", rejectAbort);
        parentSignal?.removeEventListener("abort", cancel);
        unregister?.();
        controller.abort();
    }
}
