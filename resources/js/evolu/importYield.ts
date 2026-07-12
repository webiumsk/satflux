/**
 * Shared pacing for long local imports (P1 phase 6): yield to the event loop
 * every N rows so progress can render and the UI stays responsive.
 */
export const IMPORT_YIELD_EVERY_ROWS = 25;

export function yieldToEventLoop(): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, 0);
    });
}
