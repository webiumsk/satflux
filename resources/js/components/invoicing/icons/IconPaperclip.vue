<template>
  <svg
    :class="iconClass"
    fill="none"
    stroke="currentColor"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      :stroke-width="strokeWidth"
      d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"
    />
  </svg>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    iconClass?: string;
    strokeWidth?: number | string;
  }>(),
  {
    iconClass: 'w-4 h-4',
    strokeWidth: 2,
  },
);
</script>
