<template>
  <div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div v-if="loading && !store" class="text-center py-12">
        <p class="text-gray-500">Loading...</p>
      </div>

      <div v-else-if="store" class="bg-white shadow rounded-lg p-6">
        <div class="text-center mb-8">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Store Created Successfully!</h1>
          <p class="text-gray-600">{{ store.name }} has been created.</p>
        </div>

        <div class="border-t border-gray-200 pt-6">
          <h2 class="text-xl font-semibold text-gray-900 mb-4">Next Steps</h2>
          <p class="text-gray-600 mb-6">
            Complete the wallet onboarding checklist to start accepting payments:
          </p>
          <router-link
            :to="`/stores/${store.id}/checklist`"
            class="inline-flex items-center justify-center w-full px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
          >
            View Wallet Onboarding Checklist
          </router-link>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-200">
          <router-link
            :to="`/stores/${store.id}`"
            class="text-indigo-600 hover:text-indigo-500"
          >
            ← Back to Store
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useStoresStore } from '../../store/stores';

const route = useRoute();
const storesStore = useStoresStore();

const loading = ref(false);
const store = ref(null);

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

