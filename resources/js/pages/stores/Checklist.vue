<template>
  <div class="min-h-screen bg-gray-900 border-l border-gray-800 flex flex-col">
    <div class="max-w-4xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
      <!-- Back Link -->
      <div class="mb-8">
        <router-link
          :to="`/stores/${storeId}`"
          class="inline-flex items-center text-sm font-semibold text-gray-400 hover:text-white transition-colors group"
        >
          <svg class="w-4 h-4 mr-2 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          {{ t('common.back') }} {{ t('dashboard.title') }}
        </router-link>
      </div>

      <div v-if="loading && !store" class="flex flex-col items-center justify-center py-24">
        <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-400">{{ t('common.loading') }}</p>
      </div>

      <div v-else-if="store" class="bg-gray-800/50 backdrop-blur-sm border border-gray-700 shadow-2xl rounded-3xl p-8 md:p-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-white mb-2 tracking-tight">{{ t('stores.wallet_onboarding') }}</h1>
                <p v-if="store.wallet_type" class="text-gray-400 flex flex-wrap items-center gap-2">
                  {{ t('stores.complete_steps_setup') }}
                  <WalletTypeIcon
                    :type="store.wallet_type"
                    size="sm"
                    :show-label="true"
                    class="text-indigo-400 font-semibold"
                  />
                  {{ t('stores.wallet') }}
                </p>
            </div>
            <div v-if="store.wallet_type" class="flex-shrink-0">
                <div class="bg-gray-900/50 border border-gray-700/50 rounded-2xl px-5 py-3 flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-indigo-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ t('stores.progress') }}</div>
                        <div class="text-sm font-bold text-white">{{ progressLabel }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!store.wallet_type" class="rounded-2xl border border-amber-500/25 bg-amber-500/5 p-6 mb-6 space-y-4">
          <p class="text-sm text-gray-300 leading-relaxed">
            {{ t('stores.checklist_needs_wallet_type') }}
          </p>
          <router-link
            :to="`/stores/${storeId}/wallet-connection`"
            class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors"
          >
            {{ t('setup_wizard.go_wallet_connection') }}
          </router-link>
        </div>

        <div v-else class="space-y-2">
            <WalletChecklist :store-id="storeId" @stats="onChecklistStats" />
        </div>
        
        <div class="mt-12 pt-8 border-t border-gray-700 flex justify-center">
             <p class="text-sm text-gray-500 italic">
                 {{ t('stores.all_steps_required') }}
             </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import WalletChecklist from '../../components/stores/WalletChecklist.vue';
import WalletTypeIcon from '../../components/WalletTypeIcon.vue';

const { t } = useI18n();

const route = useRoute();
const storesStore = useStoresStore();

const storeId = route.params.id as string;
const loading = ref(false);
const store = ref<any>(null);
const checklistStats = ref<{ total: number; completed: number } | null>(null);

const progressLabel = computed(() => {
  const s = checklistStats.value;
  if (!s || s.total === 0) {
    return t('stores.setup_in_progress');
  }
  if (s.completed >= s.total) {
    return t('stores.setup_complete');
  }
  return t('stores.setup_in_progress');
});

function onChecklistStats(payload: { total: number; completed: number }) {
  checklistStats.value = payload;
}

onMounted(async () => {
  loading.value = true;
  try {
    store.value = await storesStore.fetchStore(storeId);
  } finally {
    loading.value = false;
  }
});
</script>
