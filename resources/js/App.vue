<template>
  <!-- Fill AppLayout main so routed pages get a bounded flex child (QLayout / QPageContainer pattern) -->
  <AppLayout v-if="needsLayout">
    <div
      class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
      :class="layoutScrollMobile ? 'max-md:flex-none max-md:overflow-visible' : ''"
    >
      <router-view />
    </div>
  </AppLayout>
  <router-view v-else />
  <GuestUpgradeModal />
  <FlashMessage />
  <CookieConsentBanner />
</template>

<script setup lang="ts">
import { computed, watch } from "vue";
import { useRoute } from "vue-router";
import AppLayout from "./components/layout/AppLayout.vue";
import GuestUpgradeModal from "./components/account/GuestUpgradeModal.vue";
import FlashMessage from "./components/ui/FlashMessage.vue";
import CookieConsentBanner from "./components/legal/CookieConsentBanner.vue";
import { useAppLayoutScroll } from "./composables/useAppLayoutScroll";
import { useAuthStore } from "./store/auth";
import { identifyChoralaUser } from "./services/chorala";

const route = useRoute();
const authStore = useAuthStore();
const { layoutScrollMobile } = useAppLayoutScroll();

watch(
  () => authStore.user,
  () => {
    void identifyChoralaUser();
  },
);

const needsLayout = computed(() => {
  return route.meta.requiresAuth === true && !route.meta.public;
});
</script>
