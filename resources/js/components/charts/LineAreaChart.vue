<template>
  <div class="relative" @mouseleave="activeIndex = null">
    <!-- Y-axis ticks (recessive, right-aligned into the plot gutter) -->
    <div class="absolute inset-y-0 left-0 w-10 flex flex-col justify-between pointer-events-none select-none">
      <span
        v-for="tick in ticksTopDown"
        :key="tick"
        class="text-[10px] leading-none text-gray-500 tabular-nums"
      >
        {{ formatCompact(tick) }}
      </span>
    </div>

    <div ref="plotEl" class="relative ml-10 h-44 sm:h-56" @mousemove="onMove">
      <!-- hairline grid, one line per tick -->
      <div
        v-for="tick in ticksTopDown"
        :key="'g' + tick"
        class="absolute inset-x-0 border-t border-gray-700/40 pointer-events-none"
        :style="{ top: yPercent(tick, maxValue) + '%' }"
      />

      <svg
        class="absolute inset-0 w-full h-full overflow-visible"
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        aria-hidden="true"
      >
        <defs>
          <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" :stop-color="color" stop-opacity="0.18" />
            <stop offset="100%" :stop-color="color" stop-opacity="0.02" />
          </linearGradient>
        </defs>
        <path v-if="points.length > 1" :d="areaPath" :fill="`url(#${gradientId})`" />
        <path
          v-if="points.length > 1"
          :d="linePath"
          fill="none"
          :stroke="color"
          stroke-width="2"
          stroke-linejoin="round"
          stroke-linecap="round"
          vector-effect="non-scaling-stroke"
        />
      </svg>

      <!-- hover marker: >=8px dot with a 2px surface ring -->
      <div
        v-if="activePoint"
        class="absolute w-2.5 h-2.5 rounded-full ring-2 ring-gray-800 pointer-events-none -translate-x-1/2 -translate-y-1/2"
        :style="{
          left: xPercent(activeIndex!, points.length) + '%',
          top: yPercent(activePoint.value, maxValue) + '%',
          backgroundColor: color,
        }"
      />

      <!-- tooltip -->
      <div
        v-if="activePoint"
        class="absolute z-10 -translate-x-1/2 -translate-y-full -mt-3 px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-700 shadow-xl pointer-events-none whitespace-nowrap"
        :style="{
          left: clampTooltipX(xPercent(activeIndex!, points.length)) + '%',
          top: yPercent(activePoint.value, maxValue) + '%',
        }"
      >
        <p class="text-[10px] text-gray-400 leading-tight">{{ activePoint.label }}</p>
        <p class="text-xs font-bold text-white leading-tight tabular-nums">
          {{ formatValue(activePoint.value) }}
        </p>
      </div>
    </div>

    <!-- X-axis: first / middle / last labels -->
    <div class="ml-10 mt-1 flex justify-between text-[10px] text-gray-500 select-none">
      <span>{{ points[0]?.label }}</span>
      <span v-if="points.length > 2">{{ points[Math.floor(points.length / 2)]?.label }}</span>
      <span>{{ points[points.length - 1]?.label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { CHART_PRIMARY, formatCompact, nextChartUid, niceTicks, xPercent, yPercent } from "./chartScales";

export interface ChartPoint {
  label: string;
  value: number;
}

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

const gradientId = `line-area-grad-${nextChartUid()}`;

const plotEl = ref<HTMLElement | null>(null);
const activeIndex = ref<number | null>(null);

const maxValue = computed(() => {
  const ticks = niceTicks(Math.max(...props.points.map((p) => p.value), 0));
  return ticks[ticks.length - 1] ?? 1;
});

const ticksTopDown = computed(() =>
  niceTicks(Math.max(...props.points.map((p) => p.value), 0)).slice().reverse(),
);

const activePoint = computed(() =>
  activeIndex.value === null ? null : props.points[activeIndex.value] ?? null,
);

const linePath = computed(() =>
  props.points
    .map((p, i) => `${i === 0 ? "M" : "L"} ${xPercent(i, props.points.length)} ${yPercent(p.value, maxValue.value)}`)
    .join(" "),
);

const areaPath = computed(
  () => `${linePath.value} L 100 100 L 0 100 Z`,
);

function onMove(event: MouseEvent): void {
  if (!plotEl.value || props.points.length === 0) {
    activeIndex.value = null;
    return;
  }
  const rect = plotEl.value.getBoundingClientRect();
  const ratio = (event.clientX - rect.left) / rect.width;
  activeIndex.value = Math.max(0, Math.min(props.points.length - 1, Math.round(ratio * (props.points.length - 1))));
}

/** Keep the tooltip inside the plot near the edges. */
function clampTooltipX(x: number): number {
  return Math.max(10, Math.min(90, x));
}
</script>
