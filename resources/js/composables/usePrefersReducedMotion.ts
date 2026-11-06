import { onMounted, readonly, ref } from 'vue';

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/** Share the landing previews' mounted system-preference snapshot with imperative scrolling. */
export function usePrefersReducedMotion() {
  const prefersReducedMotion = ref(false);
  onMounted(() => {
    prefersReducedMotion.value = typeof window !== 'undefined'
      && typeof window.matchMedia === 'function'
      && window.matchMedia(REDUCED_MOTION_QUERY).matches;
  });
  return readonly(prefersReducedMotion);
}
