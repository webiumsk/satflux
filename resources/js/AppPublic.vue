<template>
  <router-view />
  <FlashMessage />
  <CookieConsentBanner />
</template>

<script setup lang="ts">
import FlashMessage from "./components/ui/FlashMessage.vue";
import CookieConsentBanner from "./components/legal/CookieConsentBanner.vue";
</script>
