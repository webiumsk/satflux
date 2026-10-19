import { computed } from 'vue';
import { useAuthStore } from '../store/auth';
import { useEffectiveAccess, resolveEffectivePlanCode } from './useEffectiveAccess';
export type { PlanCode } from '../types/access';
export const resolvePlanCode = resolveEffectivePlanCode;

export function useCurrentPlan() {
  const authStore = useAuthStore();
  const { planCode, purchases } = useEffectiveAccess();
  const hasProOrHigher = computed(() => planCode.value === 'pro' || planCode.value === 'enterprise');
  const canUpgradeToPro = computed(() => authStore.isAuthenticated
    && planCode.value === 'free' && purchases.value.pro_subscriptions);
  const canStartTrial = computed(() => canUpgradeToPro.value && !authStore.user?.trial_consumed_at);
  return { planCode, hasProOrHigher, canUpgradeToPro, canStartTrial };
}
