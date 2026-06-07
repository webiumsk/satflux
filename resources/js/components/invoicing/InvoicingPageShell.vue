<template>
  <div class="invoicing-page">
    <div class="sticky top-0 z-20 shadow-sm bg-gray-100">
      <slot name="header" />
      <InvoicingDocumentSubNav v-if="isDocumentsArea" />
      <slot name="subheader" />
    </div>
    <div v-if="$slots.toolbar" class="invoicing-toolbar">
      <div :class="[INVOICING_CONTAINER_CLASS, 'py-4']">
        <slot name="toolbar" />
      </div>
    </div>
    <div :class="[INVOICING_CONTAINER_CLASS, contentClass]">
      <header v-if="title || $slots.back || $slots.actions" class="mb-6">
        <slot name="back" />
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div v-if="title || subtitle">
            <h1 v-if="title" class="invoicing-title">{{ title }}</h1>
            <p v-if="subtitle" class="invoicing-subtitle">{{ subtitle }}</p>
          </div>
          <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 shrink-0">
            <slot name="actions" />
          </div>
        </div>
      </header>
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { INVOICING_CONTAINER_CLASS } from '../../composables/useInvoicingLayout';
import InvoicingDocumentSubNav from './InvoicingDocumentSubNav.vue';
import '../../styles/invoicing-theme.css';

withDefaults(
  defineProps<{
    title?: string;
    subtitle?: string;
    contentClass?: string;
  }>(),
  { contentClass: 'py-6' }
);

const route = useRoute();

const isDocumentsArea = computed(() => {
  const name = String(route.name ?? '');
  if (
    name === 'invoicing-invoices'
    || name === 'invoicing-proformas'
    || name === 'invoicing-delivery-notes'
    || name === 'invoicing-orders'
    || name === 'invoicing-quotes'
    || name === 'invoicing-recurring'
    || name === 'invoicing-credit-notes'
    || name === 'invoicing-drafts'
    || name.includes('invoice')
    || name.includes('proforma')
  ) {
    return true;
  }

  return /\/invoicing\/companies\/[^/]+\/(invoices|proformas|delivery-notes|orders|quotes|recurring|credit-notes|drafts)(\/|$)/.test(
    route.path
  );
});
</script>
