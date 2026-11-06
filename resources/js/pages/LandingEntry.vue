<template>
  <component :is="homepage" />
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { homepageVariant } from '../services/homepageVariant';

const homepage = defineAsyncComponent(
  homepageVariant === 'showcase'
    ? () => import('./LandingShowcase.vue')
    : () => import('./Landing.vue')
);
</script>
