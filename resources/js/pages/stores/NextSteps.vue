<template>
  <div class="min-h-screen bg-gray-900 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute top-0 left-0 -ml-24 -mt-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 right-0 -mr-24 -mb-24 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl"></div>

    <div v-if="loading && !store" class="text-center relative z-10">
        <svg class="animate-spin h-12 w-12 text-indigo-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-400 font-medium">{{ t('next_steps.loading_store_details') }}</p>
    </div>

    <div v-else-if="store" class="max-w-2xl w-full bg-gray-800/50 backdrop-blur-xl shadow-2xl rounded-3xl border border-gray-700 p-8 md:p-12 relative z-10 text-center">
        <!-- Success Icon -->
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-2xl bg-green-500/10 border border-green-500/20 mb-8 shadow-lg shadow-green-500/5">
            <svg class="h-10 w-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1 class="text-4xl font-extrabold text-white mb-3 tracking-tight">{{ t('next_steps.store_created') }}</h1>
        <p class="text-lg text-gray-400 mb-10">
            <span class="text-indigo-400 font-semibold">{{ store.name }}</span> {{ t('next_steps.store_ready') }}
        </p>

        <div class="space-y-6">
            <div class="bg-gray-900/50 rounded-2xl p-6 border border-gray-700/50">
                <h2 class="text-lg font-bold text-white mb-3">{{ t('next_steps.next_step_configure_wallet') }}</h2>
                <p class="text-gray-400 text-sm mb-6 leading-relaxed">
                    {{ t('next_steps.wallet_description') }}
                </p>
                <router-link
                    :to="`/stores/${store.id}/checklist`"
                    class="inline-flex items-center justify-center w-full px-6 py-4 border border-transparent text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-all duration-200"
                >
                    {{ t('next_steps.view_onboarding_checklist') }}
                    <svg class="ml-2 -mr-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </router-link>
            </div>

            <div class="flex items-center justify-center space-x-6 pt-4">
                <router-link
                    :to="`/stores/${store.id}`"
                    class="text-sm font-semibold text-gray-400 hover:text-white transition-colors"
                >
                    {{ t('next_steps.go_to_dashboard') }}
                </router-link>
                <span class="w-1 h-1 bg-gray-700 rounded-full"></span>
                <router-link
                    :to="`/stores`"
                    class="text-sm font-semibold text-gray-400 hover:text-white transition-colors"
                >
                    {{ t('next_steps.all_stores') }}
                </router-link>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';

const { t } = useI18n();

const route = useRoute();
const storesStore = useStoresStore();

const loading = ref(false);
const store = ref<any>(null);

onMounted(async () => {
  const storeId = route.params.id as string;
  loading.value = true;
  try {
    store.value = await storesStore.fetchStore(storeId);
  } finally {
    loading.value = false;
  }
});
</script>
