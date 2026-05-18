<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('raffles.field_ticket_currency') }} *</label>
      <select
        :value="ticketCurrency"
        required
        class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
        @change="onCurrencyChange"
      >
        <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1">{{ priceLabel }} *</label>
      <input
        :value="ticketPrice"
        type="number"
        :min="priceMin"
        :step="priceStep"
        required
        class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
        @input="onPriceInput"
      />
      <p v-if="isSats" class="mt-1 text-xs text-gray-500">{{ t('raffles.field_ticket_price_sats_hint') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { currencies } from '../../data/currencies';
import {
    isRaffleSatsCurrency,
    normalizeRaffleCurrency,
    RAFFLE_SATS_CURRENCY,
    type RafflePricingForm,
} from '../../utils/rafflePricing';

const props = defineProps<{
    ticketCurrency: string;
    ticketPrice: number;
    storeDefaultCurrency?: string | null;
}>();

const emit = defineEmits<{
    'update:ticketCurrency': [value: string];
    'update:ticketPrice': [value: number];
}>();

const { t } = useI18n();

const isSats = computed(() => isRaffleSatsCurrency(props.ticketCurrency));

const priceLabel = computed(() =>
    isSats.value ? t('raffles.field_ticket_price_sats') : t('raffles.field_ticket_price'),
);

const priceMin = computed(() => (isSats.value ? 1 : 0.01));
const priceStep = computed(() => (isSats.value ? 1 : 0.01));

const currencyOptions = computed(() => {
    const storeDefault = props.storeDefaultCurrency
        ? normalizeRaffleCurrency(props.storeDefaultCurrency)
        : null;
    const codes = new Set<string>([RAFFLE_SATS_CURRENCY]);
    if (storeDefault && storeDefault !== RAFFLE_SATS_CURRENCY) {
        codes.add(storeDefault);
    }
    for (const c of currencies) {
        if (c.code !== 'BTC') {
            codes.add(c.code);
        }
    }
    return [...codes].map((code) => {
        const meta = currencies.find((c) => c.code === code);
        return { value: code, label: meta ? `${code} — ${meta.name}` : code };
    });
});

function onCurrencyChange(event: Event) {
    const next = normalizeRaffleCurrency((event.target as HTMLSelectElement).value);
    emit('update:ticketCurrency', next);
    if (isRaffleSatsCurrency(next) && props.ticketPrice < 1) {
        emit('update:ticketPrice', 21000);
    } else if (!isRaffleSatsCurrency(next) && props.ticketPrice > 1000000) {
        emit('update:ticketPrice', 5);
    }
}

function onPriceInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value;
    const parsed = isSats.value ? parseInt(raw, 10) : parseFloat(raw);
    emit('update:ticketPrice', Number.isFinite(parsed) ? parsed : 0);
}

defineExpose({
    toPayload(): RafflePricingForm {
        return { ticketCurrency: props.ticketCurrency, ticketPrice: props.ticketPrice };
    },
});
</script>
