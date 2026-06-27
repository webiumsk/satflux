<template>
  <InvoicingIndex v-if="!localFirst" embedded />
  <EvoluProvider v-else-if="ready && evolu" :evolu="evolu">
    <InvoicingIndex embedded />
  </EvoluProvider>
  <InvoicingEvoluBootPlaceholder
    v-else
    embedded
    :error="bootstrapError"
    @retry="retryBootstrap"
  />
</template>

<script setup lang="ts">
import { EvoluProvider } from '@evolu/vue';
import InvoicingEvoluBootPlaceholder from '../invoicing/InvoicingEvoluBootPlaceholder.vue';
import { useInvoicingEvoluShell } from '@/composables/useInvoicingEvoluShell';
import InvoicingIndex from '../../pages/invoicing/Index.vue';

const { localFirst, ready, evolu, bootstrapError, retryBootstrap } = useInvoicingEvoluShell();
</script>
