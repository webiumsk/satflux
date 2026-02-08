<template>
  <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 p-6 flex flex-col h-full">
    <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
      <svg class="h-5 w-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
      </svg>
      {{ t('stores.top_items') }}
    </h2>
    
    <div v-if="items.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-900/30 rounded-xl border border-gray-700/50 border-dashed py-12">
      <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
      </svg>
      <p>{{ t('stores.no_items_sold') }}</p>
    </div>
    
    <div v-else class="space-y-4 flex-1">
      <div
        v-for="(item, index) in items"
        :key="index"
        class="flex items-center group p-3 rounded-xl hover:bg-gray-700/50 transition-all duration-200"
      >
        <div class="flex-shrink-0 relative">
          <div
            class="w-4 h-4 rounded-full shadow-lg"
            :style="{ backgroundColor: getItemColor(index) }"
          ></div>
          <div 
            class="absolute inset-0 rounded-full blur-[2px] opacity-50"
            :style="{ backgroundColor: getItemColor(index) }"
          ></div>
        </div>
        <div class="ml-4 flex-1 min-w-0">
          <div class="flex justify-between items-start">
            <p class="text-sm font-bold text-white truncate group-hover:text-indigo-300 transition-colors">{{ item.name }}</p>
            <p class="text-sm font-bold text-gray-200 ml-2">
              {{ formatAmount(item.total, item.currency) }}
            </p>
          </div>
          <div class="flex justify-between items-center mt-1">
            <p class="text-xs text-gray-500">
              {{ item.count }} {{ item.count === 1 ? t('stores.sale') : t('stores.sales') }}
            </p>
            <div class="h-1.5 w-24 bg-gray-700 rounded-full overflow-hidden">
                <div 
                    class="h-full rounded-full transition-all duration-500"
                    :style="{ 
                        width: `${(item.total / maxTotal) * 100}%`,
                        backgroundColor: getItemColor(index) 
                    }"
                ></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Item {
  name: string;
  count: number;
  total: number;
  currency: string;
}

interface Props {
  items: Item[];
}

const props = defineProps<Props>();

const colors = [
  '#6366f1', // indigo-500
  '#f59e0b', // amber-500
  '#10b981', // emerald-500
  '#ef4444', // red-500
  '#8b5cf6', // violet-500
];

const maxTotal = computed(() => {
  return Math.max(...props.items.map(item => item.total), 1);
});

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
