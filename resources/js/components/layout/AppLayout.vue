<template>
  <!-- Quasar QLayout-style: fixed viewport shell; scroll only inside routed pages (not main), so store toolbars stay visible on all breakpoints. -->
  <div
    class="flex min-h-dvh flex-col overflow-hidden bg-gray-900 md:h-dvh md:max-h-dvh md:min-h-0"
  >
    <AppHeader class="shrink-0" />
    <main class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
      <slot />
      <AppFooter class="shrink-0" />
    </main>
    <OnboardingTour />
  </div>
</template>

<script setup lang="ts">
import AppHeader from './AppHeader.vue';
import AppFooter from './AppFooter.vue';
import OnboardingTour from '../OnboardingTour.vue';
</script>

