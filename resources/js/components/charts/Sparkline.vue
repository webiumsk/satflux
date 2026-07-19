<template>
  <svg
    class="overflow-visible"
    :width="width"
    :height="height"
    viewBox="0 0 100 100"
    preserveAspectRatio="none"
    aria-hidden="true"
  >
    <path
      v-if="values.length > 1"
      :d="path"
      fill="none"
      :stroke="color"
      stroke-width="1.5"
      stroke-linejoin="round"
      stroke-linecap="round"
      vector-effect="non-scaling-stroke"
    />
  </svg>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { CHART_PRIMARY, xPercent, yPercent } from "./chartScales";

const props = withDefaults(
  defineProps<{
    values: number[];
    color?: string;
    width?: number;
    height?: number;
  }>(),
  { color: CHART_PRIMARY, width: 72, height: 24 },
);

const path = computed(() => {
  const max = Math.max(...props.values, 1);

  return props.values
    .map((v, i) => `${i === 0 ? "M" : "L"} ${xPercent(i, props.values.length)} ${yPercent(v, max)}`)
    .join(" ");
});
</script>
