<template>
  <div class="bg-gray-800/50 backdrop-blur-md shadow-2xl rounded-3xl border border-gray-700/50 p-8 flex flex-col relative overflow-hidden group">
    <!-- Decorative background glow -->
    <div class="absolute -top-24 -right-24 w-64 h-64 bg-indigo-600 rounded-full mix-blend-screen filter blur-[80px] opacity-10 group-hover:opacity-20 transition-opacity duration-700"></div>

    <div class="flex items-center justify-between mb-8 relative z-10">
      <div>
        <h2 class="text-xl font-bold text-white tracking-tight">{{ storeName }} {{ t('stores.sales') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ t('stores.sales_volume_period') }}</p>
      </div>
      <div class="flex items-center gap-4">
        <div class="flex bg-gray-900/50 backdrop-blur-sm rounded-xl p-1 border border-gray-700/50 shadow-inner">
            <button
            v-for="period in periods"
            :key="period"
            @click="selectedPeriod = period"
            :class="[
                'px-4 py-2 text-xs font-bold rounded-lg transition-all duration-300',
                selectedPeriod === period
                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 ring-1 ring-white/10'
                : 'text-gray-500 hover:text-white hover:bg-white/5'
            ]"
            >
            {{ period }}
            </button>
        </div>
        <router-link
          :to="manageUrl"
          class="p-2 transition-all rounded-xl hover:bg-white/5 group/btn"
        >
          <svg class="w-5 h-5 text-gray-400 group-hover/btn:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        </router-link>
      </div>
    </div>

    <div class="mb-6 relative z-10">
      <div class="flex items-baseline gap-2">
        <span class="text-5xl font-black text-white tracking-tighter">{{ totalSales }}</span>
        <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">{{ t('stores.total_sales') }}</span>
      </div>
    </div>

    <!-- Shared chart components (components/charts) - 1W bars, 1M line+area -->
    <div v-if="chartData.length > 0" class="relative z-10 mt-auto">
      <BarChart
        v-if="selectedPeriod === '1W'"
        :points="chartPoints"
        :height-class="'h-56'"
      />
      <LineAreaChart
        v-else
        :points="chartPoints"
        :height-class="'h-56'"
      />
    </div>

    <div v-else class="h-64 flex flex-col items-center justify-center text-gray-500 bg-gray-900/40 rounded-3xl border border-gray-700/50 border-dashed backdrop-blur-sm">
      <div class="w-16 h-16 bg-gray-800 rounded-2xl flex items-center justify-center mb-4 border border-gray-700">
        <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
      </div>
      <p class="font-medium">{{ t('stores.no_sales_data') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BarChart from '../charts/BarChart.vue';
import LineAreaChart, { type ChartPoint } from '../charts/LineAreaChart.vue';

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

const chartData = computed(() => (selectedPeriod.value === '1W' ? props.sales7d : props.sales30d));

const chartPoints = computed<ChartPoint[]>(() =>
  chartData.value.map((d) => ({ label: d.date, value: d.count })),
);

const totalSales = computed(() =>
  selectedPeriod.value === '1W' ? props.totalSales7d : props.totalSales30d,
);
</script>
