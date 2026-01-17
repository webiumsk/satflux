<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <router-link
        :to="`/stores/${storeId}/invoices`"
        class="text-indigo-600 hover:text-indigo-500 text-sm"
      >
        ← Back to Invoices
      </router-link>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Invoice Details</h1>

    <div class="bg-white shadow rounded-lg p-6">
      <p class="text-gray-600">Invoice detail view will be implemented here.</p>
      <p class="text-sm text-gray-500 mt-2">Invoice ID: {{ invoiceId }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';

const route = useRoute();
const storeId = route.params.id as string;
const invoiceId = route.params.invoiceId as string;
</script>

