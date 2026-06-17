<template>
  <InvoicingIndex v-if="!localFirst" />
  <EvoluProvider v-else-if="ready && evolu" :evolu="evolu">
    <InvoicingIndex />
  </EvoluProvider>
  <div v-else class="py-12 flex justify-center text-sm text-gray-400">
    {{ t('invoicing.relay_sync_loading') }}
  </div>
</template>

<script setup lang="ts">
import { shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Evolu } from '@evolu/common/local-first';
import { EvoluProvider } from '@evolu/vue';
import { ensureEvoluBoundToAccountSeed } from '@/evolu/bootstrap';
import { isInvoicingLocalFirst } from '@/evolu/flags';
import type { InvoicingLocalSchema } from '@/evolu/schema';
import InvoicingIndex from '../../pages/invoicing/Index.vue';

const { t } = useI18n();
const localFirst = isInvoicingLocalFirst();
const evolu = shallowRef<Evolu<InvoicingLocalSchema> | null>(null);
const ready = shallowRef(false);

if (localFirst) {
  void (async () => {
    await ensureEvoluBoundToAccountSeed();
    const mod = await import('@/evolu/client');
    evolu.value = mod.evolu;
    ready.value = true;
  })();
}
</script>
