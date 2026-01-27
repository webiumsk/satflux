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
    
    <div class="mb-10 relative z-10">
      <div class="flex items-baseline gap-2">
        <span class="text-5xl font-black text-white tracking-tighter">{{ totalSales }}</span>
        <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">{{ t('stores.total_sales') }}</span>
      </div>
    </div>
    
    <!-- SVG Chart Container -->
    <div class="relative h-64 w-full mt-auto" v-if="chartData.length > 0">
      <svg class="w-full h-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none">
        <!-- Gradients -->
        <defs>
          <linearGradient id="chartGradient" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.3" />
            <stop offset="100%" stop-color="#4f46e5" stop-opacity="0" />
          </linearGradient>
          <linearGradient id="barGradient" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.8" />
            <stop offset="100%" stop-color="#4f46e5" stop-opacity="0.2" />
          </linearGradient>
          <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="2" result="blur" />
            <feComposite in="SourceGraphic" in2="blur" operator="over" />
          </filter>
        </defs>

        <!-- Bar Chart (1W) -->
        <template v-if="selectedPeriod === '1W'">
          <g v-for="(item, index) in chartData" :key="`bar-${index}`">
            <rect
              :x="getX(index) - (90 / chartData.length / 2)"
              :y="getY(item.count)"
              :width="90 / chartData.length"
              :height="100 - getY(item.count)"
              fill="url(#barGradient)"
              rx="2"
              class="transition-all duration-700 ease-in-out hover:fill-indigo-400 group/bar cursor-pointer"
              @mouseenter="activePoint = index"
              @mouseleave="activePoint = null"
            />
          </g>
        </template>

        <!-- Line Chart (1M) -->
        <template v-else>
          <!-- Area Fill -->
          <path
            :d="areaPath"
            fill="url(#chartGradient)"
            class="transition-all duration-700 ease-in-out"
          />

          <!-- Line -->
          <path
            :d="linePath"
            fill="none"
            stroke="#6366f1"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            style="filter: url(#glow)"
            class="transition-all duration-700 ease-in-out"
          />
        </template>

        <!-- Hover Tracking (Overlay) -->
        <g v-if="selectedPeriod === '1M'" v-for="(item, index) in chartData" :key="`hover-${index}`">
          <!-- Invisible wide rect for better hover experience on line chart -->
          <rect
            :x="getX(index) - (100 / chartData.length / 2)"
            y="0"
            :width="100 / chartData.length"
            height="100"
            fill="transparent"
            class="cursor-pointer"
            @mouseenter="activePoint = index"
            @mouseleave="activePoint = null"
          />
        </g>
      </svg>

      <!-- HTML Tooltip -->
      <div 
        v-if="activePoint !== null"
        class="absolute pointer-events-none transition-all duration-200 z-50 bg-gray-900/90 backdrop-blur-md border border-gray-700 shadow-2xl rounded-2xl p-4 min-w-[140px]"
        :style="{
          left: `${getX(activePoint)}%`,
          bottom: `${100 - getY(chartData[activePoint].count)}%`,
          transform: 'translate(-50%, -20px)'
        }"
      >
        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">{{ chartData[activePoint].date }}</p>
        <div class="flex items-center justify-between">
           <span class="text-xs font-bold text-white">{{ t('stores.sales') }}</span>
           <span class="text-sm font-black text-indigo-400">{{ chartData[activePoint].count }}</span>
        </div>
        <div class="w-full h-1 bg-gray-800 rounded-full mt-2 overflow-hidden">
           <div class="h-full bg-indigo-500 rounded-full" :style="{ width: `${(chartData[activePoint].count / maxCount) * 100}%` }"></div>
        </div>
      </div>

      <!-- Marker Dot -->
      <div 
        v-if="activePoint !== null"
        class="absolute w-3 h-3 bg-indigo-500 rounded-full border-2 border-white shadow-lg pointer-events-none transition-all duration-200"
        :style="{
          left: `${getX(activePoint)}%`,
          top: `${getY(chartData[activePoint].count)}%`,
          transform: 'translate(-50%, -50%)'
        }"
      >
        <div class="absolute inset-0 animate-ping bg-indigo-400 rounded-full"></div>
      </div>
    </div>
    
    <div v-else class="h-64 flex flex-col items-center justify-center text-gray-500 bg-gray-900/40 rounded-3xl border border-gray-700/50 border-dashed backdrop-blur-sm">
      <div class="w-16 h-16 bg-gray-800 rounded-2xl flex items-center justify-center mb-4 border border-gray-700">
        <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
      </div>
      <p class="font-medium">{{ t('stores.no_sales_data') }}</p>
    </div>

    <!-- X Axis Dates (Simplified) -->
    <div v-if="chartData.length > 0" class="flex justify-between mt-8 text-[10px] font-bold text-gray-500 uppercase tracking-widest relative z-10 border-t border-gray-700/50 pt-4">
        <span class="bg-gray-800 px-2 rounded-md">{{ chartData[0]?.date }}</span>
        <span class="bg-gray-800 px-2 rounded-md">{{ chartData[Math.floor(chartData.length / 2)]?.date }}</span>
        <span class="bg-gray-800 px-2 rounded-md">{{ chartData[chartData.length - 1]?.date }}</span>
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
const activePoint = ref<number | null>(null);

const chartData = computed(() => {
  return selectedPeriod.value === '1W' ? props.sales7d : props.sales30d;
});

const totalSales = computed(() => {
  return selectedPeriod.value === '1W' ? props.totalSales7d : props.totalSales30d;
});

const maxCount = computed(() => {
  const max = Math.max(...chartData.value.map(d => d.count), 0);
  return max === 0 ? 10 : max * 1.2; // Add 20% headroom
});

function getX(index: number): number {
  if (chartData.value.length === 0) return 0;
  if (chartData.value.length === 1) return 50;
  
  // For bar chart, we want some padding on the edges
  if (selectedPeriod.value === '1W') {
    return (index / (chartData.value.length - 1)) * 80 + 10;
  }
  
  return (index / (chartData.value.length - 1)) * 100;
}

function getY(count: number): number {
  if (maxCount.value === 0) return 100;
  return 100 - (count / maxCount.value) * 100;
}

const linePath = computed(() => {
  if (chartData.value.length === 0) return '';
  
  return chartData.value.reduce((path, item, index) => {
    const x = getX(index);
    const y = getY(item.count);
    
    if (index === 0) return `M ${x},${y}`;
    
    // Use cubic bezier for smoother curves? Actually simple line for clarity
    // But let's try a very subtle smoothing (catmull-rom style logic would be overkill, 
    // let's do simple linear for now but we can enhance)
    return `${path} L ${x},${y}`;
  }, '');
});

const areaPath = computed(() => {
  if (chartData.value.length === 0) return '';
  const firstX = getX(0);
  const lastX = getX(chartData.value.length - 1);
  return `${linePath.value} L ${lastX},100 L ${firstX},100 Z`;
});
</script>
