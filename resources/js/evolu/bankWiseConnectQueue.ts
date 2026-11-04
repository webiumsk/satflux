/** Completion gates contain no credentials and outlive disposed views until their entered POST settles. */
const tails = new Map<string, Promise<void>>();

/** Serialize credential dispatch for one server bridge without timing out or changing entered request outcomes. */
export async function enqueueBankWiseConnect<T>(serverBridgeId: string, dispatch: () => Promise<T>): Promise<T> {
    const key = serverBridgeId.trim().toLowerCase();
    const predecessor = tails.get(key);
    let release!: () => void;
    const tail = new Promise<void>((resolve) => { release = resolve; });
    tails.set(key, tail);
    try {
        if (predecessor) await predecessor;
        return await dispatch();
    } finally {
        release();
        if (tails.get(key) === tail) tails.delete(key);
    }
}
