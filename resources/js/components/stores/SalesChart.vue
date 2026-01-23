<template>
  <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 p-6 flex flex-col">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-white">{{ storeName }} {{ t('stores.sales') }}</h2>
      <div class="flex items-center gap-3">
        <div class="flex bg-gray-700/50 rounded-lg p-1">
            <button
            v-for="period in periods"
            :key="period"
            @click="selectedPeriod = period"
            :class="[
                'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
                selectedPeriod === period
                ? 'bg-gray-600 text-white shadow-sm' 
                : 'text-gray-400 hover:text-white hover:bg-gray-600/50'
            ]"
            >
            {{ period }}
            </button>
        </div>
        <router-link
          :to="manageUrl"
          class="text-sm font-medium text-indigo-400 hover:text-indigo-300 transition-colors"
        >
          {{ t('stores.manage') }}
        </router-link>
      </div>
    </div>
    
    <div class="mb-6">
      <p class="text-3xl font-bold text-white">{{ totalSales }} <span class="text-lg font-normal text-gray-400">{{ t('stores.total_sales') }}</span></p>
      <p class="text-sm text-gray-500 mt-1">{{ t('stores.sales_volume_period') }}</p>
    </div>
    
    <div class="h-64 flex items-end space-x-2 mt-auto" v-if="chartData.length > 0">
      <div
        v-for="(item, index) in chartData"
        :key="index"
        class="flex-1 flex flex-col items-center group relative"
      >
        <div
          :style="{ height: `${getBarHeight(item.count)}%` }"
          class="w-full bg-indigo-600 rounded-t-lg opacity-80 group-hover:opacity-100 group-hover:bg-indigo-500 transition-all cursor-pointer relative"
        >
           <!-- Tooltip -->
          <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max opacity-0 group-hover:opacity-100 transition-opacity bg-gray-900 border border-gray-700 text-white text-xs px-2 py-1 rounded-md shadow-lg pointer-events-none z-10">
            <p class="font-bold border-b border-gray-700 pb-1 mb-1">{{ item.date }}</p>
            <p>{{ item.count }} {{ t('stores.sales') }}</p>
          </div>
        </div>
        <!-- X Axis Label -->
       <!-- <span class="text-[10px] text-gray-500 mt-2 transform -rotate-45 origin-top-left whitespace-nowrap absolute bottom-[-20px]">
          {{ item.date.split('/')[0] }}
        </span> -->
      </div>
    </div>
    
    <div v-else class="h-64 flex flex-col items-center justify-center text-gray-500 bg-gray-900/30 rounded-xl border border-gray-700/50 border-dashed">
      <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
      <p>{{ t('stores.no_sales_data') }}</p>
    </div>

    <!-- X Axis Dates (Simplified) -->
    <div v-if="chartData.length > 0" class="flex justify-between mt-4 text-xs text-gray-500 border-t border-gray-700 pt-2">
        <span>{{ chartData[0]?.date }}</span>
        <span>{{ chartData[Math.floor(chartData.length / 2)]?.date }}</span>
        <span>{{ chartData[chartData.length - 1]?.date }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
  return Math.max((count / maxCount) * 100, count > 0 ? 5 : 0);
}
</script>
