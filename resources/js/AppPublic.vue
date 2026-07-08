<template>
  <router-view />
  <FlashMessage />
  <CookieConsentBanner />
  <ChoralaPeekLauncher />
</template>

<script setup lang="ts">
import FlashMessage from "./components/ui/FlashMessage.vue";
import CookieConsentBanner from "./components/legal/CookieConsentBanner.vue";
import ChoralaPeekLauncher from "./components/feedback/ChoralaPeekLauncher.vue";
</script>
