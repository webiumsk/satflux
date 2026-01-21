<template>
  <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
    <!-- Paid Invoices Last 7 Days -->
    <div
      class="bg-gray-800 overflow-hidden shadow-xl rounded-2xl border border-gray-700 cursor-pointer hover:border-indigo-500/50 hover:shadow-2xl transition-all duration-200 group"
      @click="emit('view-invoices', { filter: 'paid', days: 7 })"
    >
      <div class="p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-orange-500/10 p-3 rounded-xl group-hover:bg-orange-500/20 transition-colors">
            <svg class="h-8 w-8 text-orange-500" xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 512 512.001">
                <path fill="currentColor" d="M256 0c141.385 0 256 114.615 256 256 0 141.386-114.615 256.001-256 256.001S0 397.386 0 256C0 114.615 114.615 0 256 0zm84.612 201.62c-3.88-31.345-29.619-40.265-62.659-42.711l-.517-27.705-25.587.077 1.033 26.815-20.994.354-.33-27.003-26.29.266 1.41 28.22-16.4.628-35.288.417 1.158 26.805 17.994-.304c10.59.175 14.582 6.637 14.495 11.934 1.162 41.42 1.777 70.56 2.62 113.775-.464 3.892-2.146 8.86-9.739 8.637l-17.994.302-4.602 30.607 50.985-.858 1.596 28.923 24.696-.592-.518-27.707 19.399-.679 1.222 27.518 25.587-.077-.706-28.41c42.828-3.191 72.804-14.988 75.663-54.919 1.929-32.15-13.13-46.189-37.927-51.596 14.518-7.655 23.639-21.398 21.693-42.717zm-33.774 90.39c-.008 30.881-53.854 29.493-71.146 29.608l-.77-56.279c17.291-.115 71.363-6.318 71.916 26.671zm-14.386-78.993c1.018 29.102-43.315 26.671-58.312 26.922l-.858-50.983c14.997-.253 59.556-5.415 59.17 24.061zM256.002 48.49c114.604 0 207.51 92.906 207.51 207.513 0 114.604-92.906 207.509-207.51 207.509-114.606 0-207.512-92.905-207.512-207.509 0-114.607 92.906-207.513 207.512-207.513z"/>
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-400 truncate">Paid Invoices (7d)</dt>
              <dd class="text-3xl font-bold text-white mt-1">{{ stats.paid_invoices_last_7d }}</dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="bg-gray-900/50 px-6 py-3 border-t border-gray-700 flex justify-between items-center group-hover:bg-gray-900/80 transition-colors">
        <span class="text-xs text-orange-500 font-medium flex items-center">
           <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
           Recent activity
        </span>
        <div class="text-sm">
          <a href="#" class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors" @click.prevent="emit('view-invoices', { filter: 'paid', days: 7 })">
            View details &rarr;
          </a>
        </div>
      </div>
    </div>

    <!-- Total Invoices -->
    <div
      class="bg-gray-800 overflow-hidden shadow-xl rounded-2xl border border-gray-700 cursor-pointer hover:border-indigo-500/50 hover:shadow-2xl transition-all duration-200 group"
      @click="emit('view-invoices')"
    >
      <div class="p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-indigo-500/10 p-3 rounded-xl group-hover:bg-indigo-500/20 transition-colors">
            <svg class="h-8 w-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-400 truncate">Total Invoices</dt>
              <dd class="text-3xl font-bold text-white mt-1">{{ stats.total_invoices }}</dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="bg-gray-900/50 px-6 py-3 border-t border-gray-700 flex justify-between items-center group-hover:bg-gray-900/80 transition-colors">
        <span class="text-xs text-gray-500 font-medium">All time</span>
        <div class="text-sm">
          <a href="#" class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors" @click.prevent="emit('view-invoices')">
            View history &rarr;
          </a>
        </div>
      </div>
    </div>
    
    <!-- Potential 3rd stat placeholder or specific metric -->
     <div
      class="bg-gray-800 overflow-hidden shadow-xl rounded-2xl border border-gray-700 cursor-default hidden lg:block opacity-50"
    >
      <div class="p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-gray-700/50 p-3 rounded-xl">
             <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">Lightning Volume</dt>
              <dd class="text-3xl font-bold text-gray-600 mt-1">Coming Soon</dd>
            </dl>
          </div>
        </div>
      </div>
       <div class="bg-gray-900/30 px-6 py-3 border-t border-gray-700/50">
        <div class="text-sm text-gray-600">
           Tracking unavailable
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
