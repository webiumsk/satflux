import { onMounted, onUnmounted, ref } from 'vue';

const MOBILE_QUERY = '(max-width: 767px)';

function getInitialMobile(): boolean {
  if (typeof window === 'undefined') {
    return false;
  }
  return window.matchMedia(MOBILE_QUERY).matches;
}

export function useInvoicingBreakpoint() {
  const isInvoicingMobile = ref(getInitialMobile());

  let mediaQuery: MediaQueryList | null = null;

  function onChange(event: MediaQueryListEvent) {
    isInvoicingMobile.value = event.matches;
  }

  onMounted(() => {
    mediaQuery = window.matchMedia(MOBILE_QUERY);
    isInvoicingMobile.value = mediaQuery.matches;
    mediaQuery.addEventListener('change', onChange);
  });

  onUnmounted(() => {
    mediaQuery?.removeEventListener('change', onChange);
  });

  return { isInvoicingMobile };
}
