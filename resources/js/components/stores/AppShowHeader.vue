<template>
  <div class="sticky top-0 z-20 bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-4">
      <!-- Error/Success Messages -->
      <div v-if="(error && !success) || success" class="mb-3">
        <div v-if="error && !success" class="rounded-md bg-red-50 border border-red-200 p-3">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-red-800">{{ error }}</div>
          </div>
        </div>
        <div v-if="success" class="rounded-md bg-green-50 border border-green-200 p-3">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-green-800">{{ success }}</div>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-gray-900 mb-2">{{ title }}</h1>
          <p v-if="subtitle" class="text-sm text-gray-500">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-2">
          <a
            v-if="appUrl"
            :href="appUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center px-2 md:px-4 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
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
            class="inline-flex items-center px-4 py-2 border border-transparent text-xs md:text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50"
          >
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

