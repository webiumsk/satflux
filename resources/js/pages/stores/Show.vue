<template>
  <div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div v-if="loading && !store" class="text-center py-12">
        <p class="text-gray-500">Loading store...</p>
      </div>

      <div v-else-if="store" class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
          <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">{{ store.name }}</h1>
            <router-link
              :to="`/stores/${store.id}/settings`"
              class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Settings
            </router-link>
          </div>

          <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <dt class="text-sm font-medium text-gray-500">Wallet Type</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ store.wallet_type || 'Not set' }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Created</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ new Date(store.created_at).toLocaleString() }}</dd>
            </div>
          </dl>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <router-link
              :to="`/stores/${store.id}/checklist`"
              class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition"
            >
              <div class="flex-1">
                <div class="font-medium text-gray-900">View Checklist</div>
                <div class="text-sm text-gray-500">Wallet onboarding steps</div>
              </div>
            </router-link>
            <router-link
              :to="`/stores/${store.id}/exports`"
              class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition"
            >
              <div class="flex-1">
                <div class="font-medium text-gray-900">Exports</div>
                <div class="text-sm text-gray-500">CSV invoice exports</div>
              </div>
            </router-link>
            <a
              :href="btcpayStoreUrl"
              target="_blank"
              class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition"
            >
              <div class="flex-1">
                <div class="font-medium text-gray-900">Open in BTCPay</div>
                <div class="text-sm text-gray-500">Manage store settings</div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useStoresStore } from '../../store/stores';

const route = useRoute();
const storesStore = useStoresStore();

const loading = ref(false);
const store = ref(null);

const btcpayStoreUrl = computed(() => {
  // This will need to be set from backend or config
  return 'https://pay.dvadsatjeden.org/stores';
});

onMounted(async () => {
  const storeId = route.params.id as string;
  loading.value = true;
  try {
    store.value = await storesStore.fetchStore(storeId);
  } finally {
    loading.value = false;
  }
});
</script>

