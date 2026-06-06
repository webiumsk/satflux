import { computed } from 'vue';
import { useAuthStore } from '../store/auth';

export function useBusinessInvoicing() {
  const authStore = useAuthStore();

  const canUse = computed(() => {
    const u = authStore.user;
    if (!u) return false;
    const role = u.role ?? '';
    const planCode = u.plan?.code ?? '';
    if (role === 'admin' || role === 'support' || role === 'enterprise' || role === 'pro') {
      return true;
    }
    if (planCode === 'pro' || planCode === 'enterprise') {
      return true;
    }
    return !!u.plan_features?.business_invoicing || u.plan?.features?.includes('business_invoicing');
  });

  return { canUse };
}
