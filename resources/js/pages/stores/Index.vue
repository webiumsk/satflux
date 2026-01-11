<template>
  <div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mb-6 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">Stores</h1>
        <router-link
          to="/stores/create"
          class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
        >
          Create Store
        </router-link>
      </div>

      <div v-if="loading" class="text-center py-12">
        <p class="text-gray-500">Loading stores...</p>
      </div>

      <div v-else-if="stores.length === 0" class="text-center py-12">
        <p class="text-gray-500 mb-4">No stores yet.</p>
        <router-link
          to="/stores/create"
          class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
        >
          Create Your First Store
        </router-link>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="store in stores"
          :key="store.id"
          class="bg-white overflow-hidden shadow rounded-lg cursor-pointer hover:shadow-lg transition"
          @click="$router.push(`/stores/${store.id}`)"
        >
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ store.name }}</h3>
            <p class="text-sm text-gray-500">
              Wallet: {{ store.wallet_type || 'Not set' }}
            </p>
            <p class="text-xs text-gray-400 mt-2">
              Created: {{ new Date(store.created_at).toLocaleDateString() }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useStoresStore } from '../../store/stores';

const storesStore = useStoresStore();
const { stores, loading } = storesStore;

onMounted(() => {
  storesStore.fetchStores();
});
</script>

