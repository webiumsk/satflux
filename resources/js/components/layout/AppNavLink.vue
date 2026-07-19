<template>
  <Link v-if="isInertia" :href="href"><slot /></Link>
  <RouterLink v-else :to="href"><slot /></RouterLink>
</template>

<script setup lang="ts">
/**
 * Inertia/SPA dual-mode nav link. Replaces the
 * `<component :is="isInertia ? Link : RouterLink">` pattern, which had to
 * pass `undefined` to whichever prop the other renderer owns - RouterLink
 * types `to` as required, so every call site tripped the type checker.
 * Attrs (class, aria, handlers) fall through to the rendered root.
 */
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';
import { RouterLink } from 'vue-router';

defineProps<{ href: string }>();

const isInertia = inject<boolean>('inertia', false);
</script>
