<template>
  <div class="bg-white shadow rounded-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-medium text-gray-900">{{ storeName }} Sales</h2>
      <div class="flex items-center space-x-2">
        <button
          v-for="period in periods"
          :key="period"
          @click="selectedPeriod = period"
          :class="[
            'px-3 py-1 text-sm font-medium rounded-md transition-colors',
            selectedPeriod === period
              ? 'bg-indigo-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          ]"
        >
          {{ period }}
        </button>
        <router-link
          :to="manageUrl"
          class="ml-4 text-sm font-medium text-indigo-600 hover:text-indigo-700"
        >
          Manage
        </router-link>
      </div>
    </div>
    
    <div class="mb-4">
      <p class="text-2xl font-bold text-gray-900">{{ totalSales }} Total Sales</p>
      <p class="text-sm text-gray-500 mt-1">{{ totalSales }} sales in the selected period</p>
    </div>
    
    <div class="h-64 flex items-end space-x-1" v-if="chartData.length > 0">
      <div
        v-for="(item, index) in chartData"
        :key="index"
        class="flex-1 flex flex-col items-center"
      >
        <div
          :style="{ height: `${getBarHeight(item.count)}%` }"
          class="w-full bg-indigo-600 rounded-t hover:bg-indigo-700 transition-colors cursor-pointer relative group"
          :title="`${item.date}: ${item.count} sales`"
        >
          <div class="absolute -top-8 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity bg-gray-900 text-white text-xs px-2 py-1 rounded whitespace-nowrap">
            {{ item.date }}: {{ item.count }}
          </div>
        </div>
        <span class="text-xs text-gray-500 mt-2 transform -rotate-45 origin-top-left whitespace-nowrap">
          {{ item.date }}
        </span>
      </div>
    </div>
    
    <div v-else class="h-64 flex items-center justify-center text-gray-400">
      <p>No sales data available</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';

interface Props {
  storeName: string;
  sales7d: Array<{ date: string; count: number }>;
  sales30d: Array<{ date: string; count: number }>;
  totalSales7d: number;
  totalSales30d: number;
  manageUrl?: string;
}

const props = withDefaults(defineProps<Props>(), {
  manageUrl: '#',
});

const periods = ['1W', '1M'];
const selectedPeriod = ref('1W');

const chartData = computed(() => {
  return selectedPeriod.value === '1W' ? props.sales7d : props.sales30d;
});

const totalSales = computed(() => {
  return selectedPeriod.value === '1W' ? props.totalSales7d : props.totalSales30d;
});

function getBarHeight(count: number): number {
  const maxCount = Math.max(...chartData.value.map(d => d.count), 1);
  if (maxCount === 0) return 0;
  // Minimum height of 5% for visibility, maximum 100%
  return Math.max((count / maxCount) * 95, count > 0 ? 5 : 0);
}
</script>

