<template>
  <router-view />
  <FlashMessage />
  <CookieConsentBanner />
</template>

<script setup lang="ts">
import { watch } from "vue";
import FlashMessage from "./components/ui/FlashMessage.vue";
import CookieConsentBanner from "./components/legal/CookieConsentBanner.vue";
import { useAuthStore } from "./store/auth";
import { identifyChoralaUser } from "./services/chorala";

const authStore = useAuthStore();

watch(
  () => authStore.user,
  () => {
    void identifyChoralaUser();
  },
);
</script>
