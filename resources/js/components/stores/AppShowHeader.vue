<template>
  <div class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- Error/Success Messages -->
      <div v-if="(error && !success) || success" class="mb-4">
        <div v-if="error && !success" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
          <div class="flex items-start">
            <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-red-400 font-medium">{{ error }}</div>
          </div>
        </div>
        <div v-if="success" class="rounded-xl bg-green-500/10 border border-green-500/20 p-4">
          <div class="flex items-start">
            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-green-400 font-medium">{{ success }}</div>
          </div>
        </div>
      </div>

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center">
             <button @click="$router.back()" class="mr-4 text-gray-400 hover:text-white transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
             </button>
            <div>
              <h1 class="text-2xl font-bold text-white mb-1">{{ title }}</h1>
              <p v-if="subtitle" class="text-sm text-gray-400">{{ subtitle }}</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
          <!-- Actions slot for custom buttons (e.g., Delete App) -->
          <slot name="actions"></slot>
          
          <a
            v-if="appUrl"
            :href="appUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            {{ openButtonText }}
          </a>
          <button
            type="submit"
            :form="formId"
            :disabled="saving"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all hover:scale-105"
          >
            <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ saving ? savingText : saveButtonText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string;
  subtitle?: string;
  appUrl?: string;
  openButtonText: string;
  formId: string;
  saveButtonText: string;
  savingText?: string;
  saving: boolean;
  error?: string;
  success?: string;
}>();
</script>
