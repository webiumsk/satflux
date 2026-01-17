<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-6">
        <router-link
          :to="`/stores/${storeId}`"
          class="text-indigo-600 hover:text-indigo-500 text-sm"
        >
          ← Back to Store
        </router-link>
      </div>

      <div v-if="loading && !store" class="text-center py-12">
        <p class="text-gray-500">Loading...</p>
      </div>

      <div v-else-if="store" class="bg-white shadow rounded-lg p-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Wallet Onboarding Checklist</h1>
        <p class="text-gray-600 mb-6">
          Complete these steps to set up your {{ store.wallet_type === 'blink' ? 'Blink' : 'Aqua (Boltz)' }} wallet.
        </p>

        <WalletChecklist :store-id="storeId" />
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useStoresStore } from '../../store/stores';
import WalletChecklist from '../../components/stores/WalletChecklist.vue';

const route = useRoute();
const storesStore = useStoresStore();

const storeId = route.params.id as string;
const loading = ref(false);
const store = ref(null);

onMounted(async () => {
  loading.value = true;
  try {
    store.value = await storesStore.fetchStore(storeId);
  } finally {
    loading.value = false;
  }
});
</script>








