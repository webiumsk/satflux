<template>
  <OnboardingPopover
    v-if="step && targetRect && visible"
    :visible="true"
    :target-rect="targetRect"
    :placement="step.placement"
    :title="t(step.titleKey)"
    :body="t(step.bodyKey)"
    :next-label="t('onboarding.next')"
    :skip-label="t('onboarding.skip')"
    @next="onboarding.advance()"
    @skip="onboarding.dismiss()"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, inject } from 'vue';
import { useRoute } from 'vue-router';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useOnboardingStore } from '../store/onboarding';
import OnboardingPopover from './ui/OnboardingPopover.vue';

const { t } = useI18n();
const isInertia = inject<boolean>('inertia', false);
const vueRoute = !isInertia ? useRoute() : null;
const inertiaPage = isInertia ? usePage() : null;

const currentPath = computed(() => {
  if (isInertia && inertiaPage) {
    try {
      const u = inertiaPage.url;
      return typeof u === 'string' ? new URL(u, 'http://x').pathname : (typeof window !== 'undefined' ? window.location.pathname : '');
    } catch {
      return typeof window !== 'undefined' ? window.location.pathname : '';
    }
  }
  return vueRoute ? vueRoute.path : '';
});

const onboarding = useOnboardingStore();

const step = computed(() => onboarding.currentStep);
const targetRect = ref<DOMRect | null>(null);
const visible = ref(false);

let rafId: number | null = null;
let intervalId: ReturnType<typeof setInterval> | null = null;

function updatePosition() {
  const path = currentPath.value;
  const stepVal = onboarding.currentStep;
  if (!stepVal) {
    targetRect.value = null;
    visible.value = false;
    return;
  }
  if (!stepVal.routeMatch(path)) {
    targetRect.value = null;
    visible.value = false;
    return;
  }
  const el = document.querySelector(`[data-onboarding="${stepVal.target}"]`);
  if (!el) {
    targetRect.value = null;
    visible.value = false;
    return;
  }
  targetRect.value = el.getBoundingClientRect();
  visible.value = true;
}

function scheduleUpdate() {
  if (rafId !== null) cancelAnimationFrame(rafId);
  rafId = requestAnimationFrame(() => {
    rafId = null;
    updatePosition();
  });
}

function startPolling() {
  if (intervalId !== null) return;
  intervalId = setInterval(scheduleUpdate, 500);
}

function stopPolling() {
  if (intervalId !== null) {
    clearInterval(intervalId);
    intervalId = null;
  }
}

watch(
  () => [currentPath.value, onboarding.currentStepId],
  () => {
    const path = currentPath.value;
    const onAppShow = path.includes('/stores/') && path.includes('/apps/') && !path.includes('/apps/create');
    if (onboarding.currentStepId === 'pos_1' && onAppShow) {
      onboarding.advance();
    }
    scheduleUpdate();
    if (onboarding.currentStep && onboarding.currentStep.routeMatch(path)) {
      startPolling();
    } else {
      stopPolling();
    }
  },
  { immediate: true }
);

onMounted(() => {
  scheduleUpdate();
  if (onboarding.currentStep && onboarding.currentStep.routeMatch(currentPath.value)) {
    startPolling();
  }
  window.addEventListener('resize', scheduleUpdate);
  window.addEventListener('scroll', scheduleUpdate, true);
});

onUnmounted(() => {
  stopPolling();
  if (rafId !== null) cancelAnimationFrame(rafId);
  window.removeEventListener('resize', scheduleUpdate);
  window.removeEventListener('scroll', scheduleUpdate, true);
});
</script>
