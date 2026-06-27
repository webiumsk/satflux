<template>
  <aside class="bg-gray-800/80 border border-gray-700 rounded-2xl p-6 lg:sticky lg:top-6">
    <h3 class="text-lg font-bold text-white mb-1">{{ t('dashboard.invoicing_features_title') }}</h3>
    <p class="text-sm text-gray-400 mb-6">{{ t('dashboard.invoicing_features_subtitle') }}</p>

    <div class="relative overflow-hidden rounded-xl border border-indigo-500/25 bg-gradient-to-br from-indigo-950/60 via-gray-900 to-purple-950/40 mb-6">
      <div
        v-for="(slide, index) in slides"
        :key="slide.key"
        class="transition-opacity duration-500 px-5 py-6 min-h-[11rem] flex flex-col justify-center"
        :class="index === activeSlide ? 'opacity-100' : 'opacity-0 absolute inset-0 pointer-events-none'"
        :aria-hidden="index !== activeSlide"
      >
        <div class="w-10 h-10 rounded-lg bg-indigo-600/30 border border-indigo-500/40 flex items-center justify-center mb-4">
          <svg class="w-5 h-5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <p class="text-base font-semibold text-white">{{ slide.title }}</p>
        <p class="text-sm text-gray-400 mt-2 leading-relaxed">{{ slide.description }}</p>
      </div>
      <div class="flex justify-center gap-1.5 pb-4">
        <button
          v-for="(_, index) in slides"
          :key="index"
          type="button"
          class="h-1.5 rounded-full transition-all"
          :class="index === activeSlide ? 'w-5 bg-indigo-400' : 'w-1.5 bg-gray-600 hover:bg-gray-500'"
          :aria-label="t('dashboard.invoicing_slide_go', { n: index + 1 })"
          @click="activeSlide = index"
        />
      </div>
    </div>

    <ul class="space-y-3">
      <li
        v-for="key in highlightKeys"
        :key="key"
        class="flex items-start gap-3 text-sm text-gray-300"
      >
        <svg class="w-4 h-4 text-indigo-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ t('plans.features.' + key) }}</span>
      </li>
    </ul>
  </aside>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePlanFeatures } from '../../composables/usePlanFeatures';

const { t, te } = useI18n();
const { planFeatures, load: loadPlanFeatures } = usePlanFeatures();

const activeSlide = ref(0);
let slideTimer: ReturnType<typeof setInterval> | undefined;

const highlightKeys = computed(() => planFeatures.value.invoicing_highlight_keys);

const slides = computed(() =>
  highlightKeys.value.map((key) => ({
    key,
    title: t('plans.features.' + key),
    description: te(`dashboard.invoicing_feature_blurb.${key}`)
      ? t(`dashboard.invoicing_feature_blurb.${key}`)
      : t('invoicing.subtitle'),
  })),
);

onMounted(() => {
  void loadPlanFeatures();
  slideTimer = setInterval(() => {
    const count = slides.value.length;
    if (count <= 1) return;
    activeSlide.value = (activeSlide.value + 1) % count;
  }, 5000);
});

onUnmounted(() => {
  if (slideTimer) clearInterval(slideTimer);
});
</script>
