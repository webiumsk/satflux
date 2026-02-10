import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';

const STORAGE_KEY = 'onboarding_pos_tour';

export type PosOnboardingStepId = 'pos_1' | 'pos_2' | 'pos_3' | 'pos_4' | 'done' | 'dismissed';

export interface OnboardingStep {
  id: PosOnboardingStepId;
  target: string; // data-onboarding attribute value, e.g. "pos-1"
  titleKey: string;
  bodyKey: string;
  placement: 'top' | 'bottom' | 'left' | 'right';
  /** Route path must match (regex or string contains) for this step to be visible */
  routeMatch: (path: string) => boolean;
}

const POS_STEPS: OnboardingStep[] = [
  {
    id: 'pos_1',
    target: 'pos-1',
    titleKey: 'onboarding.pos_1_title',
    bodyKey: 'onboarding.pos_1_body',
    placement: 'right',
    routeMatch: (path) => path.match(/^\/stores\/[^/]+\/?$/) !== null,
  },
  {
    id: 'pos_2',
    target: 'pos-2',
    titleKey: 'onboarding.pos_2_title',
    bodyKey: 'onboarding.pos_2_body',
    placement: 'bottom',
    routeMatch: (path) => path.includes('/stores/') && path.includes('/apps/') && !path.includes('/apps/create'),
  },
  {
    id: 'pos_3',
    target: 'pos-3',
    titleKey: 'onboarding.pos_3_title',
    bodyKey: 'onboarding.pos_3_body',
    placement: 'top',
    routeMatch: (path) => path.includes('/stores/') && path.includes('/apps/') && !path.includes('/apps/create'),
  },
  {
    id: 'pos_4',
    target: 'pos-4',
    titleKey: 'onboarding.pos_4_title',
    bodyKey: 'onboarding.pos_4_body',
    placement: 'bottom',
    routeMatch: (path) => path.includes('/stores/') && path.includes('/apps/') && !path.includes('/apps/create'),
  },
];

function loadStored(): PosOnboardingStepId {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v === 'dismissed' || v === 'done') return v;
    if (v === 'pos_1' || v === 'pos_2' || v === 'pos_3' || v === 'pos_4') return v;
  } catch (_) {}
  return 'pos_1';
}

export const useOnboardingStore = defineStore('onboarding', () => {
  const currentStepId = ref<PosOnboardingStepId>(loadStored());

  const currentStep = computed(() => {
    if (currentStepId.value === 'done' || currentStepId.value === 'dismissed') return null;
    return POS_STEPS.find((s) => s.id === currentStepId.value) ?? null;
  });

  const stepIndex = computed(() => {
    if (!currentStep.value) return -1;
    return POS_STEPS.findIndex((s) => s.id === currentStep.value!.id);
  });

  const nextStepId = computed((): PosOnboardingStepId | null => {
    const idx = stepIndex.value;
    if (idx < 0 || idx >= POS_STEPS.length - 1) return 'done';
    return POS_STEPS[idx + 1].id;
  });

  function advance() {
    const next = nextStepId.value;
    if (next === 'done') {
      currentStepId.value = 'done';
    } else {
      currentStepId.value = next;
    }
    try {
      localStorage.setItem(STORAGE_KEY, currentStepId.value);
    } catch (_) {}
  }

  function dismiss() {
    currentStepId.value = 'dismissed';
    try {
      localStorage.setItem(STORAGE_KEY, 'dismissed');
    } catch (_) {}
  }

  function reset() {
    currentStepId.value = 'pos_1';
    try {
      localStorage.setItem(STORAGE_KEY, 'pos_1');
    } catch (_) {}
  }

  function isActive() {
    return currentStepId.value !== 'dismissed' && currentStepId.value !== 'done';
  }

  return {
    currentStepId,
    currentStep,
    stepIndex,
    nextStepId,
    steps: POS_STEPS,
    advance,
    dismiss,
    reset,
    isActive,
  };
});
