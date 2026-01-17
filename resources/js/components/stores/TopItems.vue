<template>
  <div class="bg-white shadow rounded-lg p-6">
    <h2 class="text-lg font-medium text-gray-900 mb-4">Top Items</h2>
    
    <div v-if="items.length === 0" class="text-center py-8 text-gray-400">
      <p>No items sold yet</p>
    </div>
    
    <div v-else class="space-y-4">
      <div
        v-for="(item, index) in items"
        :key="index"
        class="flex items-center"
      >
        <div class="flex-shrink-0">
          <div
            class="w-3 h-3 rounded-full"
            :style="{ backgroundColor: getItemColor(index) }"
          ></div>
        </div>
        <div class="ml-3 flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
          <p class="text-sm text-gray-500">
            {{ item.count }} {{ item.count === 1 ? 'sale' : 'sales' }},
            {{ formatAmount(item.total, item.currency) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Item {
  name: string;
  count: number;
  total: number;
  currency: string;
}

interface Props {
  items: Item[];
}

defineProps<Props>();

const colors = [
  'rgb(249, 115, 22)', // orange-500
  'rgb(34, 197, 94)',  // green-500
  'rgb(59, 130, 246)', // blue-500
  'rgb(168, 85, 247)', // purple-500
  'rgb(236, 72, 153)', // pink-500
];

function getItemColor(index: number): string {
  return colors[index % colors.length];
}

function formatAmount(amount: number, currency: string = 'EUR'): string {
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency || 'EUR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch (error) {
    return `${amount} ${currency || 'EUR'}`;
  }
}
</script>

