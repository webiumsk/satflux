<template>
  <!-- Quasar QLayout-style: fixed viewport shell; scroll only inside page/main (not document) -->
  <div class="flex h-dvh max-h-dvh min-h-0 flex-col overflow-hidden bg-gray-900">
    <AppHeader class="shrink-0" />
    <main class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
      <slot />
    </main>
    <AppFooter class="shrink-0" />
    <OnboardingTour />
  </div>
</template>

<script setup lang="ts">
import AppHeader from './AppHeader.vue';
import AppFooter from './AppFooter.vue';
import OnboardingTour from '../OnboardingTour.vue';
</script>

