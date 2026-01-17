<template>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
    <!-- Paid Invoices Last 7 Days -->
    <div
      class="bg-white overflow-hidden shadow rounded-lg cursor-pointer hover:shadow-md transition "
      @click="emit('view-invoices', { filter: 'paid', days: 7 })"
    >
      <div class="p-5">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">Paid Invoices (Last 7 Days)</dt>
              <dd class="text-lg font-medium text-gray-900">{{ stats.paid_invoices_last_7d }}</dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="bg-gray-50 px-5 py-3">
        <div class="text-sm">
          <a href="#" class="font-medium text-indigo-700 hover:text-indigo-900" @click.prevent="emit('view-invoices', { filter: 'paid', days: 7 })">
            View all
          </a>
        </div>
      </div>
    </div>

    <!-- Total Invoices -->
    <div
      class="bg-white overflow-hidden shadow rounded-lg cursor-pointer hover:shadow-md transition"
      @click="emit('view-invoices')"
    >
      <div class="p-5">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-6 w-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">Total Invoices</dt>
              <dd class="text-lg font-medium text-gray-900">{{ stats.total_invoices }}</dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="bg-gray-50 px-5 py-3">
        <div class="text-sm">
          <a href="#" class="font-medium text-indigo-700 hover:text-indigo-900" @click.prevent="emit('view-invoices')">
            View all
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  stats: {
    paid_invoices_last_7d: number;
    total_invoices: number;
  };
}

defineProps<Props>();

const emit = defineEmits<{
  'view-invoices': [filters?: any];
}>();
</script>

