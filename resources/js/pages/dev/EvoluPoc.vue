<template>
  <EvoluProvider :evolu="evolu">
    <EvoluPocPanel />
  </EvoluProvider>
</template>

<script setup lang="ts">
import { EvoluProvider } from "@evolu/vue";
import { evolu } from "@/evolu/client";
import EvoluPocPanel from "./EvoluPocPanel.vue";
</script>
