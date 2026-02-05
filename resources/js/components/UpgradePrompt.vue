<template>
  <div
    class="rounded-xl border border-amber-600/50 bg-amber-950/30 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
  >
    <p class="text-sm text-amber-200">
      {{ message }}
    </p>
    <router-link
      :to="{ name: 'pricing' }"
      class="shrink-0 inline-flex items-center px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium transition-colors"
    >
      Upgrade to Pro
    </router-link>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  message: string;
}>();
</script>
