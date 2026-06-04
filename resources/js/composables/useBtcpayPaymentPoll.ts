import { onUnmounted, watch, type Ref } from 'vue';

/**
 * Poll document/list while waiting for a BTCPay webhook to mark an issued invoice paid.
 */
export function useBtcpayPaymentPoll(options: {
  enabled: Ref<boolean>;
  status: Ref<string>;
  reload: () => Promise<void>;
  intervalMs?: number;
}) {
  let timer: ReturnType<typeof setInterval> | null = null;

  function stop() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function start() {
    stop();
    if (!options.enabled.value || options.status.value !== 'issued') {
      return;
    }
    timer = setInterval(async () => {
      await options.reload();
      if (options.status.value !== 'issued') {
        stop();
      }
    }, options.intervalMs ?? 5000);
  }

  watch(
    [options.enabled, options.status],
    () => {
      if (options.enabled.value && options.status.value === 'issued') {
        start();
      } else {
        stop();
      }
    },
    { immediate: true }
  );

  onUnmounted(stop);

  return { stop, start };
}
