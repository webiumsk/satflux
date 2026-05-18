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
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div class="flex items-start gap-4 min-w-0">
                <button type="button" class="mt-1 text-gray-400 hover:text-white transition-colors shrink-0" @click="goList">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                  </svg>
                </button>
                <div class="min-w-0">
                  <div v-if="raffle" class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-white">{{ raffle.name }}</h1>
                    <span :class="statusBadgeClass(raffle.status)" class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ t(`raffles.status_${raffle.status.toLowerCase()}`) }}
                    </span>
                  </div>
                  <h1 v-else class="text-2xl font-bold text-white">{{ t('raffles.title') }}</h1>
                  <p v-if="raffle?.description" class="text-gray-400 mt-2 max-w-2xl">{{ raffle.description }}</p>
                  <p v-else class="text-sm text-gray-400 mt-1">
                    <span class="text-indigo-400">{{ store.name }}</span>
                  </p>
                </div>
              </div>
              <div v-if="raffle" class="flex flex-wrap gap-2 shrink-0">
                <button
                  v-for="action in raffle.allowedActions"
                  :key="action"
                  type="button"
                  :disabled="actionLoading"
                  class="px-4 py-2 text-sm font-medium rounded-lg text-white disabled:opacity-50"
                  :class="actionButtonClass(action)"
                  @click="openConfirm(action)"
                >
                  {{ t(`raffles.action_${action}`) }}
                </button>
                <button
                  type="button"
                  class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-600 text-gray-200 hover:bg-gray-700"
                  :disabled="refreshing"
                  @click="refreshAll"
                >
                  {{ t('raffles.refresh') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
      <template #default>
        <div v-if="detailLoading && !raffle" class="max-w-7xl mx-auto px-4 py-16 text-center text-gray-400">
          {{ t('common.loading') }}
        </div>
        <div v-else-if="detailError" class="max-w-7xl mx-auto px-4 py-16 text-center text-red-400">
          {{ detailError }}
        </div>
        <div v-else-if="raffle" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <section v-if="raffle.status === 'Draft'" class="rounded-xl border border-gray-700 bg-gray-800 p-6 space-y-4">
          <h2 class="text-lg font-semibold text-white">{{ t('raffles.edit_draft') }}</h2>
          <form class="space-y-4 max-w-xl" @submit.prevent="saveDraft">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('raffles.field_name') }} *</label>
              <input
                v-model="editForm.name"
                type="text"
                required
                maxlength="255"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('raffles.field_description') }}</label>
              <textarea v-model="editForm.description" rows="3" class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2" />
            </div>
            <RaffleTicketPricingFields
              v-model:ticket-currency="editForm.ticketCurrency"
              v-model:ticket-price="editForm.ticketPrice"
              :store-default-currency="store?.default_currency"
            />
            <div>
              <label class="flex items-center gap-2 text-sm text-gray-300 mb-2">
                <input v-model="editUnlimitedTickets" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-indigo-600" />
                {{ t('raffles.unlimited_tickets') }}
              </label>
              <input
                v-if="!editUnlimitedTickets"
                v-model.number="editForm.maxTickets"
                type="number"
                min="1"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2"
              />
            </div>
            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="editSaving"
                class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
              >
                {{ editSaving ? t('raffles.saving') : t('raffles.save_changes') }}
              </button>
            </div>
          </form>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
            <p class="text-xs text-gray-500 uppercase">{{ t('raffles.kpi_tickets_sold') }}</p>
            <p class="text-2xl font-bold text-white mt-1">{{ raffle.ticketsSold }}<span class="text-gray-500 text-lg"> / {{ raffle.maxTickets ?? '∞' }}</span></p>
          </div>
          <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
            <p class="text-xs text-gray-500 uppercase">{{ t('raffles.kpi_revenue') }}</p>
            <p class="text-2xl font-bold text-white mt-1">{{ revenueLabel }}</p>
          </div>
        </div>

        <section v-if="raffle.showsPublicLink" class="rounded-xl border border-gray-700 bg-gray-800 p-6 space-y-4">
          <h2 class="text-lg font-semibold text-white">{{ t('raffles.public_link') }}</h2>
          <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <code class="flex-1 text-sm text-indigo-300 break-all bg-gray-900 rounded-lg px-3 py-2">{{ publicUrl }}</code>
            <div class="flex gap-2 shrink-0">
              <button type="button" class="px-3 py-2 text-sm rounded-lg border border-gray-600 text-gray-200 hover:bg-gray-700" @click="copyPublicUrl">
                {{ copied ? t('common.copied') : t('raffles.copy_link') }}
              </button>
              <button type="button" class="px-3 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-500" @click="showQr = true">
                {{ t('common.qr_code') }}
              </button>
            </div>
          </div>
          <p class="text-xs text-gray-500">{{ t('raffles.public_link_hint') }}</p>
        </section>

        <section v-if="canShowPresenter" class="rounded-xl border border-gray-700 bg-gray-800 p-6 space-y-4">
          <h2 class="text-lg font-semibold text-white">{{ t('raffles.presenter_title') }}</h2>
          <p class="text-sm text-gray-400">{{ t('raffles.presenter_hint') }}</p>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              :disabled="presenterLoading"
              class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-amber-600 hover:bg-amber-500 disabled:opacity-50"
              @click="openPresenter"
            >
              {{ presenterLoading ? t('common.loading') : t('raffles.open_presenter') }}
            </button>
            <button
              v-if="lastPresenterUrl"
              type="button"
              class="px-4 py-2 text-sm rounded-lg border border-gray-600 text-gray-200 hover:bg-gray-700"
              @click="copyPresenterUrl"
            >
              {{ presenterCopied ? t('common.copied') : t('raffles.copy_presenter_link') }}
            </button>
          </div>
          <p v-if="presenterExpiresAt" class="text-xs text-gray-500">
            {{ t('raffles.presenter_expires', { time: formatDate(presenterExpiresAt) }) }}
          </p>
        </section>

        <div class="border-b border-gray-700">
          <nav class="flex gap-6">
            <button
              type="button"
              class="pb-3 text-sm font-medium border-b-2 transition-colors"
              :class="activeTab === 'tickets' ? 'border-indigo-500 text-white' : 'border-transparent text-gray-400 hover:text-gray-200'"
              @click="activeTab = 'tickets'"
            >
              {{ t('raffles.tab_tickets') }}
            </button>
            <button
              type="button"
              class="pb-3 text-sm font-medium border-b-2 transition-colors"
              :class="activeTab === 'drawings' ? 'border-indigo-500 text-white' : 'border-transparent text-gray-400 hover:text-gray-200'"
              @click="activeTab = 'drawings'"
            >
              {{ t('raffles.tab_drawings') }}
            </button>
          </nav>
        </div>

        <div v-if="activeTab === 'tickets'">
          <div v-if="ticketsLoading" class="text-gray-400 py-8 text-center">{{ t('common.loading') }}</div>
          <p v-else-if="tickets.length === 0" class="text-gray-400 py-8 text-center">{{ t('raffles.no_tickets') }}</p>
          <div v-else class="overflow-hidden rounded-xl border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-800">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">#</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.buyer') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ t('raffles.allocated_at') }}</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700 bg-gray-900">
                <tr v-for="ticket in tickets" :key="ticket.ticketNumber">
                  <td class="px-4 py-3 text-sm text-white font-mono">{{ ticket.ticketNumber }}</td>
                  <td class="px-4 py-3 text-sm text-gray-300">
                    <div>{{ ticket.buyerName || '—' }}</div>
                    <div v-if="ticket.buyerEmail" class="text-xs text-gray-500">{{ ticket.buyerEmail }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-400">{{ formatDate(ticket.allocatedAt) }}</td>
                  <td class="px-4 py-3 text-right">
                    <a
                      v-if="ticket.receiptUrl"
                      :href="receiptAbsoluteUrl(ticket.receiptUrl)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-indigo-400 hover:text-indigo-300 text-sm"
                    >
                      {{ t('raffles.receipt') }}
                    </a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else>
          <div v-if="drawingsLoading" class="text-gray-400 py-8 text-center">{{ t('common.loading') }}</div>
          <p v-else-if="drawings.length === 0" class="text-gray-400 py-8 text-center">{{ t('raffles.no_drawings') }}</p>
          <div v-else class="space-y-3">
            <div
              v-for="d in drawings"
              :key="d.drawOrder"
              class="rounded-xl border border-gray-700 bg-gray-800 p-4 flex flex-wrap items-center justify-between gap-2"
            >
              <div>
                <p class="text-white font-medium">
                  {{ t('raffles.draw_winner', { order: d.drawOrder, ticket: d.winningTicketNumber }) }}
                </p>
                <p v-if="d.winnerName || d.winnerEmail" class="text-sm text-gray-400 mt-1">
                  {{ [d.winnerName, d.winnerEmail].filter(Boolean).join(' · ') }}
                </p>
              </div>
              <span class="text-xs text-gray-500">{{ formatDate(d.drawnAt) }}</span>
            </div>
          </div>
        </div>
      </div>

      <UrlQrModal :open="showQr" :url="publicUrl" :title="t('raffles.public_link')" @close="showQr = false" />

      <div
        v-if="drawReveal"
        class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4"
        @click.self="drawReveal = null"
      >
        <div class="bg-gray-800 rounded-2xl border border-amber-500/40 max-w-lg w-full p-8 text-center shadow-2xl shadow-amber-900/20">
          <p class="text-sm uppercase tracking-wider text-amber-400 mb-2">{{ t('raffles.draw_reveal_kicker') }}</p>
          <p class="text-6xl font-bold text-white font-mono mb-4">#{{ drawReveal.winningTicketNumber }}</p>
          <p v-if="drawReveal.winnerName || drawReveal.winnerEmail" class="text-lg text-gray-200 mb-1">
            {{ drawReveal.winnerName || '—' }}
          </p>
          <p v-if="drawReveal.winnerEmail" class="text-sm text-gray-400 mb-6">{{ drawReveal.winnerEmail }}</p>
          <p v-else class="mb-6" />
          <p class="text-xs text-gray-500 mb-6">
            {{ t('raffles.draw_reveal_meta', { order: drawReveal.drawOrder }) }}
          </p>
          <button
            type="button"
            class="px-6 py-2.5 text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500"
            @click="drawReveal = null; activeTab = 'drawings'"
          >
            {{ t('raffles.draw_reveal_close') }}
          </button>
        </div>
      </div>

      <div v-if="confirmAction" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="confirmAction = null">
        <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full p-6">
          <h3 class="text-lg font-bold text-white mb-2">{{ t(`raffles.confirm_${confirmAction}_title`) }}</h3>
          <p class="text-gray-400 text-sm mb-6">{{ t(`raffles.confirm_${confirmAction}_body`) }}</p>
          <div class="flex justify-end gap-3">
            <button type="button" class="px-4 py-2 text-sm text-gray-300" @click="confirmAction = null">{{ t('common.cancel') }}</button>
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
              :disabled="actionLoading"
              @click="runConfirmedAction"
            >
              {{ t('common.confirm') }}
            </button>
          </div>
        </div>
      </div>
      </template>
    </AppShowLayout>
  </RafflesPageLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import RafflesPageLayout from '../../components/stores/RafflesPageLayout.vue';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import RaffleTicketPricingFields from '../../components/stores/RaffleTicketPricingFields.vue';
import UrlQrModal from '../../components/ui/UrlQrModal.vue';
import { useStorePageShell } from '../../composables/useStorePageShell';
import { useBtcPayUrl } from '../../composables/useBtcPayUrl';
import { useCopiedFeedback } from '../../composables/useCopiedFeedback';
import { useRafflesStore, type Raffle, type RaffleStatus, type RaffleAction, type RaffleTicket, type RaffleDrawing } from '../../store/raffles';
import { useFlashStore } from '../../store/flash';
import { resolvePresenterUrl } from '../../utils/rafflePresenterUrl';
import {
    buildRafflePricingPayload,
    formatRaffleRevenue,
    pricingFromRaffle,
} from '../../utils/rafflePricing';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const flashStore = useFlashStore();
const rafflesStore = useRafflesStore();
const { btcPayUrl, load: loadBtcPayUrl } = useBtcPayUrl();
const { copied, flashAfter } = useCopiedFeedback();
const { storeId, store, error, apps, loadStore, goSettings, goSection } = useStorePageShell();

const raffleId = computed(() => route.params.raffleId as string);
const raffle = ref<Raffle | null>(null);
const virtualApp = computed(() => ({ name: raffle.value?.name ?? t('raffles.title') }));
const tickets = ref<RaffleTicket[]>([]);
const drawings = ref<RaffleDrawing[]>([]);
const detailLoading = ref(true);
const detailError = ref('');
const ticketsLoading = ref(false);
const drawingsLoading = ref(false);
const refreshing = ref(false);
const actionLoading = ref(false);
const activeTab = ref<'tickets' | 'drawings'>('tickets');
const showQr = ref(false);
const confirmAction = ref<RaffleAction | null>(null);
const drawReveal = ref<RaffleDrawing | null>(null);
const editForm = reactive({
    name: '',
    description: '',
    ticketCurrency: 'EUR',
    ticketPrice: 5,
    maxTickets: 100 as number | null,
});
const editUnlimitedTickets = ref(false);
const editSaving = ref(false);
const presenterLoading = ref(false);
const lastPresenterUrl = ref('');
const presenterExpiresAt = ref('');
const presenterCopied = ref(false);

const canShowPresenter = computed(() => {
    const s = raffle.value?.status;
    return s === 'Closed' || s === 'Drawing' || s === 'Completed';
});

const revenueLabel = computed(() =>
    raffle.value ? formatRaffleRevenue(raffle.value) : '—',
);

const publicUrl = computed(() => {
    if (!raffle.value || !btcPayUrl.value) return '';
    return `${btcPayUrl.value}/raffle/${raffle.value.id}`;
});

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

function actionButtonClass(action: RaffleAction): string {
    if (action === 'draw') return 'bg-amber-600 hover:bg-amber-500';
    if (action === 'close' || action === 'complete') return 'bg-gray-600 hover:bg-gray-500';
    return 'bg-indigo-600 hover:bg-indigo-500';
}

function formatDate(iso: string): string {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

function receiptAbsoluteUrl(path: string): string {
    if (path.startsWith('http')) return path;
    const base = btcPayUrl.value.replace(/\/$/, '');
    return `${base}${path.startsWith('/') ? path : `/${path}`}`;
}

function goList() {
    router.push({ name: 'stores-raffles', params: { id: storeId.value } });
}

function syncEditFormFromRaffle() {
    if (!raffle.value || raffle.value.status !== 'Draft') return;
    editForm.name = raffle.value.name;
    editForm.description = raffle.value.description ?? '';
    const pricing = pricingFromRaffle(raffle.value);
    editForm.ticketCurrency = pricing.ticketCurrency;
    editForm.ticketPrice = pricing.ticketPrice;
    editUnlimitedTickets.value = raffle.value.maxTickets == null;
    editForm.maxTickets = raffle.value.maxTickets ?? 100;
}

watch(editUnlimitedTickets, (v) => {
    editForm.maxTickets = v ? null : (editForm.maxTickets ?? 100);
});

async function saveDraft() {
    if (!raffle.value || raffle.value.status !== 'Draft') return;
    editSaving.value = true;
    try {
        raffle.value = await rafflesStore.updateRaffle(storeId.value, raffleId.value, {
            name: editForm.name.trim(),
            description: editForm.description.trim() || null,
            maxTickets: editUnlimitedTickets.value ? null : editForm.maxTickets,
            ...buildRafflePricingPayload({
                ticketCurrency: editForm.ticketCurrency,
                ticketPrice: editForm.ticketPrice,
            }),
        });
        flashStore.success(t('raffles.updated_success'));
        syncEditFormFromRaffle();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        flashStore.error(e.response?.data?.message || t('raffles.update_failed'));
    } finally {
        editSaving.value = false;
    }
}

async function openPresenter() {
    presenterLoading.value = true;
    try {
        const data = await rafflesStore.createPresenterToken(storeId.value, raffleId.value);
        const url = resolvePresenterUrl(data, raffleId.value, btcPayUrl.value);
        lastPresenterUrl.value = url;
        presenterExpiresAt.value = data.expiresAt;
        window.open(url, '_blank', 'noopener,noreferrer');
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        flashStore.error(e.response?.data?.message || t('raffles.presenter_failed'));
    } finally {
        presenterLoading.value = false;
    }
}

async function copyPresenterUrl() {
    if (!lastPresenterUrl.value) return;
    try {
        await navigator.clipboard.writeText(lastPresenterUrl.value);
        presenterCopied.value = true;
        setTimeout(() => {
            presenterCopied.value = false;
        }, 2000);
    } catch {
        flashStore.error(t('raffles.copy_failed'));
    }
}

async function loadDetail() {
    detailLoading.value = true;
    detailError.value = '';
    try {
        raffle.value = await rafflesStore.fetchRaffle(storeId.value, raffleId.value);
        syncEditFormFromRaffle();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        detailError.value = e.response?.data?.message || t('raffles.load_failed');
    } finally {
        detailLoading.value = false;
    }
}

async function loadTickets() {
    ticketsLoading.value = true;
    try {
        tickets.value = await rafflesStore.fetchTickets(storeId.value, raffleId.value);
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        flashStore.error(e.response?.data?.message || t('raffles.load_failed'));
    } finally {
        ticketsLoading.value = false;
    }
}

async function loadDrawings() {
    drawingsLoading.value = true;
    try {
        drawings.value = await rafflesStore.fetchDrawings(storeId.value, raffleId.value);
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        flashStore.error(e.response?.data?.message || t('raffles.load_failed'));
    } finally {
        drawingsLoading.value = false;
    }
}

async function refreshAll() {
    refreshing.value = true;
    await loadDetail();
    await Promise.all([loadTickets(), loadDrawings()]);
    refreshing.value = false;
}

function openConfirm(action: RaffleAction) {
    confirmAction.value = action;
}

async function runConfirmedAction() {
    if (!confirmAction.value || !raffle.value) return;
    const action = confirmAction.value;
    actionLoading.value = true;
    try {
        if (action === 'open') {
            raffle.value = await rafflesStore.openRaffle(storeId.value, raffleId.value);
            flashStore.success(t('raffles.opened_success'));
        } else if (action === 'close') {
            raffle.value = await rafflesStore.closeRaffle(storeId.value, raffleId.value);
            flashStore.success(t('raffles.closed_success'));
        } else if (action === 'draw') {
            const result = await rafflesStore.drawRaffle(storeId.value, raffleId.value);
            drawReveal.value = result;
            flashStore.success(t('raffles.draw_success'));
            await loadDetail();
            await loadDrawings();
            activeTab.value = 'drawings';
        } else if (action === 'complete') {
            raffle.value = await rafflesStore.completeRaffle(storeId.value, raffleId.value);
            flashStore.success(t('raffles.completed_success'));
        }
        confirmAction.value = null;
        if (action !== 'draw') {
            await refreshAll();
        }
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string }; status?: number } };
        const msg = e.response?.data?.message || t('raffles.action_failed');
        flashStore.error(msg);
        if (action === 'draw' && e.response?.status === 400 && msg.toLowerCase().includes('eligible')) {
            confirmAction.value = null;
            await loadDetail();
        }
    } finally {
        actionLoading.value = false;
    }
}

async function copyPublicUrl() {
    if (!publicUrl.value) return;
    try {
        await navigator.clipboard.writeText(publicUrl.value);
        flashAfter();
    } catch {
        flashStore.error(t('raffles.copy_failed'));
    }
}

watch(activeTab, (tab) => {
    if (tab === 'tickets' && tickets.value.length === 0) loadTickets();
    if (tab === 'drawings' && drawings.value.length === 0) loadDrawings();
});

onMounted(async () => {
    await loadBtcPayUrl();
    await loadDetail();
    await loadTickets();
});

watch(raffleId, async () => {
    await loadDetail();
    tickets.value = [];
    drawings.value = [];
    await loadTickets();
});
</script>
