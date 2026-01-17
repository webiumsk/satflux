<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <router-link
        :to="`/stores/${storeId}`"
        class="text-indigo-600 hover:text-indigo-500 text-sm"
      >
        ← Back to Store
      </router-link>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Invoices</h1>

    <div class="bg-white shadow rounded-lg p-6">
      <p class="text-gray-600">Invoices list will be implemented here.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';

const route = useRoute();
const storeId = route.params.id as string;
</script>

