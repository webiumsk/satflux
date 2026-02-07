<template>
  <span
    :class="[
      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
      planCode === 'pro' ? 'bg-indigo-600/30 text-indigo-300' : 'bg-gray-600 text-gray-300',
    ]"
  >
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    planCode?: string;
    label?: string;
  }>(),
  { planCode: 'free', label: '' }
);

const label = computed(() => props.label || (props.planCode === 'pro' ? 'Pro' : 'Free'));
</script>
