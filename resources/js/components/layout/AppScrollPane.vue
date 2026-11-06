<template>
  <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
    <slot />
    <AppFooter v-if="showFooter" class="hidden md:block" />
  </div>
</template>

<script setup lang="ts">
import AppFooter from './AppFooter.vue';

withDefaults(defineProps<{
  /** Only primary page/layout scroll owners opt in; nested panes have no footer. */
  showFooter?: boolean;
}>(), { showFooter: false });
</script>
