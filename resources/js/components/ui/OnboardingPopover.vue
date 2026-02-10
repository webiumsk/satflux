<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="fixed z-[200] max-w-sm rounded-xl border border-orange-400/40 bg-gray-900 shadow-xl shadow-orange-900/20 p-4 animate-in fade-in duration-200"
      :style="popoverStyle"
    >
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0 h-9 w-9 rounded-lg bg-orange-500/20 flex items-center justify-center">
          <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="text-sm font-bold text-orange-100 mb-1">{{ title }}</h4>
          <p class="text-sm text-gray-400 leading-relaxed">{{ body }}</p>
          <div class="flex items-center gap-2 mt-4">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-sm font-semibold text-orange-50 bg-orange-500/30 border border-orange-400/40 hover:bg-orange-500/40 hover:border-orange-400/60 transition-colors"
              @click="$emit('next')"
            >
              {{ nextLabel }}
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-400 hover:text-white transition-colors"
              @click="$emit('skip')"
            >
              {{ skipLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    visible: boolean;
    targetRect: DOMRect | null;
    placement: 'top' | 'bottom' | 'left' | 'right';
    title: string;
    body: string;
    nextLabel?: string;
    skipLabel?: string;
  }>(),
  {
    nextLabel: 'Next',
    skipLabel: 'Skip',
  }
);

defineEmits<{
  next: [];
  skip: [];
}>();

const GAP = 12;

const popoverStyle = computed(() => {
  const r = props.targetRect;
  if (!r) return { left: '-9999px', top: '0' };
  const w = 320;
  const h = 180;
  switch (props.placement) {
    case 'right':
      return {
        left: `${r.right + GAP}px`,
        top: `${r.top + r.height / 2 - h / 2}px`,
        width: `${w}px`,
      };
    case 'left':
      return {
        left: `${r.left - w - GAP}px`,
        top: `${r.top + r.height / 2 - h / 2}px`,
        width: `${w}px`,
      };
    case 'bottom':
      return {
        left: `${r.left + r.width / 2 - w / 2}px`,
        top: `${r.bottom + GAP}px`,
        width: `${w}px`,
      };
    case 'top':
    default:
      return {
        left: `${r.left + r.width / 2 - w / 2}px`,
        top: `${r.top - h - GAP}px`,
        width: `${w}px`,
      };
  }
});

</script>
