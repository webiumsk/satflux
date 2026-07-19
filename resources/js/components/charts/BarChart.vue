<template>
  <div class="relative" @mouseleave="activeIndex = null">
    <div class="absolute inset-y-0 left-0 w-10 flex flex-col justify-between pointer-events-none select-none">
      <span
        v-for="tick in ticksTopDown"
        :key="tick"
        class="text-[10px] leading-none text-gray-500 tabular-nums"
      >
        {{ formatCompact(tick) }}
      </span>
    </div>

    <div class="relative ml-10 h-44 sm:h-56">
      <div
        v-for="tick in ticksTopDown"
        :key="'g' + tick"
        class="absolute inset-x-0 border-t border-gray-700/40 pointer-events-none"
        :style="{ top: yPercent(tick, maxValue) + '%' }"
      />

      <!-- Bars as flex items: exact pixel control (<=24px wide, 4px rounded
           data-end, square baseline, 2px surface gaps via column gap). -->
      <div class="absolute inset-0 flex items-end justify-between gap-[2px]">
        <div
          v-for="(point, index) in points"
          :key="point.label + index"
          class="relative flex-1 h-full flex items-end justify-center cursor-default"
          @mouseenter="activeIndex = index"
        >
          <div
            class="w-full max-w-[24px] rounded-t-[4px] transition-opacity"
            :class="activeIndex !== null && activeIndex !== index ? 'opacity-50' : ''"
            :style="{
              height: Math.max(point.value > 0 ? 2 : 0, 100 - yPercentValue(point.value)) + '%',
              backgroundColor: color,
            }"
          />

          <div
            v-if="activeIndex === index"
            class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-10 px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-700 shadow-xl pointer-events-none whitespace-nowrap"
          >
            <p class="text-[10px] text-gray-400 leading-tight">{{ point.label }}</p>
            <p class="text-xs font-bold text-white leading-tight tabular-nums">
              {{ formatValue(point.value) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="ml-10 mt-1 flex justify-between text-[10px] text-gray-500 select-none">
      <span>{{ points[0]?.label }}</span>
      <span v-if="points.length > 2">{{ points[Math.floor(points.length / 2)]?.label }}</span>
      <span>{{ points[points.length - 1]?.label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { CHART_PRIMARY, formatCompact, niceTicks, yPercent } from "./chartScales";
import type { ChartPoint } from "./LineAreaChart.vue";

const props = withDefaults(
  defineProps<{
    points: ChartPoint[];
    color?: string;
    formatValue?: (value: number) => string;
  }>(),
  {
    color: CHART_PRIMARY,
    formatValue: (value: number) => formatCompact(value),
  },
);

const activeIndex = ref<number | null>(null);

const maxValue = computed(() => {
  const ticks = niceTicks(Math.max(...props.points.map((p) => p.value), 0));
  return ticks[ticks.length - 1] ?? 1;
});

const ticksTopDown = computed(() =>
  niceTicks(Math.max(...props.points.map((p) => p.value), 0)).slice().reverse(),
);

function yPercentValue(value: number): number {
  return yPercent(value, maxValue.value);
}
</script>
