<template>
  <div class="min-h-0 flex-1 max-md:flex-none max-md:overflow-visible md:overflow-y-auto overscroll-y-contain custom-scrollbar">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-6 flex flex-row items-start justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-white">{{ t('dashboard.title') }}</h1>
          <p class="text-gray-400 mt-1">{{ t('dashboard.welcome_back', { name: userName }) }}</p>
        </div>
        <button
          v-if="activeTab === 'stores'"
          type="button"
          :disabled="storesTabRef?.refreshing"
          :title="t('dashboard.refresh_stats')"
          class="p-2 rounded-lg border border-gray-600 bg-gray-800 text-gray-400 hover:text-white hover:border-indigo-500/50 hover:bg-gray-750 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          @click="handleRefresh"
        >
          <svg class="w-5 h-5" :class="{ 'animate-spin': storesTabRef?.refreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>

      <nav
        class="flex border-b border-gray-700 mb-8 -mx-1 overflow-x-auto"
        :aria-label="t('dashboard.tabs_aria')"
      >
        <button
          v-for="tab in dashboardTabs"
          :key="tab.id"
          type="button"
          :class="[
            'px-5 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
            activeTab === tab.id
              ? 'border-indigo-500 text-indigo-400'
              : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-600',
          ]"
          @click="setTab(tab.id)"
        >
          {{ t(tab.labelKey) }}
        </button>
      </nav>

      <DashboardStoresTab v-show="activeTab === 'stores'" ref="storesTabRef" />
      <DashboardInvoicingTab v-if="activeTab === 'invoicing'" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../store/auth';
import DashboardStoresTab from '../components/dashboard/DashboardStoresTab.vue';
import DashboardInvoicingTab from '../components/dashboard/DashboardInvoicingTab.vue';

type DashboardTab = 'stores' | 'invoicing';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const dashboardTabs: Array<{ id: DashboardTab; labelKey: string }> = [
  { id: 'stores', labelKey: 'dashboard.tab_stores' },
  { id: 'invoicing', labelKey: 'dashboard.tab_invoicing' },
];

const storesTabRef = ref<InstanceType<typeof DashboardStoresTab> | null>(null);

const userName = computed(() => {
  if (authStore.user?.email) {
    return authStore.user.email.split('@')[0];
  }
  return 'User';
});

function parseTab(value: unknown): DashboardTab {
  return value === 'invoicing' ? 'invoicing' : 'stores';
}

const activeTab = ref<DashboardTab>(parseTab(route.query.tab));

watch(
  () => route.query.tab,
  (q) => {
    activeTab.value = parseTab(q);
  },
);

function setTab(tab: DashboardTab) {
  activeTab.value = tab;
  const query = tab === 'stores' ? {} : { tab };
  void router.replace({ path: route.path, query });
}

async function handleRefresh() {
  await storesTabRef.value?.handleRefresh();
}

onMounted(() => {
  activeTab.value = parseTab(route.query.tab);
});
</script>
