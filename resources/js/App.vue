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
  <LegacyRecoveryMigrationGate />
  <FlashMessage />
  <CookieConsentBanner />
  <ChoralaPeekLauncher />
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import AppLayout from "./components/layout/AppLayout.vue";
import GuestUpgradeModal from "./components/account/GuestUpgradeModal.vue";
import LegacyRecoveryMigrationGate from "./components/auth/LegacyRecoveryMigrationGate.vue";
import FlashMessage from "./components/ui/FlashMessage.vue";
import CookieConsentBanner from "./components/legal/CookieConsentBanner.vue";
import ChoralaPeekLauncher from "./components/feedback/ChoralaPeekLauncher.vue";
import { useAppLayoutScroll } from "./composables/useAppLayoutScroll";

const route = useRoute();
const { layoutScrollMobile } = useAppLayoutScroll();

const needsLayout = computed(() => {
  return route.meta.requiresAuth === true && !route.meta.public;
});
</script>
