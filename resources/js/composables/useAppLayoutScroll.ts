import { computed, type ComputedRef } from 'vue';
import { useRoute } from 'vue-router';

/**
 * Store detail routes scroll inside their own pane (sidebar + toolbar stay fixed).
 * All other authenticated routes scroll at AppLayout level on mobile.
 */
export function usesInternalMobileScroll(path: string): boolean {
  if (!/^\/stores\/[^/]+(\/.+)?$/.test(path)) {
    return false;
  }
  return path !== '/stores/create';
}

export function useAppLayoutScroll(): {
  usesInternalMobileScroll: ComputedRef<boolean>;
  layoutScrollMobile: ComputedRef<boolean>;
  isInvoicingRoute: ComputedRef<boolean>;
} {
  const route = useRoute();

  const internalScroll = computed(() => usesInternalMobileScroll(route.path));
  const layoutScrollMobile = computed(() => !internalScroll.value);
  const isInvoicingRoute = computed(() => route.path.startsWith('/invoicing'));

  return {
    usesInternalMobileScroll: internalScroll,
    layoutScrollMobile,
    isInvoicingRoute,
  };
}
