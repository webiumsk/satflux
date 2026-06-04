import { computed } from 'vue';
import { useAuthStore } from '../store/auth';

export function useBusinessInvoicing() {
  const authStore = useAuthStore();

  const canUse = computed(() => {
    const u = authStore.user;
    if (!u) return false;
    if (u.role === 'admin' || u.role === 'support' || u.role === 'enterprise') {
      return true;
    }
    return !!u.plan_features?.business_invoicing || u.plan?.features?.includes('business_invoicing');
  });

  return { canUse };
}
