<template>
  <div class="flex items-center gap-5">
    <div class="relative shrink-0" @mouseleave="activeIndex = null">
      <svg :width="size" :height="size" viewBox="0 0 42 42" role="img">
        <circle
          cx="21" cy="21" :r="radius"
          fill="none"
          class="stroke-gray-700/40"
          :stroke-width="stroke"
        />
        <!-- One arc per slice; the 2px surface gap comes from padAngle. -->
        <circle
          v-for="(slice, index) in arcs"
          :key="slice.key"
          cx="21" cy="21" :r="radius"
          fill="none"
          :stroke="slice.color"
          :stroke-width="activeIndex === index ? stroke + 1 : stroke"
          :stroke-dasharray="`${slice.length} ${circumference - slice.length}`"
          :stroke-dashoffset="slice.offset"
          class="transition-all cursor-default"
          :class="activeIndex !== null && activeIndex !== index ? 'opacity-40' : ''"
          @mouseenter="activeIndex = index"
        />
      </svg>
      <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none select-none">
        <span class="text-lg font-bold text-white tabular-nums leading-tight">
          {{ activeSlice ? formatShare(activeSlice.share) : formatShare(1) }}
        </span>
        <span class="text-[10px] text-gray-400 leading-tight max-w-[70px] truncate">
          {{ activeSlice ? activeSlice.label : totalLabel }}
        </span>
      </div>
    </div>

    <!-- Legend: dependable identity channel (never color-alone) -->
    <ul class="min-w-0 flex-1 space-y-1.5">
      <li
        v-for="(slice, index) in arcs"
        :key="'l' + slice.key"
        class="flex items-center gap-2 text-xs cursor-default"
        :class="activeIndex !== null && activeIndex !== index ? 'opacity-50' : ''"
        @mouseenter="activeIndex = index"
        @mouseleave="activeIndex = null"
      >
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: slice.color }" />
        <span class="text-gray-300 truncate">{{ slice.label }}</span>
        <span class="ml-auto text-gray-400 tabular-nums shrink-0">{{ formatShare(slice.share) }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";

export interface DonutSlice {
  key: string;
  label: string;
  value: number;
  color: string;
}

const props = withDefaults(
  defineProps<{
    slices: DonutSlice[];
    totalLabel?: string;
    size?: number;
  }>(),
  { totalLabel: "", size: 128 },
);

const stroke = 5;
const radius = computed(() => 21 - stroke / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
/** ~2px visual gap between slices, in circumference units. */
const gap = computed(() => circumference.value * 0.015);

const activeIndex = ref<number | null>(null);

const total = computed(() => props.slices.reduce((sum, s) => sum + s.value, 0));

const arcs = computed(() => {
  if (total.value <= 0) {
    return [];
  }
  const visible = props.slices.filter((s) => s.value > 0);
  const gapTotal = visible.length > 1 ? gap.value * visible.length : 0;
  const usable = circumference.value - gapTotal;
  let cursor = 0;

  return visible.map((slice) => {
    const share = slice.value / total.value;
    const length = Math.max(0.5, share * usable);
    const arc = {
      ...slice,
      share,
      length,
      // Dashoffset rotates the arc start; begin at 12 o'clock (offset by a
      // quarter turn) and advance the cursor per slice + gap.
      offset: circumference.value / 4 - cursor,
    };
    cursor += length + (visible.length > 1 ? gap.value : 0);

    return arc;
  });
});

const activeSlice = computed(() =>
  activeIndex.value === null ? null : arcs.value[activeIndex.value] ?? null,
);

function formatShare(share: number): string {
  return Math.round(share * 100) + "%";
}
</script>
