<template>
  <section
    v-if="hasBoltzServiceAlert(store)"
    role="status"
    class="mb-4 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-100"
  >
    <h2 class="text-sm font-semibold">{{ t('stores.boltz_service_alert_title') }}</h2>
    <p class="mt-1 text-sm leading-relaxed">{{ t('stores.boltz_service_alert_body') }}</p>
    <router-link
      v-if="showAction"
      :to="{ name: 'stores-wallet-connection', params: { id: store.id } }"
      class="mt-3 inline-flex rounded-lg bg-amber-400 px-3 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
      @click.stop
    >{{ t('stores.boltz_service_alert_action') }}</router-link>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Store } from '../../store/stores';
import { hasBoltzServiceAlert } from '../../utils/boltzAvailability';

withDefaults(defineProps<{
  store: Pick<Store, 'id' | 'wallet_type' | 'boltz_service_alert'>;
  showAction?: boolean;
}>(), { showAction: true });

const { t } = useI18n();
</script>
