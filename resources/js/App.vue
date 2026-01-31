<template>
  <AppLayout v-if="needsLayout">
    <router-view />
  </AppLayout>
  <router-view v-else />
  <FlashMessage />
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import AppLayout from "./components/layout/AppLayout.vue";
import FlashMessage from "./components/ui/FlashMessage.vue";

const route = useRoute();

// Show layout for authenticated pages (pages that require auth)
// Public pages (landing) don't need AppLayout, they have their own layout
const needsLayout = computed(() => {
  return route.meta.requiresAuth === true && !route.meta.public;
});
</script>
