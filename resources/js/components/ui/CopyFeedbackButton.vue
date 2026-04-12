<template>
  <button
    type="button"
    :disabled="disabled"
    class="inline-flex items-center px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
    @click="$emit('click')"
  >
    <svg v-if="copied" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>
    <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
      />
    </svg>
    {{ copied ? copiedLabel : copyLabel }}
  </button>
</template>

<script setup lang="ts">
defineProps<{
  disabled?: boolean;
  copied: boolean;
  copyLabel: string;
  copiedLabel: string;
}>();

defineEmits<{
  click: [];
}>();
</script>
