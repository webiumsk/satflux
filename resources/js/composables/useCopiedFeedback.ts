import { ref, onScopeDispose } from "vue";

const DEFAULT_MS = 2000;

/**
 * Shared "copied" flash state (e.g. after clipboard write), with debounced reset
 * and cleanup on scope dispose. Same idea as UrlQrModal copy feedback.
 */
export function useCopiedFeedback(resetAfterMs = DEFAULT_MS) {
  const copied = ref(false);
  let timer: ReturnType<typeof setTimeout> | null = null;

  function clearTimer() {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
  }

  function reset() {
    clearTimer();
    copied.value = false;
  }

  /**
   * Run async `action`, then show copied state for `resetAfterMs` after it resolves.
   * Callback must return a Promise so clipboard (and similar) work completes first.
   */
  async function flashAfter(action: () => Promise<void>): Promise<void> {
    try {
      await action();
      copied.value = true;
      clearTimer();
      timer = setTimeout(() => {
        copied.value = false;
        timer = null;
      }, resetAfterMs);
    } catch (e) {
      console.error(e);
      throw e;
    }
  }

  onScopeDispose(() => {
    clearTimer();
  });

  return { copied, flashAfter, reset };
}
