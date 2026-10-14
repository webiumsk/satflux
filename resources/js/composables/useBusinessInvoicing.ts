import { computed } from 'vue';
import { useEffectiveAccess } from './useEffectiveAccess';
export function useBusinessInvoicing() {
  const { hasFeature } = useEffectiveAccess();
  const canUse = computed(() => hasFeature('business_invoicing'));
  return { canUse };
}
