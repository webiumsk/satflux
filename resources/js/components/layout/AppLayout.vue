<template>
  <!-- Quasar QLayout-style: fixed viewport shell; scroll only inside routed pages (not main), so store toolbars stay visible on all breakpoints. -->
  <!-- Cap height on mobile too (max-h-dvh); otherwise flex-1 + overflow-y-auto inside store pages never gets a bounded column and touch scrolling breaks (e.g. Tickets). md: keeps explicit h-dvh. -->
  <div
    class="flex min-h-dvh max-h-dvh flex-col overflow-hidden bg-gray-900 md:h-dvh md:max-h-dvh md:min-h-0"
  >
    <AppHeader class="shrink-0" />
    <main class="flex min-h-0 min-w-0 flex-1 flex-col max-md:overflow-y-auto md:overflow-hidden">
      <div class="flex min-h-0 min-w-0 flex-1 flex-col max-md:flex-none max-md:min-h-0 md:overflow-hidden">
        <slot />
      </div>
      <AppFooter class="shrink-0 max-md:mt-auto" />
    </main>
    <OnboardingTour />
  </div>
</template>

<script setup lang="ts">
import AppHeader from './AppHeader.vue';
import AppFooter from './AppFooter.vue';
import OnboardingTour from '../OnboardingTour.vue';
</script>

