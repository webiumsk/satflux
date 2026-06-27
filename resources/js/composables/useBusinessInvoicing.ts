import { computed } from 'vue';
import { useAuthStore } from '../store/auth';

export function useBusinessInvoicing() {
  const authStore = useAuthStore();

  const canUse = computed(() => {
    const u = authStore.user;
    if (!u) return false;
    const role = u.role ?? '';
    if (role === 'admin' || role === 'support' || role === 'enterprise') {
      return true;
    }
    return !!u.plan_features?.business_invoicing;
  });

  return { canUse };
}
