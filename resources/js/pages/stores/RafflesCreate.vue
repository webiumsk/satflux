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
            <div class="flex items-center">
              <button type="button" class="mr-4 text-gray-400 hover:text-white transition-colors" @click="goBack">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
              </button>
              <div>
                <h1 class="text-2xl font-bold text-white mb-1">{{ t('raffles.create') }}</h1>
                <p class="text-sm text-gray-400">
                  <span class="text-indigo-400">{{ store.name }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
      <template #default>
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <form class="space-y-6 bg-gray-800 rounded-xl border border-gray-700 p-6" @submit.prevent="submit">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('raffles.field_name') }} *</label>
              <input
                v-model="form.name"
                type="text"
                required
                maxlength="255"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('raffles.field_description') }}</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
            <RaffleTicketPricingFields
              v-model:ticket-currency="form.ticketCurrency"
              v-model:ticket-price="form.ticketPrice"
              :store-default-currency="store.default_currency"
            />
            <div>
              <label class="flex items-center gap-2 text-sm text-gray-300 mb-2">
                <input v-model="unlimitedTickets" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-indigo-600" />
                {{ t('raffles.unlimited_tickets') }}
              </label>
              <input
                v-if="!unlimitedTickets"
                v-model.number="form.maxTickets"
                type="number"
                min="1"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" class="px-4 py-2 text-sm text-gray-300 hover:text-white" @click="goBack">
                {{ t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
              >
                {{ saving ? t('raffles.saving') : t('raffles.create') }}
              </button>
            </div>
          </form>
        </div>
      </template>
    </AppShowLayout>
  </RafflesPageLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import RafflesPageLayout from '../../components/stores/RafflesPageLayout.vue';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import RaffleTicketPricingFields from '../../components/stores/RaffleTicketPricingFields.vue';
import { useStorePageShell } from '../../composables/useStorePageShell';
import { useRafflesStore } from '../../store/raffles';
import { useFlashStore } from '../../store/flash';
import { buildRafflePricingPayload, defaultRafflePricingForm } from '../../utils/rafflePricing';

const { t } = useI18n();
const router = useRouter();
const flashStore = useFlashStore();
const rafflesStore = useRafflesStore();
const { storeId, store, error, apps, loadStore, goSettings, goSection } = useStorePageShell();

const virtualApp = computed(() => ({ name: t('raffles.create') }));

const form = reactive({
    name: '',
    description: '',
    ticketCurrency: 'EUR',
    ticketPrice: 5,
    maxTickets: 100 as number | null,
});
const unlimitedTickets = ref(false);
const saving = ref(false);
const pricingInitialized = ref(false);

watch(
    () => store.value?.default_currency,
    (currency: string | undefined) => {
        if (pricingInitialized.value || !currency) return;
        const defaults = defaultRafflePricingForm(currency);
        form.ticketCurrency = defaults.ticketCurrency;
        form.ticketPrice = defaults.ticketPrice;
        pricingInitialized.value = true;
    },
    { immediate: true },
);

watch(unlimitedTickets, (v: boolean) => {
    form.maxTickets = v ? null : 100;
});

function goBack() {
    router.push({ name: 'stores-raffles', params: { id: storeId.value } });
}

async function submit() {
    saving.value = true;
    try {
        const raffle = await rafflesStore.createRaffle(storeId.value, {
            name: form.name.trim(),
            description: form.description.trim() || null,
            maxTickets: unlimitedTickets.value ? null : form.maxTickets,
            ...buildRafflePricingPayload({
                ticketCurrency: form.ticketCurrency,
                ticketPrice: form.ticketPrice,
            }),
        });
        flashStore.success(t('raffles.created_success'));
        router.push({ name: 'stores-raffles-show', params: { id: storeId.value, raffleId: raffle.id } });
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        flashStore.error(e.response?.data?.message || t('raffles.create_failed'));
    } finally {
        saving.value = false;
    }
}
</script>
