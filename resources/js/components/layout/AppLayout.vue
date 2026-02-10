<template>
  <div class="min-h-screen bg-gray-900 flex flex-col">
    <AppHeader />
    <main class="flex-1 overflow-hidden">
      <slot />
    </main>
    <AppFooter />
    <OnboardingTour />
  </div>
</template>

<script setup lang="ts">
import AppHeader from './AppHeader.vue';
import AppFooter from './AppFooter.vue';
import OnboardingTour from '../OnboardingTour.vue';
</script>

