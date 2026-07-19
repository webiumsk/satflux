<template>
  <p v-if="delta !== undefined" class="mt-1.5 flex items-center gap-1 text-xs">
    <template v-if="delta === null">
      <span class="font-bold text-indigo-300">{{ newLabel }}</span>
    </template>
    <template v-else>
      <svg
        v-if="delta >= 0"
        class="w-3 h-3 text-green-500"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" /></svg>
      <svg
        v-else
        class="w-3 h-3 text-red-400"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
      <span :class="delta >= 0 ? 'font-bold text-green-500' : 'font-bold text-red-400'" class="tabular-nums">
        {{ (delta >= 0 ? '+' : '') + delta.toFixed(1) }}%
      </span>
    </template>
    <span class="text-gray-500">{{ label }}</span>
  </p>
</template>

<script setup lang="ts">
/**
 * KPI delta vs the previous period. `delta` semantics:
 * - undefined: no previous data at all (free plan) - render nothing
 * - null: previous period was zero - percent change is undefined, show "new"
 * - number: signed percent change
 */
defineProps<{
  delta: number | null | undefined;
  label: string;
  newLabel: string;
}>();
</script>
