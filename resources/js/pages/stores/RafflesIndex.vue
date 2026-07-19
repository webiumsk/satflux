<template>
  <RafflesPageLayout
    :store="store"
    :apps="apps"
    :error="error"
    @retry="loadStore"
    @show-settings="goSettings"
    @show-section="goSection"
  >
    <AppShowLayout v-if="store" :store="store" :app="virtualApp">
      <template #toolbar>
        <div class="border-b border-gray-800 bg-gray-900/80 backdrop-blur-md">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 lg:py-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h1 class="text-2xl font-bold text-white mb-1">{{ t('raffles.title') }}</h1>
                <p class="text-sm text-gray-400">
                  {{ t('raffles.subtitle') }}
                  <span class="text-indigo-400">{{ store.name }}</span>
                </p>
              </div>
              <button
                v-if="!pluginUnavailable && !listError"
                type="button"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
                @click="onCreateClick"
              >
                {{ t('raffles.create') }}
              </button>
            </div>
          </div>
        </div>
      </template>
      <template #default>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
          <div v-if="pluginUnavailable" class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-6 text-amber-100">
            <p class="font-medium">{{ t('raffles.plugin_not_installed') }}</p>
          </div>

          <div v-else-if="listLoading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-14 rounded-lg bg-gray-800 animate-pulse" />
          </div>

          <div v-else-if="listError" class="rounded-xl border border-red-500/30 bg-red-500/10 p-6 text-red-200">
            <p>{{ listError }}</p>
          </div>

          <div v-else-if="raffles.length === 0" class="text-center py-16 rounded-xl border border-gray-700 bg-gray-800/50">
            <p class="text-gray-300 mb-2">{{ t('raffles.no_raffles') }}</p>
            <p class="text-sm text-gray-500 mb-6">{{ t('raffles.no_raffles_description') }}</p>
            <button
              type="button"
              class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500"
              @click="onCreateClick"
            >
              {{ t('raffles.create_first') }}
            </button>
          </div>

          <div v-else class="overflow-hidden rounded-xl border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-800">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.col_name') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.col_status') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.col_tickets') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.col_price') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.col_created') }}</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700 bg-gray-900">
                <tr v-for="raffle in raffles" :key="raffle.id" class="hover:bg-gray-800/50">
                  <td class="px-4 py-3 text-sm text-white font-medium">{{ raffle.name }}</td>
                  <td class="px-4 py-3">
                    <span :class="statusBadgeClass(raffle.status)" class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium">
                      {{ t(`raffles.status_${raffle.status.toLowerCase()}`) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-300">
                    {{ raffle.ticketsSold }} / {{ raffle.maxTickets ?? '∞' }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-300">{{ formatTicketPrice(raffle) }}</td>
                  <td class="px-4 py-3 text-sm text-gray-400">{{ formatDate(raffle.createdAt) }}</td>
                  <td class="px-4 py-3 text-right">
                    <button
                      type="button"
                      class="text-indigo-400 hover:text-indigo-300 text-sm font-medium"
                      @click="router.push({ name: 'stores-raffles-show', params: { id: storeId, raffleId: raffle.id } })"
                    >
                      {{ t('raffles.manage') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </AppShowLayout>
  </RafflesPageLayout>
  <UpgradeModal
    :show="showUpgradeModal"
    :message="t('raffles.limit_modal_message')"
    :limits="
      raffleLimit != null
        ? [{ feature: 'raffles', current: raffleCount, max: raffleLimit }]
        : []
    "
    recommended-plan="pro"
    @close="showUpgradeModal = false"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import RafflesPageLayout from '../../components/stores/RafflesPageLayout.vue';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import UpgradeModal from '../../components/stores/UpgradeModal.vue';
import { useAccountLimits } from '../../composables/useAccountLimits';
import { useStorePageShell } from '../../composables/useStorePageShell';
import { useAppsStore } from '../../store/apps';
import { useRafflesStore, type Raffle, type RaffleStatus } from '../../store/raffles';
import { formatRaffleTicketPrice } from '../../utils/rafflePricing';

const { t } = useI18n();
const router = useRouter();
const rafflesStore = useRafflesStore();
const appsStore = useAppsStore();
const { limits } = useAccountLimits();
const { storeId, store, error, loadStore, goSettings, goSection } = useStorePageShell();
const apps = computed(() => appsStore.apps);

const virtualApp = computed(() => ({ id: '', name: t('raffles.title') }));

const raffles = ref<Raffle[]>([]);
const listLoading = ref(true);
const listError = ref('');
const pluginUnavailable = ref(false);
const showUpgradeModal = ref(false);

const raffleLimit = computed(() =>
    limits.value?.raffles?.unlimited ? null : (limits.value?.raffles?.max ?? null),
);

const raffleCount = computed(() => limits.value?.raffles?.current ?? raffles.value.length);

const canCreateRaffle = computed(() => {
    if (limits.value?.raffles?.allowed === false) return false;
    if (raffleLimit.value == null) return true;
    return raffleCount.value < raffleLimit.value;
});

function onCreateClick() {
    if (!canCreateRaffle.value) {
        showUpgradeModal.value = true;
        return;
    }
    router.push({ name: 'stores-raffles-create', params: { id: storeId.value } });
}

function statusBadgeClass(status: RaffleStatus): string {
    const map: Record<RaffleStatus, string> = {
        Draft: 'bg-gray-700 text-gray-300',
        Open: 'bg-green-900/50 text-green-300',
        Closed: 'bg-amber-900/50 text-amber-300',
        Drawing: 'bg-indigo-900/50 text-indigo-300',
        Completed: 'bg-gray-700 text-gray-400',
    };
    return map[status] ?? 'bg-gray-700 text-gray-300';
}

function formatTicketPrice(raffle: Raffle): string {
    return formatRaffleTicketPrice(raffle);
}

function formatDate(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return iso;
    }
}

async function loadRaffles() {
    listLoading.value = true;
    listError.value = '';
    pluginUnavailable.value = false;
    try {
        raffles.value = await rafflesStore.fetchRaffles(storeId.value);
    } catch (err: unknown) {
        const e = err as { response?: { status?: number; data?: { code?: string; message?: string } } };
        if (e.response?.status === 404 && e.response?.data?.code === 'raffle_plugin_unavailable') {
            pluginUnavailable.value = true;
        } else {
            listError.value = e.response?.data?.message || t('raffles.load_failed');
        }
    } finally {
        listLoading.value = false;
    }
}

watch(storeId, (id) => {
    if (id) {
        loadRaffles();
        appsStore.fetchApps(id).catch(() => {
            // Sidebar apps are optional; avoid unhandled rejection if fetch fails.
        });
    }
}, { immediate: true });
</script>
