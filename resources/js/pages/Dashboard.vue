<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-8">Dashboard</h1>

      <div v-if="loading" class="text-center py-12">
        <p class="text-gray-500">Loading...</p>
      </div>

      <div v-else class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Your Stores</h2>
            <router-link
              to="/stores/create"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
            >
              Create Store
            </router-link>
          </div>

          <div v-if="stores.length === 0" class="text-center py-8">
            <p class="text-gray-500 mb-4">No stores yet.</p>
            <router-link
              to="/stores/create"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
            >
              Create Your First Store
            </router-link>
          </div>

          <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="store in stores"
              :key="store.id"
              class="border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-indigo-500 hover:shadow-md transition"
              @click="$router.push(`/stores/${store.id}`)"
            >
              <h3 class="font-medium text-gray-900 mb-1">{{ store.name }}</h3>
              <p class="text-sm text-gray-500">
                {{ store.wallet_type || 'No wallet type' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useStoresStore } from '../store/stores';

const storesStore = useStoresStore();
const { stores, loading } = storesStore;

onMounted(() => {
  storesStore.fetchStores();
});
</script>

